<?php

namespace Tests\Feature;

use App\Models\{GeneralSetting, Order, Product, Role, User};
use App\Services\{CustomerInvoicing, DeliverySchedule, DocumentTheme};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\{Carbon, Str};
use Tests\TestCase;

class OrderDeliveryScheduleEditTest extends TestCase
{
    use RefreshDatabase;

    protected function fixture(string $source='website', ?string $scheduled='2026-11-13 19:30:00'): array
    {
        $this->travelTo(Carbon::parse('2026-11-12 15:00:00','UTC'));
        GeneralSetting::findOrFail(1)->update(['matrix_delivery_enabled'=>false,'pickup_address'=>'Sample pickup counter','tax_basis_points'=>0]);
        $admin=User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);
        $user=User::factory()->create(['name'=>'Delivery Customer','is_active'=>true,'account_type'=>'individual','email_verified_at'=>now(),'role_id'=>Role::where('name','Customer')->value('id')]);
        $product=Product::create(['category_id'=>1,'slug'=>Str::uuid(),'premium_marketing_name'=>'Sample Cake','qr_code'=>Str::uuid(),'is_active'=>true,'total_selling_price_cad'=>10]);
        $product->inventory()->create(['quantity_on_hand'=>9]);
        $order=Order::create(['checkout_token'=>Str::uuid(),'number'=>'EDIT-'.Str::random(6),'source'=>$source,'customer_id'=>$user->customerRecord()->id,'created_by'=>$source==='pos'?$admin->id:$user->id,
            'first_name'=>'Delivery','last_name'=>'Customer','email'=>$user->email,'phone'=>'555123','address'=>'10 Warehouse Street','city'=>'Toronto','province'=>'ON','postal_code'=>'M1P 1A1','country'=>'Canada',
            'subtotal_cents'=>1000,'delivery_cents'=>500,'tax_cents'=>0,'tax_basis_points'=>0,'currency'=>'CAD','payment_method'=>'cash','payment_status'=>'paid','status'=>'parked_admin',
            'fulfillment'=>'delivery','delivery_scheduled_at'=>$scheduled,'ordered_at'=>now()]);
        $order->items()->create(['product_id'=>$product->id,'name'=>'Sample Cake','product_code'=>'CAKE-1','qr_code'=>$product->qr_code,'quantity'=>1,'unit_cents'=>1000,'line_cents'=>1000,'stock_deducted'=>true]);
        $this->actingAs($admin);
        return ['order'=>$order->fresh(),'admin'=>$admin,'user'=>$user,'product'=>$product];
    }

    protected function payload(Order $order, array $schedule=[]): array
    {
        return ['revision'=>$order->revision,'items'=>[$order->items()->first()->id=>['quantity'=>1,'unit_price'=>'10.00']],
            'delivery'=>'5.00','tax'=>'0.00','tax_mode'=>'recalculate','reason'=>'Customer requested a different delivery time']
            + $order->only(['first_name','last_name','email','phone','address','city','province','postal_code','country','fulfillment']) + $schedule;
    }

    private function inputs(string $html): array
    {
        $dom=new \DOMDocument; @$dom->loadHTML($html); $xpath=new \DOMXPath($dom); $values=[];
        foreach (DeliverySchedule::FIELDS as $name) {
            $input=$xpath->query('//form[@id="order-amend-form"]//input[@name="'.$name.'"]')->item(0);
            $this->assertNotNull($input);
            $values[$name]=$input->getAttribute('value');
            if ($name==='delivery_date') $values['min']=$input->getAttribute('min');
        }
        return $values;
    }

    public function test_edit_forms_prefill_saved_toronto_times_for_website_and_admin_orders(): void
    {
        foreach (['website','pos'] as $source) {
            $f=$this->fixture($source);
            $response=$this->get(route('admin.orders.show',$f['order']))->assertOk()->assertSee('Change the saved delivery date and time below.');
            $inputs=$this->inputs($response->getContent());
            $this->assertSame('2026-11-13',$inputs['delivery_date']);
            $this->assertSame('14:30',$inputs['delivery_time']);
            $this->assertSame((string)$f['order']->delivery_scheduled_at->timestamp,$inputs['delivery_time_reference']);
            $this->assertSame('2026-11-13 19:30:00',$f['order']->fresh()->getRawOriginal('delivery_scheduled_at'));
        }
    }

    public function test_changes_save_in_utc_are_audited_and_preserve_payments_stock_and_issued_invoices(): void
    {
        foreach (['website','pos'] as $source) {
            $f=$this->fixture($source); $order=$f['order'];
            $order->updateQuietly(['status'=>'ready_to_dispatch']);
            $invoice=app(CustomerInvoicing::class)->issue($order); $snapshot=$invoice->fresh()->getRawOriginal();
            $financial=$order->only(['subtotal_cents','delivery_cents','tax_cents','payment_status','ordered_at','created_at']);
            $data=$this->payload($order,['delivery_date'=>'2026-11-14','delivery_time'=>'09:45','delivery_time_reference'=>$order->delivery_scheduled_at->timestamp]);
            $this->post(route('admin.orders.amend',$order),$data)->assertSessionHasNoErrors()->assertRedirect();
            $saved=$order->fresh();
            $this->assertSame('2026-11-14 14:45:00',$saved->getRawOriginal('delivery_scheduled_at'));
            $this->assertEquals($financial,$saved->only(array_keys($financial)));
            $this->assertSame(1500,$saved->net_received_cents);
            $this->assertSame(0,$saved->balance_cents);
            $this->assertEquals(9,$f['product']->inventory()->first()->quantity_on_hand);
            $this->assertDatabaseCount('inventory_movements',0);
            $this->assertSame(1,$saved->revision);
            $event=$saved->events()->reorder('id','desc')->first();
            $this->assertSame($f['admin']->id,$event->user_id);
            $this->assertStringContainsString('13 Nov 2026 14:30 EST → 14 Nov 2026 09:45 EST',$event->description);
            $this->assertSame($snapshot,$invoice->fresh()->getRawOriginal());
            $this->assertStringContainsString('13 Nov 2026, 2:30 PM EST',app(DocumentTheme::class)->order($saved,$invoice)['delivery']);
            $this->get(route('admin.orders.show',$order))->assertOk()->assertSee('14 Nov 2026, 9:45 AM EST');
            $this->get(route('admin.orders.print',$order))->assertOk()->assertSee('14 Nov 2026, 9:45 AM EST');
            $this->post(route('admin.orders.amend',$order),$data)->assertSessionHasErrors('order');
            $this->assertSame(1,$order->fresh()->revision);
            $this->actingAs($f['user'])->get(route('customer.order',$order))->assertOk()->assertSee('14 Nov 2026, 9:45 AM EST');
        }
    }

    public function test_invalid_or_past_changes_roll_back_the_whole_amendment(): void
    {
        $f=$this->fixture(); $order=$f['order']; $before=$order->getRawOriginal();
        foreach ([['delivery_date'=>'','delivery_time'=>''],['delivery_date'=>'2026-11-14'],
            ['delivery_date'=>'2026-11-11','delivery_time'=>'09:45'],['delivery_date'=>'2026-02-30','delivery_time'=>'09:45'],
            ['delivery_date'=>'2026-11-14','delivery_time'=>'25:00'],['delivery_date'=>['2026-11-14'],'delivery_time'=>'09:45'],
            ['delivery_date'=>'2027-03-14','delivery_time'=>'02:30'],['delivery_date'=>'2027-11-07','delivery_time'=>'01:30']] as $schedule) {
            $data=$this->payload($order,$schedule); $data['items'][$order->items()->first()->id]['quantity']=2;
            $this->postJson(route('admin.orders.amend',$order),$data)->assertUnprocessable();
            $this->assertSame($before,$order->fresh()->getRawOriginal());
        }
        $this->assertEquals(9,$f['product']->inventory()->first()->quantity_on_hand);
        $this->assertSame(1,$order->items()->first()->quantity);
        $this->assertDatabaseCount('inventory_movements',0);
        $this->assertDatabaseCount('order_events',0);
    }

    public function test_unchanged_past_times_and_old_forms_do_not_reschedule_orders(): void
    {
        $f=$this->fixture(scheduled:'2026-11-10 19:30:25'); $order=$f['order'];
        $inputs=$this->inputs($this->get(route('admin.orders.show',$order))->assertOk()->getContent());
        $this->assertSame('2026-11-10',$inputs['min']);
        $this->post(route('admin.orders.amend',$order),$this->payload($order,$inputs))->assertSessionHasNoErrors();
        $this->assertSame('2026-11-10 19:30:25',$order->fresh()->getRawOriginal('delivery_scheduled_at'));
        $this->post(route('admin.orders.amend',$order),$this->payload($order->fresh()))->assertSessionHasNoErrors();
        $this->assertSame('2026-11-10 19:30:25',$order->fresh()->getRawOriginal('delivery_scheduled_at'));
        $this->assertStringNotContainsString('Delivery date & time (Toronto):',$order->events()->reorder('id','desc')->first()->description);
    }

    public function test_pickup_clears_schedule_and_delivery_can_be_scheduled_again(): void
    {
        $f=$this->fixture(); $order=$f['order'];
        $data=array_replace($this->payload($order),['fulfillment'=>'pickup','delivery'=>'0','delivery_date'=>'bad','delivery_time'=>'bad']);
        $this->post(route('admin.orders.amend',$order),$data)->assertSessionHasNoErrors();
        $this->assertNull($order->fresh()->delivery_scheduled_at);
        $this->assertStringContainsString('→ Not set',$order->events()->reorder('id','desc')->first()->description);
        $data=array_replace($this->payload($order->fresh(),['delivery_date'=>'2026-11-14','delivery_time'=>'09:45']),['fulfillment'=>'delivery']);
        $this->post(route('admin.orders.amend',$order),$data)->assertSessionHasNoErrors();
        $this->assertSame('2026-11-14 14:45:00',$order->fresh()->getRawOriginal('delivery_scheduled_at'));
    }

    public function test_legacy_orders_get_an_editable_default_but_old_forms_preserve_null(): void
    {
        $f=$this->fixture(scheduled:null); $order=$f['order'];
        $inputs=$this->inputs($this->get(route('admin.orders.show',$order))->assertOk()->getContent());
        $this->assertSame('2026-11-12',$inputs['delivery_date']); $this->assertSame('12:00',$inputs['delivery_time']);
        $this->assertNull($order->fresh()->delivery_scheduled_at);
        $this->post(route('admin.orders.amend',$order),$this->payload($order))->assertSessionHasNoErrors();
        $this->assertNull($order->fresh()->delivery_scheduled_at);
        $this->post(route('admin.orders.amend',$order),$this->payload($order->fresh(),$inputs))->assertSessionHasNoErrors();
        $this->assertSame('2026-11-12 17:00:00',$order->fresh()->getRawOriginal('delivery_scheduled_at'));
    }

    public function test_failed_validation_keeps_entered_schedule_and_existing_permissions_still_apply(): void
    {
        $f=$this->fixture(); $order=$f['order'];
        $data=$this->payload($order,['delivery_date'=>'2026-11-14','delivery_time'=>'16:20']); $data['reason']='';
        $this->post(route('admin.orders.amend',$order),$data)->assertSessionHasErrors('reason');
        $inputs=$this->inputs($this->get(route('admin.orders.show',$order))->assertOk()->getContent());
        $this->assertSame('2026-11-14',$inputs['delivery_date']); $this->assertSame('16:20',$inputs['delivery_time']);
        $role=Role::create(['name'=>'Order schedule viewer','permissions'=>['orders.view']]);
        $viewer=User::factory()->create(['role_id'=>$role->id,'is_active'=>true]);
        $data['reason']='Reschedule';
        $this->actingAs($viewer)->post(route('admin.orders.amend',$order),$data)->assertForbidden();
        $this->actingAs($f['admin']);
        foreach (['cancelled','completed','delivered','out_for_delivery'] as $status) {
            $order->updateQuietly(['status'=>$status]);
            $this->post(route('admin.orders.amend',$order),$data)->assertSessionHasErrors('order');
            $this->assertSame('2026-11-13 19:30:00',$order->fresh()->getRawOriginal('delivery_scheduled_at'));
        }
    }

    public function test_saved_repeated_hour_is_preserved_and_summer_edits_use_edt(): void
    {
        $f=$this->fixture(scheduled:'2027-11-07 06:30:00'); $order=$f['order'];
        $inputs=$this->inputs($this->get(route('admin.orders.show',$order))->assertOk()->getContent());
        $this->assertSame('01:30',$inputs['delivery_time']);
        $this->post(route('admin.orders.amend',$order),$this->payload($order,$inputs))->assertSessionHasNoErrors();
        $this->assertSame('2027-11-07 06:30:00',$order->fresh()->getRawOriginal('delivery_scheduled_at'));
        $this->post(route('admin.orders.amend',$order),$this->payload($order->fresh(),['delivery_date'=>'2027-07-01','delivery_time'=>'16:20']))->assertSessionHasNoErrors();
        $this->assertSame('2027-07-01 20:20:00',$order->fresh()->getRawOriginal('delivery_scheduled_at'));
    }
}
