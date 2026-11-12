<?php

namespace Tests\Feature;

use App\Models\{EmailHistory, GeneralSetting, Order, Product, Role, User};
use App\Services\{CustomerInvoicing, DeliverySchedule, DocumentTheme};
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeliveryScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function fixture(): array
    {
        $this->travelTo(Carbon::parse('2026-11-12 04:30:00', 'UTC'));
        GeneralSetting::findOrFail(1)->update(['matrix_delivery_enabled'=>false, 'pickup_address'=>'Sample pickup counter', 'delivery_cents'=>500, 'tax_basis_points'=>0]);
        $user=User::factory()->create(['name'=>'Scheduled Customer', 'account_type'=>'individual', 'is_active'=>true, 'email_verified_at'=>now(), 'phone'=>'555123', 'role_id'=>Role::where('name','Customer')->value('id')]);
        $customer=$user->customerRecord();
        $address=['address'=>'10 Warehouse Street','city'=>'Toronto','province'=>'ON','postal_code'=>'M1P 1A1','country'=>'Canada'];
        $customer->update($address);
        $admin=User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'), 'is_active'=>true]);
        $product=Product::create(['category_id'=>1,'slug'=>'scheduled-cake','premium_marketing_name'=>'Scheduled Cake','qr_code'=>'SCHEDULED','is_active'=>true,'total_selling_price_cad'=>10]);
        $product->inventory()->create(['quantity_on_hand'=>10]);
        return compact('user','customer','admin','product','address');
    }

    private function checkout(array $f): array
    {
        $this->actingAs($f['user']);
        $this->postJson(route('cart.add',$f['product']),['quantity'=>1])->assertOk();
        $this->get('/checkout')->assertOk();
        return ['checkout_token'=>session('checkout_token'), 'fulfillment'=>'delivery', 'first_name'=>'Scheduled', 'last_name'=>'Customer',
            'email'=>$f['user']->email, 'phone'=>'555123', 'payment_method'=>'cash'] + $f['address'];
    }

    private function pos(array $f, string $fulfillment='delivery'): array
    {
        $this->actingAs($f['admin']);
        $quote=$this->postJson(route('admin.pos.quote'), ['customer_id'=>$f['customer']->id, 'items'=>[['id'=>$f['product']->id,'quantity'=>1]], 'fulfillment'=>$fulfillment])->assertOk()->json();
        return ['customer_id'=>$f['customer']->id, 'quote'=>$quote['quote'], 'payment_method'=>'cash', 'payment_status'=>'unpaid', 'phone'=>'555123'] + $f['address'];
    }

    private function assertInputs(string $html, string $date, string $time): void
    {
        $dom=new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath=new \DOMXPath($dom);
        foreach (['delivery_date'=>$date,'delivery_time'=>$time] as $name=>$value) {
            $this->assertSame($value,$xpath->query('//input[@name="'.$name.'"]')->item(0)->getAttribute('value'));
        }
        $this->assertStringContainsString('Toronto time',$html);
    }

    public function test_both_forms_default_to_two_hours_ahead_in_toronto_across_midnight(): void
    {
        $f=$this->fixture(); $this->checkout($f);
        $this->assertInputs($this->get('/checkout')->assertOk()->getContent(),'2026-11-12','01:30');
        $this->assertInputs($this->actingAs($f['admin'])->get(route('admin.pos.index'))->assertOk()->getContent(),'2026-11-12','01:30');
        $this->assertSame('2026-11-11',BusinessTime::now()->format('Y-m-d'));
    }

    public function test_customer_selection_is_saved_in_utc_and_shown_in_orders_emails_and_invoice_snapshots(): void
    {
        $f=$this->fixture(); $data=$this->checkout($f)+['delivery_date'=>'2026-11-13','delivery_time'=>'14:45'];
        $this->post('/checkout',$data)->assertRedirect('/order-success')->assertSessionHasNoErrors();
        $order=Order::sole();
        $this->assertSame('2026-11-13 19:45:00',$order->delivery_scheduled_at->format('Y-m-d H:i:s'));
        foreach (['/order-success','/order-print',route('customer.order',$order)] as $url) {
            $this->get($url)->assertOk()->assertSee('Fri, 13 Nov 2026, 2:45 PM EST')->assertSee('(Toronto)');
        }
        $this->actingAs($f['admin'])->get(route('admin.orders.show',$order))->assertOk()->assertSee('2:45 PM EST');
        $this->get(route('admin.orders.print',$order))->assertOk()->assertSee('2:45 PM EST');
        $emails=EmailHistory::where('order_id',$order->id)->get();
        $this->assertCount(2,$emails);
        foreach ($emails as $email) {
            $this->assertStringContainsString('2:45 PM EST',$email->payload['body']);
            $this->assertStringContainsString('2:45 PM EST',$email->payload['document']['doc']['delivery']);
        }
        $order->updateQuietly(['status'=>'ready_to_dispatch']);
        $invoice=app(CustomerInvoicing::class)->issue($order);
        $before=$invoice->fresh()->getRawOriginal();
        $order->updateQuietly(['delivery_scheduled_at'=>'2026-11-14 20:00:00']);
        $this->assertStringContainsString('13 Nov 2026, 2:45 PM EST',app(DocumentTheme::class)->order($order->fresh(),$invoice)['delivery']);
        $this->assertSame($before,$invoice->fresh()->getRawOriginal());
    }

    public function test_admin_can_change_the_time_after_price_review_and_retry_without_creating_another_order(): void
    {
        $f=$this->fixture(); $data=$this->pos($f)+['delivery_date'=>'2026-11-12','delivery_time'=>'01:00'];
        $this->postJson(route('admin.pos.store'),$data)->assertOk();
        $order=Order::sole();
        $this->assertSame('2026-11-12 06:00:00',$order->getRawOriginal('delivery_scheduled_at'));
        $this->get(route('admin.pos.order',$order))->assertOk()->assertSee('1:00 AM EST');
        $this->get(route('admin.pos.receipt',$order))->assertOk()->assertSee('1:00 AM EST');
        $this->travel(3)->hours();
        $this->postJson(route('admin.pos.store'),$data)->assertOk();
        $this->assertDatabaseCount('orders',1);
        $this->assertEquals(9,$f['product']->inventory()->first()->quantity_on_hand);
        $this->assertSame('2026-11-12 06:00:00',$order->fresh()->getRawOriginal('delivery_scheduled_at'));
    }

    public function test_invalid_missing_or_past_selections_are_rejected_without_creating_orders(): void
    {
        $f=$this->fixture(); $web=$this->checkout($f); $pos=$this->pos($f);
        foreach ([['delivery_date'=>'','delivery_time'=>''], ['delivery_date'=>'2026-11-13'],
            ['delivery_date'=>'2026-02-30','delivery_time'=>'10:00'], ['delivery_date'=>['2026-11-13'],'delivery_time'=>'10:00'],
            ['delivery_date'=>'2026-11-13','delivery_time'=>'25:00'], ['delivery_date'=>'2026-11-11','delivery_time'=>'23:29'],
            ['delivery_date'=>'2027-03-14','delivery_time'=>'02:30']] as $invalid) {
            $this->actingAs($f['user'])->postJson('/checkout',$web+$invalid)->assertUnprocessable();
            $this->actingAs($f['admin'])->postJson(route('admin.pos.store'),$pos+$invalid)->assertUnprocessable();
        }
        $this->assertDatabaseCount('orders',0);
        $this->assertEquals(10,$f['product']->inventory()->first()->quantity_on_hand);
    }

    public function test_selected_fields_survive_customer_validation_errors(): void
    {
        $f=$this->fixture(); $data=$this->checkout($f)+['delivery_date'=>'2026-11-15','delivery_time'=>'09:20'];
        $data['phone']='';
        $this->post('/checkout',$data)->assertSessionHasErrors('phone')->assertSessionHasInput('delivery_date','2026-11-15');
        $this->assertInputs($this->get('/checkout')->assertOk()->getContent(),'2026-11-15','09:20');
    }

    public function test_pickup_ignores_schedule_and_customer_retry_preserves_the_original_selection(): void
    {
        $f=$this->fixture(); $web=$this->checkout($f);
        $this->post('/checkout',array_replace($web,['fulfillment'=>'pickup','delivery_date'=>'bad','delivery_time'=>'bad']))->assertRedirect('/order-success');
        $this->assertNull(Order::sole()->delivery_scheduled_at);
        $pos=$this->pos($f,'pickup');
        $this->postJson(route('admin.pos.store'),$pos+['delivery_date'=>'bad','delivery_time'=>'bad'])->assertOk();
        $this->assertSame(0,Order::whereNotNull('delivery_scheduled_at')->count());
        $data=$this->checkout($f)+app(DeliverySchedule::class)->defaults();
        $this->post('/checkout',$data)->assertRedirect('/order-success');
        $order=Order::latest('id')->first(); $saved=$order->getRawOriginal('delivery_scheduled_at');
        $this->travel(3)->hours();
        $this->post('/checkout',$data)->assertRedirect('/order-success');
        $this->assertDatabaseCount('orders',3);
        $this->assertSame($saved,$order->fresh()->getRawOriginal('delivery_scheduled_at'));
    }

    public function test_older_forms_without_schedule_fields_receive_the_two_hour_default(): void
    {
        $f=$this->fixture(); $data=$this->checkout($f);
        $this->post('/checkout',$data)->assertRedirect('/order-success');
        $this->postJson(route('admin.pos.store'),$this->pos($f))->assertOk();
        foreach (Order::all() as $order) $this->assertSame('2026-11-12 06:30:00',$order->getRawOriginal('delivery_scheduled_at'));
    }

    public function test_defaults_follow_elapsed_time_through_both_daylight_saving_changes(): void
    {
        $service=app(DeliverySchedule::class);
        foreach (['2027-03-14 05:30:00'=>['2027-03-14','03:30','2027-03-14 07:30:00'],
            '2027-11-07 03:30:00'=>['2027-11-07','01:30','2027-11-07 05:30:00'],
            '2027-11-07 04:30:00'=>['2027-11-07','01:30','2027-11-07 06:30:00']] as $now=>[$date,$time,$utc]) {
            $this->travelTo(Carbon::parse($now,'UTC'));
            $defaults=$service->defaults();
            $this->assertSame($date,$defaults['delivery_date']); $this->assertSame($time,$defaults['delivery_time']);
            $this->assertSame($utc,$service->resolve($defaults,true)->format('Y-m-d H:i:s'));
        }
    }

    public function test_changed_visible_time_cannot_be_overridden_by_a_stale_default_reference(): void
    {
        $this->travelTo(Carbon::parse('2026-07-01 12:00:00','UTC'));
        $service=app(DeliverySchedule::class);
        $input=array_replace($service->defaults(),['delivery_date'=>'2026-07-02','delivery_time'=>'16:00']);
        $this->assertSame('2026-07-02 20:00:00',$service->resolve($input,true)->format('Y-m-d H:i:s'));
        $this->expectException(ValidationException::class);
        $service->resolve(['delivery_date'=>'2026-11-01','delivery_time'=>'01:30'],true);
    }
}
