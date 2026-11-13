<?php

namespace Tests\Feature;

use App\Models\{Customer, CustomerRefund, CustomerRefundLine, Order, OrderCredit, Payment, Product, Role, User};
use App\Services\{AdminDashboard, BusinessReports, CustomerDocuments, CustomerInvoicing, CustomerRefunds, DocumentTheme, OnlinePayments};
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerRefundTest extends TestCase
{
    use RefreshDatabase;

    private function super(): User { return User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]); }
    private function customer(): Customer { return User::factory()->create(['role_id'=>Role::where('name','Customer')->value('id'),'account_type'=>'individual','is_active'=>true])->customerRecord(); }
    private function order(Customer $customer, array $extra=[]): Order
    {
        return Order::create($extra + ['customer_id'=>$customer->id,'checkout_token'=>Str::uuid(),'number'=>'REFUND-'.Str::random(7),
            'source'=>'website','first_name'=>$customer->name,'last_name'=>'Test','email'=>$customer->email,'phone'=>'123',
            'address'=>'Street','city'=>'Toronto','province'=>'ON','postal_code'=>'M5V','country'=>'Canada',
            'subtotal_cents'=>10000,'delivery_cents'=>0,'tax_cents'=>0,'currency'=>'CAD','payment_method'=>'cash',
            'payment_status'=>'paid','status'=>'delivered','revision'=>0,'ordered_at'=>now()->subDays(10)]);
    }
    private function line(string $method='cash',string $amount='30',array $extra=[]): array
    {
        return $extra + ['key'=>(string)Str::uuid(),'method'=>$method,'amount'=>$amount,'tax'=>'0','refund_date'=>BusinessTime::today()->toDateString(),
            'reference'=>$method==='cash'?'':'BANK-'.Str::random(8),'bank'=>'Test bank','cheque_date'=>BusinessTime::today()->toDateString(),
            'note'=>'Verified payout','verified'=>$method!=='cheque','proof_hash'=>$method==='cash'?null:hash('sha256','test-proof'),'proof_name'=>$method==='cash'?null:'proof.pdf'];
    }
    private function input(Order $order,array $lines=[]): array { return ['order_id'=>$order->id,'request_key'=>(string)Str::uuid(),'reason'=>'Customer returned the incorrect item','lines'=>$lines?:[$this->line()]]; }
    private function confirmed(Customer $customer,array $input): array
    {
        $plan=app(CustomerRefunds::class)->preview($customer,$input,auth()->user());
        return $input+['confirmed'=>1,'preview_hash'=>$plan['preview_hash'],'expires_at'=>$plan['expires_at']];
    }
    private function refund(Order $order,array $lines=[]): CustomerRefund
    {
        return app(CustomerRefunds::class)->create($order->customer,$this->confirmed($order->customer,$this->input($order,$lines)),auth()->user());
    }
    private function act(CustomerRefundLine $line,string $action): void
    {
        $input=['action'=>$action,'revision'=>$line->refund->revision,'reason'=>'Verified with bank','date'=>BusinessTime::today()->toDateString(),'reference'=>'CLEARED-'.Str::random(8)];
        $plan=app(CustomerRefunds::class)->previewAction($line,$input,auth()->user());
        app(CustomerRefunds::class)->act($line,$input+['preview_hash'=>$plan['preview_hash'],'confirmed'=>1],auth()->user());
    }
    private function invalid(callable $callback): void
    {
        try { $callback(); $this->fail('Expected validation to reject this request.'); }
        catch(ValidationException $e) { $this->assertNotEmpty($e->errors()); }
    }

    public function test_paid_order_refund_keeps_zero_due_and_preserves_invoice_items_and_inventory(): void
    {
        $this->actingAs($this->super()); $customer=$this->customer(); $order=$this->order($customer);
        $product=Product::create(['category_id'=>1,'slug'=>'refund-stock','qr_code'=>'REFUND-STOCK','premium_marketing_name'=>'Original item','is_active'=>true]);
        $product->inventory()->create(['quantity_on_hand'=>42]);
        $order->items()->create(['product_id'=>$product->id,'name'=>'Original item','quantity'=>1,'unit_cents'=>10000,'line_cents'=>10000]);
        $invoice=app(CustomerInvoicing::class)->issue($order); $before=$invoice->fresh()->getAttributes();
        $items=$order->items()->get()->toArray(); $moves=DB::table('inventory_movements')->count();
        $refund=$this->refund($order); $order->refresh();
        $this->assertSame(10000,$order->final_total_cents); $this->assertSame(7000,$order->net_received_cents);
        $this->assertSame(3000,(int)$order->refund_credit_cents); $this->assertSame(0,$order->balance_cents);
        $this->assertSame(3000,$order->refunded_cents); $this->assertSame('delivered',$order->status);
        $this->assertSame('Partially refunded · Paid',$order->payment_status_label);
        $this->assertSame($before,$invoice->fresh()->getAttributes()); $this->assertSame($items,$order->items()->get()->toArray());
        $this->assertEquals(42,$product->inventory->fresh()->quantity_on_hand); $this->assertSame($moves,DB::table('inventory_movements')->count());
        $this->assertSame(0,app(CustomerInvoicing::class)->balances($customer)['pending']);
        $row=DB::query()->fromSub(app(AdminDashboard::class)->balances(),'b')->where('id',$order->id)->first();
        $this->assertSame(0,(int)$row->balance_cents); $this->assertSame(7000,(int)$row->adjusted_total_cents);
        $this->assertSame(-3000,(int)DB::query()->fromSub(app(BusinessReports::class)->movements(),'m')->where('order_id',$order->id)->where('entry_kind','refund')->sum('amount_cents'));
        $this->assertSame(3000,$refund->lines->first()->credit->amount_cents);
        $this->assertStringStartsWith('RF-',$refund->number); $this->assertStringStartsWith('CN-',$refund->lines->first()->credit->number);
        $this->assertNotNull($order->deletionBlockReason());
        $doc=app(DocumentTheme::class)->order($order,$invoice); $this->assertSame('-$30.00',$doc['totals']['Current credit notes']);
        $this->assertSame('$0.00',$doc['totals']['Current balance due']);
        $this->refund($order,[$this->line('cash','70')]); $order->refresh();
        $this->assertSame(0,$order->net_received_cents); $this->assertSame(0,$order->balance_cents);
        $this->assertSame('Fully refunded',$order->payment_status_label);
        $this->invalid(fn()=>$this->refund($order,[$this->line('cash','0.01')]));
    }

    public function test_refund_on_partly_paid_and_settlement_funded_orders_preserves_original_due(): void
    {
        $this->actingAs($this->super()); $customer=$this->customer();
        $partial=$this->order($customer,['payment_status'=>'unpaid','manual_collected_cents'=>4000]);
        $this->refund($partial); $partial->refresh(); $this->assertSame(6000,$partial->balance_cents); $this->assertSame(1000,$partial->net_received_cents);
        $this->refund($partial,[$this->line('cash','10')]); $this->assertSame(6000,$partial->fresh()->balance_cents);
        $funded=$this->order($customer,['manual_collected_cents'=>0]);
        $funded->settlements()->create(['amount_cents'=>10000,'entry_kind'=>'payment','method'=>'cash','user_id'=>auth()->id(),'note'=>'Received on account']);
        $this->refund($funded); $this->assertSame(0,$funded->fresh()->balance_cents); $this->assertSame(7000,$funded->fresh()->net_received_cents);
        $this->assertSame(6000,app(CustomerInvoicing::class)->balances($customer)['pending']);
    }

    public function test_mixed_refunds_cheque_reservation_clearance_cancellation_and_saved_versions(): void
    {
        $this->actingAs($this->super()); $order=$this->order($this->customer());
        $refund=$this->refund($order,[$this->line('cash','10'),$this->line('etransfer','20'),$this->line('cheque','40'),$this->line('cheque','30')]);
        $order->refresh(); $this->assertSame(3000,(int)$order->refund_credit_cents); $this->assertSame(7000,(int)$order->pending_refund_cents); $this->assertSame(0,$order->balance_cents);
        $this->assertSame(0,app(CustomerRefunds::class)->account($order)['maximum_cents']);
        $this->assertSame(2,OrderCredit::count()); $originalSnapshot=$refund->events()->first()->snapshot;
        $this->invalid(fn()=>$this->refund($order,[$this->line('cash','0.01')]));
        $cheques=$refund->lines()->where('method','cheque')->get(); $this->act($cheques[0],'clear');
        $order->refresh(); $this->assertSame(7000,(int)$order->refund_credit_cents); $this->assertSame(3000,$order->net_received_cents); $this->assertSame(0,$order->balance_cents);
        $this->invalid(fn()=>$this->act($cheques[0]->fresh(),'clear'));
        $this->act($cheques[1]->fresh(),'cancel'); $order->refresh();
        $this->assertSame(0,(int)$order->pending_refund_cents); $this->assertSame(3000,app(CustomerRefunds::class)->account($order)['maximum_cents']);
        $this->assertSame(3,OrderCredit::count()); $this->assertSame($originalSnapshot,$refund->events()->first()->snapshot);
        $this->assertSame(3,$refund->events()->count());
        $this->assertSame('cancelled',$cheques[1]->fresh()->status);
    }

    public function test_repeated_submission_is_idempotent_and_stale_previews_are_rejected(): void
    {
        $this->actingAs($this->super()); $order=$this->order($this->customer()); $service=app(CustomerRefunds::class);
        $input=$this->confirmed($order->customer,$this->input($order));
        $first=$service->create($order->customer,$input,auth()->user()); $second=$service->create($order->customer,$input,auth()->user());
        $this->assertSame($first->id,$second->id); $this->assertSame(1,OrderCredit::count()); $this->assertSame(1,$order->settlements()->count());
        $changed=$input; $changed['lines'][0]['amount']='31'; $this->invalid(fn()=>$service->create($order->customer,$changed,auth()->user()));
        $stale=$this->confirmed($order->customer,$this->input($order,[$this->line('cash','60')]));
        $this->refund($order,[$this->line('cash','20')]); $this->invalid(fn()=>$service->create($order->customer,$stale,auth()->user()));
        $edit=$this->confirmed($order->customer,$this->input($order)); $edit['reason']='Changed after review';
        $this->invalid(fn()=>$service->create($order->customer,$edit,auth()->user()));
        $expired=$this->confirmed($order->customer,$this->input($order)); $this->travel(21)->minutes();
        $this->invalid(fn()=>$service->create($order->customer,$expired,auth()->user())); $this->travelBack();
    }

    public function test_invalid_amounts_tax_dates_and_missing_evidence_do_not_post(): void
    {
        $this->actingAs($this->super()); $order=$this->order($this->customer(),['subtotal_cents'=>10000,'tax_cents'=>1300]);
        foreach (['0','-1','113.01','1.001','1e2'] as $amount) $this->invalid(fn()=>$this->refund($order,[$this->line('cash',$amount)]));
        foreach ([['tax'=>'14'],['tax'=>'31'],['refund_date'=>BusinessTime::today()->addDay()->toDateString()],['refund_date'=>'2020-01-01'],['verified'=>false]] as $extra) $this->invalid(fn()=>$this->refund($order,[$this->line('cash','30',$extra)]));
        foreach (['etransfer','cheque','card','paypal'] as $method) {
            $this->invalid(fn()=>$this->refund($order,[$this->line($method,'30',['proof_hash'=>null])]));
            $this->invalid(fn()=>$this->refund($order,[$this->line($method,'30',['reference'=>' '])]));
        }
        $this->invalid(fn()=>$this->refund($order,[$this->line('cash','113',['tax'=>'0'])]));
        $this->refund($order,[$this->line('cash','113',['tax'=>'13'])]);
        $this->assertSame(1300,(int)$order->fresh()->refund_credit_tax_cents); $this->assertSame(0,$order->fresh()->balance_cents);
    }

    public function test_duplicate_references_and_wrong_order_are_rejected(): void
    {
        $this->actingAs($this->super()); $customer=$this->customer(); $order=$this->order($customer);
        $this->refund($order,[$this->line('etransfer','10',['reference'=>'REF123'])]);
        $this->invalid(fn()=>$this->refund($order,[$this->line('etransfer','10',['reference'=>' ref123 '])]));
        $this->invalid(fn()=>$this->refund($order,[$this->line('cheque','10',['reference'=>'DUP']),$this->line('cheque','10',['reference'=>'dup'])]));
        foreach (['parked_admin','warehouse_pending','packing','out_for_delivery','cancelled'] as $status) {
            $bad=$this->order($customer,['status'=>$status]); $this->invalid(fn()=>$this->refund($bad));
        }
        $foreign=$this->order($this->customer());
        $this->postJson(route('admin.customers.refunds.preview',$customer),$this->input($foreign))->assertNotFound();
    }

    public function test_cheque_clearance_requires_bank_confirmation_and_available_funds(): void
    {
        $this->actingAs($this->super()); $order=$this->order($this->customer());
        $refund=$this->refund($order,[$this->line('cheque','80')]); $line=$refund->lines->first();
        $order->forceFill(['manual_collected_cents'=>5000])->saveQuietly();
        $this->invalid(fn()=>$this->act($line,'clear')); $this->assertSame(0,OrderCredit::count());
        $this->act($line->fresh(),'cancel'); $this->assertSame(0,(int)$order->fresh()->pending_refund_cents);
    }

    public function test_http_permissions_documents_uploads_and_customer_isolation(): void
    {
        Storage::fake('local'); $this->actingAs($this->super()); $customer=$this->customer(); $order=$this->order($customer);
        $this->get(route('admin.customers.refunds.create',[$customer,'order_id'=>$order->id]))->assertOk()->assertSee('Review Refund');
        $this->getJson(route('admin.customers.refunds.orders',[$customer,'q'=>$order->number]))->assertOk()->assertJsonPath('results.0.id',$order->id);
        $input=$this->input($order,[$this->line('etransfer')]); $input['lines'][0]['proof']=UploadedFile::fake()->image('proof.jpg');
        $plan=$this->postJson(route('admin.customers.refunds.preview',$customer),$input)->assertOk()->json();
        $this->postJson(route('admin.customers.refunds.store',$customer),$input+['confirmed'=>1,'preview_hash'=>$plan['preview_hash'],'expires_at'=>$plan['expires_at']])->assertOk();
        $refund=CustomerRefund::firstOrFail(); $line=$refund->lines->first(); Storage::disk('local')->assertExists($line->proof_path);
        $this->get(route('admin.customers.show',$customer))->assertOk()->assertSee('Add Refund')->assertSee($refund->number);
        $this->get(route('admin.orders.show',$order))->assertOk()->assertSee($refund->number);
        $this->get(route('admin.customers.refunds.show',[$customer,$refund]))->assertOk()->assertSee('Current order amount due');
        $this->get(route('admin.customers.refunds.document',[$customer,$refund]))->assertOk()->assertSee('Refund Confirmation')->assertSee('30.00')->assertSee('0.00');
        $this->get(route('admin.customers.refunds.credit',[$customer,$refund,$line->credit]))->assertOk()->assertSee('Credit Note')->assertSee('GST/HST credited');
        $this->get(route('admin.customers.refunds.proof',[$customer,$refund,$line]))->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $other=$this->customer();
        $this->get(route('admin.customers.refunds.show',[$other,$refund]))->assertNotFound();
        $this->get(route('admin.customers.refunds.proof',[$other,$refund,$line]))->assertNotFound();
        $this->actingAs($customer->user);
        $this->get(route('customer.documents'))->assertOk()->assertSee('Credit note');
        $this->get(route('customer.documents.file',['kind'=>'credit-note','document'=>$line->credit->id]))->assertOk()->assertSee($line->credit->number);
        $this->get(route('customer.documents.file',['kind'=>'refund','document'=>$refund->events()->first()->id]))->assertOk()->assertSee('Refund Confirmation');
        $this->get(route('customer.documents.file',['kind'=>'credit-note','document'=>$line->credit->id,'download'=>1]))->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->actingAs($other->user)->get(route('customer.documents.file',['kind'=>'credit-note','document'=>$line->credit->id]))->assertNotFound();
        $documents=app(CustomerDocuments::class); [$record]=$documents->resolve($customer,$customer->user,'credit-note',$line->credit->id); $this->assertSame($line->credit->id,$record->id);
        $staff=User::factory()->create(['role_id'=>Role::create(['name'=>'Refund staff','permissions'=>['orders.view','orders.manage','customers.manage','access.admin.customers.index','access.admin.customers.refunds.create']])->id,'is_active'=>true]); $this->actingAs($staff);
        $this->get(route('admin.customers.refunds.create',$customer))->assertForbidden();
        $this->postJson(route('admin.customers.refunds.preview',$customer),$this->input($order))->assertForbidden();
        $this->get(route('admin.customers.refunds.proof',[$customer,$refund,$line]))->assertForbidden();
    }

    public function test_online_order_external_refund_is_counted_once_and_blocks_another_gateway_payout(): void
    {
        Http::preventStrayRequests(); $this->actingAs($this->super()); $order=$this->order($this->customer(),['payment_method'=>'card']);
        $payment=Payment::create(['order_id'=>$order->id,'provider'=>'helcim','mode'=>'live','status'=>'paid','amount_cents'=>10000,'currency'=>'CAD',
            'paid_at'=>now(),'transaction_id'=>'TEST-ONLY','reference'=>(string)Str::uuid(),'expires_at'=>now()->addHour(),'refunded_cents'=>0]);
        $this->invalid(fn()=>$this->refund($order,[$this->line('card','30')]));
        $this->refund($order); $order->refresh(); $this->assertSame(7000,$order->net_received_cents); $this->assertSame(0,$order->balance_cents);
        $this->assertSame(0,(int)$payment->fresh()->refunded_cents);
        try { app(OnlinePayments::class)->refund($payment,auth()->id()); $this->fail('Gateway payout must be blocked.'); }
        catch(\RuntimeException $e) { $this->assertStringContainsString('customer refunds',$e->getMessage()); }
        Http::assertNothingSent();
    }

    public function test_gateway_pending_refunds_and_sandbox_payments_block_new_refunds(): void
    {
        $this->actingAs($this->super()); $order=$this->order($this->customer(),['payment_method'=>'card']);
        $payment=Payment::create(['order_id'=>$order->id,'provider'=>'helcim','mode'=>'sandbox','status'=>'paid','amount_cents'=>10000,'currency'=>'CAD',
            'paid_at'=>now(),'transaction_id'=>'TEST-ONLY','reference'=>(string)Str::uuid(),'expires_at'=>now()->addHour(),'refunded_cents'=>0]);
        $this->invalid(fn()=>$this->refund($order)); $payment->update(['mode'=>'live']);
        DB::table('payment_refunds')->insert(['payment_id'=>$payment->id,'provider_refund_id'=>'PENDING-TEST','amount_cents'=>1000,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
        $this->invalid(fn()=>$this->refund($order)); $this->assertSame(0,CustomerRefund::count());
    }

    public function test_cheque_preview_changes_and_tampered_proofs_cannot_be_committed(): void
    {
        Storage::fake('local'); $this->actingAs($this->super()); $customer=$this->customer(); $order=$this->order($customer);
        $forged=$this->input($order,[$this->line('etransfer')]);
        $this->postJson(route('admin.customers.refunds.preview',$customer),$forged)->assertUnprocessable()->assertJsonValidationErrors('lines.0.proof');
        $input=$this->input($order,[$this->line('etransfer')]); $input['lines'][0]['proof']=UploadedFile::fake()->image('proof.jpg');
        $plan=$this->postJson(route('admin.customers.refunds.preview',$customer),$input)->assertOk()->json();
        $input['lines'][0]['proof']=UploadedFile::fake()->image('changed.jpg',20,20);
        $this->postJson(route('admin.customers.refunds.store',$customer),$input+['confirmed'=>1,'preview_hash'=>$plan['preview_hash'],'expires_at'=>$plan['expires_at']])->assertUnprocessable();
        $this->assertSame([],Storage::disk('local')->allFiles('customer-refund-proofs'));
        $refund=$this->refund($order,[$this->line('cheque','30')]); $line=$refund->lines->first();
        $action=['action'=>'clear','revision'=>1,'reason'=>'Bank verified','date'=>BusinessTime::today()->toDateString(),'reference'=>'CLEAR-1'];
        $preview=app(CustomerRefunds::class)->previewAction($line,$action,auth()->user());
        $this->refund($order,[$this->line('cash','10')]);
        $this->invalid(fn()=>app(CustomerRefunds::class)->act($line,$action+['preview_hash'=>$preview['preview_hash'],'confirmed'=>1],auth()->user()));
        $this->assertSame('awaiting_clearance',$line->fresh()->status);
        $future=$this->refund($order,[$this->line('cheque','10',['cheque_date'=>BusinessTime::today()->addDays(3)->toDateString()])]);
        $this->invalid(fn()=>$this->act($future->lines->first(),'clear'));
    }

    public function test_completed_orders_are_eligible_and_credit_tax_limits_include_pending_cheques(): void
    {
        $this->actingAs($this->super()); $order=$this->order($this->customer(),['status'=>'completed','subtotal_cents'=>10000,'tax_cents'=>1300]);
        $this->refund($order,[$this->line('cheque','56.50',['tax'=>'6.50'])]);
        $this->invalid(fn()=>$this->refund($order,[$this->line('cash','56.50',['tax'=>'6.51'])]));
        $this->refund($order,[$this->line('cash','56.50',['tax'=>'6.50'])]);
        $this->assertSame(0,$order->fresh()->balance_cents); $this->assertSame('completed',$order->fresh()->status);
        $this->assertSame(650,(int)$order->fresh()->refund_credit_tax_cents); $this->assertSame(650,(int)$order->fresh()->pending_refund_tax_cents);
        $summary=app(BusinessReports::class)->orderSummary(app(BusinessReports::class)->orders());
        $this->assertSame(11300,(int)$summary['value']); $this->assertSame(5650,(int)$summary['credits']); $this->assertSame(5650,(int)$summary['adjusted_value']); $this->assertSame(0,(int)$summary['due']);
    }

    public function test_cheque_http_review_and_clearance_require_confirmation_and_only_post_once(): void
    {
        $this->actingAs($this->super()); $customer=$this->customer(); $order=$this->order($customer);
        $refund=$this->refund($order,[$this->line('cheque','30')]); $line=$refund->lines->first();
        $url=route('admin.customers.refunds.action',[$customer,$refund,$line]);
        $input=['action'=>'clear','revision'=>1,'reason'=>'Bank statement verified','date'=>BusinessTime::today()->toDateString(),'reference'=>'CLEAR-HTTP'];
        $this->postJson($url,$input)->assertUnprocessable();
        $plan=$this->postJson(route('admin.customers.refunds.action-preview',[$customer,$refund,$line]),$input)->assertOk()->json();
        $confirmed=$input+['confirmed'=>1,'preview_hash'=>$plan['preview_hash']];
        $this->postJson($url,$confirmed)->assertOk();
        $this->postJson($url,$confirmed)->assertUnprocessable();
        $this->assertSame('cleared',$line->fresh()->status); $this->assertSame(1,OrderCredit::count());
        $this->assertSame(0,$order->fresh()->balance_cents); $this->assertSame(7000,$order->fresh()->net_received_cents);
    }
}
