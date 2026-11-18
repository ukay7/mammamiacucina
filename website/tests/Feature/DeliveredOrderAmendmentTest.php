<?php

namespace Tests\Feature;

use App\Models\{CustomerRefund, Order, OrderCredit, OrderRevision, Payment, Product, Role, User};
use App\Services\{CustomerDocuments, CustomerInvoicing, CustomerRefunds, DocumentTheme, OrderAmendment};
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeliveredOrderAmendmentTest extends TestCase
{
    use RefreshDatabase;

    private User $super;
    protected function setUp(): void
    {
        parent::setUp(); Http::preventStrayRequests();
        $this->super=User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);
        $this->actingAs($this->super);
    }
    private function order(array $extra=[]): Order
    {
        $customer=User::factory()->create(['account_type'=>'individual','is_active'=>true,'role_id'=>Role::where('name','Customer')->value('id')])->customerRecord();
        $product=Product::create(['category_id'=>1,'slug'=>Str::uuid(),'qr_code'=>Str::uuid(),'product_code'=>'CAKE-1','premium_marketing_name'=>'Delivered cake','is_active'=>true,'total_selling_price_cad'=>'25.00']);
        $product->inventory()->create(['quantity_on_hand'=>20]);
        $order=Order::create($extra+['customer_id'=>$customer->id,'checkout_token'=>Str::uuid(),'number'=>'AMEND-'.Str::random(8),'source'=>'website',
            'first_name'=>'Buyer','last_name'=>'Owner','email'=>$customer->email,'phone'=>'123','address'=>'Street','city'=>'Toronto','province'=>'ON','postal_code'=>'M5V 2T6','country'=>'Canada',
            'subtotal_cents'=>10000,'delivery_cents'=>0,'tax_cents'=>0,'currency'=>'CAD','payment_method'=>'cash','payment_status'=>'paid','status'=>'delivered','fulfillment'=>'pickup','revision'=>0,'ordered_at'=>now()->subDays(5)]);
        $order->items()->create(['product_id'=>$product->id,'product_code'=>$product->product_code,'name'=>$product->premium_marketing_name,'quantity'=>4,'unit_cents'=>2500,'line_cents'=>10000,'stock_deducted'=>true,'packed'=>true]);
        app(CustomerInvoicing::class)->issue($order);return $order->fresh();
    }
    private function changes(Order $order,int $quantity=3,string $price='25.00',array $extra=[]): array
    {
        return $extra+['revision'=>$order->revision,'items'=>[$order->items()->first()->id=>['quantity'=>$quantity,'unit_price'=>$price]],'fulfillment'=>'pickup',
            'delivery'=>'0','tax'=>'0','tax_mode'=>'manual','reason'=>'Agreed correction after delivery','first_name'=>'Buyer','last_name'=>'Owner'];
    }
    private function refundInput(Order $order,string $amount='25',string $kind='overpayment',string $method='cash'): array
    {
        return ['order_id'=>$order->id,'kind'=>$kind,'request_key'=>(string)Str::uuid(),'reason'=>'Return agreed difference','lines'=>[
            ['key'=>(string)Str::uuid(),'method'=>$method,'amount'=>$amount,'tax'=>'0','refund_date'=>BusinessTime::today()->toDateString(),
                'reference'=>$method==='cash'?'':'BANK-'.Str::random(8),'cheque_date'=>BusinessTime::today()->toDateString(),'verified'=>true,
                'proof_hash'=>$method==='cash'?null:hash('sha256','proof'),'proof_name'=>$method==='cash'?null:'proof.pdf']]];
    }
    private function refund(Order $order,array $data): CustomerRefund
    {
        $service=app(CustomerRefunds::class);$plan=$service->preview($order->customer,$data,$this->super);
        return $service->create($order->customer,$data+['confirmed'=>1,'preview_hash'=>$plan['preview_hash'],'expires_at'=>$plan['expires_at']],$this->super);
    }
    private function invalid(callable $action): void
    {
        try {$action();$this->fail('Expected rejected financial change');}catch(ValidationException $e){$this->assertNotEmpty($e->errors());}
    }

    public function test_only_super_admin_can_edit_delivered_and_completed_orders_in_ui_and_on_server(): void
    {
        $role=Role::create(['name'=>'Order editor','permissions'=>['orders.view','orders.manage'],'is_super'=>false]);
        $staff=User::factory()->create(['is_active'=>true,'role_id'=>$role->id]);
        foreach(['delivered','completed'] as $status) {
            $order=$this->order(['status'=>$status]);$data=$this->changes($order);
            $this->actingAs($staff)->get(route('admin.orders.show',$order))->assertOk()->assertDontSee('id="order-amend-form"',false);
            $this->post(route('admin.orders.amend',$order),$data)->assertForbidden();
            try {app(OrderAmendment::class)->amend($order,$data,$staff->id);$this->fail('Service allowed regular staff');} catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
            $this->actingAs($order->customer->user)->post(route('admin.orders.amend',$order),$data)->assertRedirect(route('customer.orders'));
            $this->assertSame(10000,$order->fresh()->final_total_cents);
            $this->actingAs($this->super)->get(route('admin.orders.show',$order))->assertOk()->assertSee('id="order-amend-form"',false);
            $this->post(route('admin.orders.amend',$order),$data)->assertSessionHasNoErrors();
            $this->assertSame($status,$order->fresh()->status);
        }
    }

    public function test_reduction_preserves_original_payment_invoice_and_stock_and_pays_difference_without_second_credit(): void
    {
        $order=$this->order();$invoice=$order->invoice->getAttributes();$product=$order->items->first()->product;
        $this->post(route('admin.orders.amend',$order),$this->changes($order))->assertSessionHasNoErrors();$order->refresh();
        $this->assertSame(7500,$order->final_total_cents);$this->assertSame(10000,$order->net_received_cents);$this->assertSame(-2500,$order->balance_cents);
        $this->assertEquals(20,$product->inventory->fresh()->quantity_on_hand);$this->assertSame($invoice,$order->invoice->fresh()->getAttributes());
        $revision=OrderRevision::sole();$this->assertSame(4,$revision->before['items'][0]['quantity']);$this->assertSame(3,$revision->after['items'][0]['quantity']);
        $this->assertSame(-2500,$revision->difference_cents);$this->assertFalse($revision->stock_returns[0]['restocked']);
        $this->get(route('admin.orders.show',$order))->assertOk()->assertSee($revision->number);
        $this->get(route('admin.customers.documents.file',[$order->customer_id,'revision',$revision]))->assertOk()->assertSee('Order Adjustment')->assertSee('25.00');
        $beforeRevision=$revision->getAttributes();$data=$this->refundInput($order);$service=app(CustomerRefunds::class);
        $this->invalid(fn()=>$service->preview($order->customer,$this->refundInput($order,'25','return'),$this->super));
        $this->invalid(fn()=>$service->preview($order->customer,$this->refundInput($order,'25.01'),$this->super));
        $plan=$service->preview($order->customer,$data,$this->super);$this->assertSame(0,$plan['balance_after_cents']);
        $confirmed=$data+['confirmed'=>1,'preview_hash'=>$plan['preview_hash'],'expires_at'=>$plan['expires_at']];
        $refund=$service->create($order->customer,$confirmed,$this->super);$service->create($order->customer,$confirmed,$this->super);
        $order->refresh();$this->assertSame(0,$order->balance_cents);$this->assertSame(7500,$order->net_received_cents);$this->assertSame(7500,$order->adjusted_total_cents);
        $this->assertSame(0,OrderCredit::count());$this->assertSame(1,CustomerRefund::count());$this->assertSame(1,$order->settlements()->count());
        $this->assertSame($beforeRevision,$revision->fresh()->getAttributes());$this->assertSame($invoice,$order->invoice->fresh()->getAttributes());
        $this->get(route('admin.customers.refunds.document',[$order->customer_id,$refund]))->assertOk()->assertSee('no additional sales credit');
        $this->assertSame(0,app(CustomerInvoicing::class)->balances($order->customer)['pending']);
        $this->assertStringContainsString('Subsequent order adjustment',app(DocumentTheme::class)->order($order,$order->invoice)['notice']);
    }

    public function test_explicit_return_restocks_once_and_stale_edits_cannot_repeat_it(): void
    {
        $order=$this->order();$item=$order->items->first();$data=$this->changes($order);$data['items'][$item->id]['restock_returned']=1;
        $this->post(route('admin.orders.amend',$order),$data)->assertSessionHasNoErrors();
        $this->post(route('admin.orders.amend',$order),$data)->assertSessionHasErrors('order');
        $this->assertEquals(21,$item->product->inventory->quantity_on_hand);$this->assertSame(1,OrderRevision::count());
        $this->assertTrue(OrderRevision::sole()->stock_returns[0]['restocked']);
    }

    public function test_increased_order_retains_collections_and_exposes_additional_amount_due(): void
    {
        $order=$this->order();$data=$this->changes($order,4,'30.00');
        $this->post(route('admin.orders.amend',$order),$data)->assertSessionHasNoErrors();$order->refresh();
        $this->assertSame(12000,$order->final_total_cents);$this->assertSame(10000,$order->net_received_cents);$this->assertSame(2000,$order->balance_cents);
        $this->assertSame(2000,OrderRevision::sole()->difference_cents);
        $this->assertSame(2000,app(CustomerInvoicing::class)->balances($order->customer)['pending']);
    }

    public function test_prior_sales_credit_and_partially_paid_order_use_actual_remaining_balance(): void
    {
        $order=$this->order();$this->refund($order,$this->refundInput($order,'30','return'));$order->refresh();
        $this->post(route('admin.orders.amend',$order),$this->changes($order))->assertSessionHasNoErrors();$order->refresh();
        $this->assertSame(4500,$order->adjusted_total_cents);$this->assertSame(7000,$order->net_received_cents);$this->assertSame(-2500,$order->balance_cents);
        $this->refund($order,$this->refundInput($order));$order->refresh();
        $this->assertSame(0,$order->balance_cents);$this->assertSame(3000,(int)$order->refund_credit_cents);$this->assertSame(1,OrderCredit::count());
        $partial=$this->order(['payment_status'=>'unpaid','manual_collected_cents'=>2000]);
        $this->post(route('admin.orders.amend',$partial),$this->changes($partial))->assertSessionHasNoErrors();
        $this->assertSame(5500,$partial->fresh()->balance_cents);
    }

    public function test_pending_overpayment_cheque_reserves_money_and_blocks_amendment_then_clears_without_credit(): void
    {
        $order=$this->order();$this->post(route('admin.orders.amend',$order),$this->changes($order))->assertSessionHasNoErrors();$order->refresh();
        $refund=$this->refund($order,$this->refundInput($order,'25','overpayment','cheque'));$order->refresh();
        $this->assertSame(-2500,$order->balance_cents);$this->assertSame(0,app(CustomerRefunds::class)->account($order)['overpayment_maximum_cents']);
        $this->post(route('admin.orders.amend',$order),$this->changes($order,2))->assertSessionHasErrors('order');
        $refund->refresh();$line=$refund->lines->sole();$action=['action'=>'clear','revision'=>$refund->revision,'date'=>BusinessTime::today()->toDateString(),'reference'=>'BANK-CLEARED','reason'=>'Bank confirms payout'];
        $service=app(CustomerRefunds::class);$plan=$service->previewAction($line,$action,$this->super);
        $service->act($line,$action+['confirmed'=>1,'preview_hash'=>$plan['preview_hash']],$this->super);
        $order->refresh();$this->assertSame(0,$order->balance_cents);$this->assertSame(0,(int)$order->pending_refund_cents);$this->assertSame(0,OrderCredit::count());
    }

    public function test_revised_zero_total_can_return_all_collected_money_and_blocks_extra_tax_credit(): void
    {
        $order=$this->order();$this->post(route('admin.orders.amend',$order),$this->changes($order,1,'0'))->assertSessionHasNoErrors();$order->refresh();
        $this->assertSame(10000,app(CustomerRefunds::class)->account($order)['maximum_cents']);
        $invalid=$this->refundInput($order,'100');$invalid['lines'][0]['tax']='1';
        $this->invalid(fn()=>app(CustomerRefunds::class)->preview($order->customer,$invalid,$this->super));
        $this->refund($order,$this->refundInput($order,'100'));$this->assertSame(0,$order->fresh()->balance_cents);
    }

    public function test_customer_revision_documents_are_immutable_and_scoped_to_owner(): void
    {
        $order=$this->order();$this->post(route('admin.orders.amend',$order),$this->changes($order))->assertSessionHasNoErrors();$revision=OrderRevision::sole();
        $owner=$order->customer->user;$this->actingAs($owner);
        $this->get(route('customer.documents.file',['revision',$revision]))->assertOk()->assertSee('Before this revision')->assertSee('After this revision');
        $this->get(route('customer.documents'))->assertOk()->assertSee($revision->number);
        $this->actingAs(User::factory()->create(['account_type'=>'individual','is_active'=>true,'role_id'=>Role::where('name','Customer')->value('id')]))->get(route('customer.documents.file',['revision',$revision]))->assertNotFound();
    }

    public function test_gateway_paid_revision_caps_refunds_before_contacting_provider_and_keeps_payment_fixed(): void
    {
        $order=$this->order(['payment_method'=>'card']);
        $payment=Payment::create(['order_id'=>$order->id,'reference'=>Str::uuid(),'provider'=>'helcim','mode'=>'live','status'=>'paid','amount_cents'=>10000,'refunded_cents'=>0,'currency'=>'CAD','paid_at'=>now(),'expires_at'=>now()->addHour(),'transaction_id'=>'QA-100']);
        $original=$payment->fresh()->getAttributes();
        $this->post(route('admin.orders.amend',$order),$this->changes($order))->assertSessionHasNoErrors();$order->refresh();
        $this->assertSame($original,$payment->fresh()->getAttributes());
        $this->assertSame(-2500,$order->balance_cents);
        $this->get(route('admin.orders.show',$order))->assertOk()->assertSee('max="25"',false)->assertSee('available CAD 25.00');
        try {app(\App\Services\OnlinePayments::class)->refund($payment,$this->super->id,10000,2501);$this->fail('Refund exceeded revised balance');}
        catch(\RuntimeException $e){$this->assertStringContainsString('current Refund Due',$e->getMessage());}
        Http::assertNothingSent();
        $this->refund($order,$this->refundInput($order));$order->refresh();
        $this->assertSame(0,$order->balance_cents);
        try {app(\App\Services\OnlinePayments::class)->refund($payment,$this->super->id,10000,2500);$this->fail('Duplicate external and gateway refund allowed');}
        catch(\RuntimeException $e){$this->assertStringContainsString('already returned outside',$e->getMessage());}
        Http::assertNothingSent();
    }

    public function test_stale_refund_preview_is_rejected_after_another_order_edit(): void
    {
        $order=$this->order();$this->post(route('admin.orders.amend',$order),$this->changes($order))->assertSessionHasNoErrors();$order->refresh();
        $data=$this->refundInput($order);$service=app(CustomerRefunds::class);$plan=$service->preview($order->customer,$data,$this->super);
        $this->post(route('admin.orders.amend',$order),$this->changes($order,4,'20'))->assertSessionHasNoErrors();$order->refresh();
        $this->invalid(fn()=>$service->create($order->customer,$data+['confirmed'=>1,'preview_hash'=>$plan['preview_hash'],'expires_at'=>$plan['expires_at']],$this->super));
        $this->assertSame(-2000,$order->balance_cents);$this->assertSame(0,CustomerRefund::count());
    }
}
