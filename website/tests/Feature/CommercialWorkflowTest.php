<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Http\UploadedFile;
use App\Models\{User,Role,Product,Order,GeneralSetting};
use App\Services\{PosSale,CustomerTerms,OrderManagement};
class CommercialWorkflowTest extends TestCase {
 use RefreshDatabase;
 private function setupSale():array {
  $admin=User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);
  $u=User::factory()->create(['role_id'=>Role::where('name','Customer')->value('id'),'account_type'=>'individual','is_active'=>true]);$c=$u->customerRecord();
  $p=Product::create(['category_id'=>1,'slug'=>'discount-cake','premium_marketing_name'=>'Discount Cake','qr_code'=>'CREDIT','is_active'=>true,'total_selling_price_cad'=>100]);
  $p->inventory()->create(['quantity_on_hand'=>100]);GeneralSetting::find(1)->update(['tax_basis_points'=>0,'delivery_cents'=>0,'pickup_address'=>'Warehouse']);$this->actingAs($admin);return [$admin,$u,$c,$p];
 }
 private function sale($c,$p,$admin,string $method='cash',string $fulfillment='pickup'){
  $service=app(PosSale::class);$q=$service->quote([['id'=>$p->id,'quantity'=>1]],$fulfillment,$admin->id,['customer_id'=>$c->id]);
  return $service->complete($q['quote'],['customer_id'=>$c->id,'payment_method'=>$method,'payment_status'=>'paid','address'=>'Street','city'=>'Toronto','province'=>'ON','postal_code'=>'M1P1A1','country'=>'Canada','phone'=>'123'],$admin->id);
 }
 public function test_product_and_order_discounts_are_customer_scoped_and_snapshotted(){
  [$a,$u,$c,$p]=$this->setupSale();$c->update(['order_discount_bps'=>500]);DB::table('customer_product_discounts')->insert(['customer_id'=>$c->id,'product_id'=>$p->id,'discount_bps'=>500,'enabled'=>true]);
  $o=$this->sale($c,$p,$a);$this->assertEquals(9025,$o->final_total_cents);$this->assertEquals(475,$o->order_discount_cents);$this->assertEquals(9500,$o->items->first()->unit_cents);$this->assertEquals(10000,$o->items->first()->original_unit_cents);$this->assertSame('ready_to_dispatch',$o->status);
  $c->update(['order_discount_bps'=>0]);$this->assertEquals(9025,$o->fresh()->final_total_cents);
  $this->get(route('admin.customers.show',$c))->assertOk()->assertSee('customer-tab-discounts')->assertSee('Save Settings');
  $this->getJson(route('admin.customers.discounts',$c))->assertOk()->assertJsonPath('rows.0.percent',5);
 }
 public function test_credit_block_warning_zero_limit_and_settlement_restore(){
  [$a,$u,$c,$p]=$this->setupSale();$c->update(['credit_limit_cents'=>10000]);$o=$this->sale($c,$p,$a,'credit');$this->assertSame('unpaid',$o->payment_status);$this->assertEquals(10000,app(CustomerTerms::class)->used($c));
  try{$this->sale($c,$p,$a,'credit');$this->fail('Credit exceeded');}catch(\Illuminate\Validation\ValidationException $e){$this->assertStringContainsString('Credit warning',$e->getMessage());}
  $this->sale($c,$p,$a,'cash');$c->update(['credit_policy'=>'warn']);$excess=$this->sale($c,$p,$a,'credit');$this->assertNotNull($excess->credit_warning);
  app(OrderManagement::class)->update($o,['revision'=>0,'status'=>'delivered','payment_status'=>'paid','delivery'=>'0','tax'=>'0'],$a->id);
  $this->assertEquals(10000,app(CustomerTerms::class)->used($c));
  $c->update(['credit_limit_cents'=>0]);$this->assertNotContains('credit',app(CustomerTerms::class)->allowed($c,'pos'));
 }
 public function test_cheque_can_be_delivered_unpaid_then_verified_and_completed(){
  [$a,$u,$c,$p]=$this->setupSale();$c->update(['cheque_days'=>10]);$o=$this->sale($c,$p,$a,'cheque');$this->assertSame('unpaid',$o->payment_status);$this->assertEquals(now()->addDays(10)->toDateString(),$o->cheque_due_date);
  $service=app(OrderManagement::class);$d=['revision'=>0,'status'=>'delivered','payment_status'=>'unpaid','delivery'=>'0','tax'=>'0'];$service->update($o,$d,$a->id);$o->refresh();
  try{$service->update($o,array_replace($d,['revision'=>$o->revision,'status'=>'completed']),$a->id);$this->fail('Unpaid completion');}catch(\Illuminate\Validation\ValidationException $e){$this->assertStringContainsString('payment',$e->getMessage());}
  Storage::fake('local');$this->actingAs($u)->post(route('orders.transfer-receipt.store',$o),['revision'=>$o->revision,'receipt'=>UploadedFile::fake()->createWithContent('receipt.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ioAAAAASUVORK5CYII='))])->assertSessionHasNoErrors();
  $o->refresh();$this->actingAs($a)->post(route('orders.transfer.verify',$o),['revision'=>$o->revision,'received'=>1])->assertSessionHasNoErrors();$o->refresh();$this->assertSame('delivered',$o->status);$this->assertEquals(0,$o->balance_cents);
  $service->update($o,array_replace($d,['revision'=>$o->revision,'status'=>'completed','payment_status'=>'paid']),$a->id);$this->assertSame('completed',$o->fresh()->status);
 }
 public function test_website_discount_cheque_and_permissions(){
  [$a,$u,$c,$p]=$this->setupSale();$c->update(['order_discount_bps'=>1000]);$this->actingAs($u);$this->postJson(route('cart.add',$p),['quantity'=>1])->assertOk();$this->get('/checkout')->assertOk()->assertSee('Additional order discount');
  $data=['checkout_token'=>session('checkout_token'),'fulfillment'=>'pickup','payment_method'=>'cheque','first_name'=>'Test','last_name'=>'Customer','phone'=>'123','email'=>$u->email];
  $this->post('/checkout',$data)->assertSessionHasNoErrors()->assertRedirect('/order-success');$o=Order::first();$this->assertEquals(9000,$o->final_total_cents);$this->assertSame('parked_admin',$o->status);$this->assertSame('unpaid',$o->payment_status);
  $c->update(['payment_permissions'=>['pos'=>['cheque'=>false]]]);$this->actingAs($a);try{$this->sale($c,$p,$a,'cheque');$this->fail('Disabled cheque');}catch(\Illuminate\Validation\ValidationException $e){$this->assertStringContainsString('disabled',$e->getMessage());}
 }
 public function test_overdue_report_and_settings_permissions(){
  [$a,$u,$c,$p]=$this->setupSale();$o=$this->sale($c,$p,$a,'cheque');$this->travel(8)->days();$this->get(route('admin.reports.overdue-cheques'))->assertOk()->assertSee($o->number);
  $this->post(route('admin.customers.terms',$c),['revision'=>0,'credit_limit'=>1000,'overage'=>100,'credit_policy'=>'block','cheque_days'=>15,'permissions'=>['website'=>['cash'=>1]]])->assertSessionHasNoErrors();$this->assertEquals(110000,$c->fresh()->credit_limit_cents+$c->fresh()->overage_cents);
  $this->post(route('admin.customers.terms',$c),['revision'=>0,'credit_limit'=>1,'overage'=>0,'credit_policy'=>'block','cheque_days'=>7])->assertSessionHasErrors('revision');
  $this->actingAs($u)->getJson(route('admin.customers.discounts',$c))->assertRedirect();$this->travelBack();
 }
 public function test_discount_api_saves_only_submitted_rows_and_enforces_revision_and_permissions(){
  [$a,$u,$c,$p]=$this->setupSale();
  $data=['revision'=>0,'order_percent'=>5,'rows'=>[['id'=>$p->id,'percent'=>5,'enabled'=>true]]];
  $this->postJson(route('admin.customers.discounts.save',$c),$data)->assertOk()->assertJsonPath('revision',1);
  $this->assertDatabaseHas('customer_product_discounts',['customer_id'=>$c->id,'product_id'=>$p->id,'discount_bps'=>500,'enabled'=>1]);
  $this->assertEquals(500,$c->fresh()->order_discount_bps);
  $this->postJson(route('admin.customers.discounts.save',$c),$data)->assertUnprocessable();
  $this->postJson(route('admin.customers.discounts.save',$c),array_replace($data,['revision'=>1,'rows'=>[['id'=>$p->id,'percent'=>101,'enabled'=>true]]]))->assertUnprocessable();
  $this->postJson(route('admin.customers.discounts.save',$c),['revision'=>1,'order_percent'=>0,'rows'=>[['id'=>$p->id,'percent'=>5,'enabled'=>false]]])->assertOk();
  $this->assertEquals(10000,app(CustomerTerms::class)->price($p,$c->fresh())['unit_cents']);
  $staff=User::factory()->create(['is_active'=>true,'role_id'=>Role::create(['name'=>'Viewer only','permissions'=>['orders.view']])->id]);
  $this->actingAs($staff)->postJson(route('admin.customers.discounts.save',$c),$data)->assertForbidden();
 }

 public function test_invoices_are_numbered_once_and_customer_balances_track_settlements(){
  [$a,$u,$c,$p]=$this->setupSale();$c->update(['credit_limit_cents'=>200000,'overage_cents'=>10000]);
  $credit=$this->sale($c,$p,$a,'credit');$cheque=$this->sale($c,$p,$a,'cheque');$cash=$this->sale($c,$p,$a,'cash');
  $this->assertEquals(4000,$credit->invoice->number);$this->assertEquals(4001,$cheque->invoice->number);$this->assertEquals(4002,$cash->invoice->number);
  app(\App\Services\CustomerInvoicing::class)->issue($credit);
  $this->assertDatabaseCount('customer_invoices',3);
  $service=app(\App\Services\CustomerInvoicing::class);$b=$service->balances($c);
  $this->assertSame(20000,$b['receivables']);$this->assertSame(20000,$b['pending']);$this->assertSame(10000,$b['used']);$this->assertSame(200000,$b['available']);
  $credit->settlements()->create(['amount_cents'=>2500,'method'=>'cash','note'=>'Part payment','user_id'=>$a->id]);
  $b=$service->balances($c);$this->assertSame(17500,$b['pending']);$this->assertSame(7500,$b['used']);$this->assertSame(202500,$b['available']);
  $this->get(route('admin.customers.show',$c))->assertOk()->assertSee('INV-4000')->assertSee('Payment entry #')->assertDontSee('Not configured');
  $this->get(route('admin.invoices.show',$credit->invoice))->assertOk()->assertSee('INV-4000')->assertSee('100.00')->assertSee('75.00');
  $credit->update(['subtotal_cents'=>12000]);$this->assertEquals(10000,$credit->invoice->fresh()->total_cents);
  $this->get(route('admin.invoices.show',$credit->invoice))->assertSee('Subsequent order adjustment');
  $cheque->update(['status'=>'cancelled']);$this->assertSame(9500,$service->balances($c)['receivables']);
 }
 public function test_invoice_access_email_deduplication_and_historical_backfill(){
  [$a,$u,$c,$p]=$this->setupSale();$o=$this->sale($c,$p,$a);$invoice=$o->invoice;
  $this->post(route('admin.invoices.email',$invoice))->assertSessionHasNoErrors();
  $this->post(route('admin.invoices.email',$invoice))->assertSessionHasNoErrors();
  $this->assertSame(1,\App\Models\EmailHistory::where('event_key','invoice-email:'.$invoice->id)->count());
  $this->get('/invoice-document/'.$invoice->id)->assertForbidden();
  $signed=\Illuminate\Support\Facades\URL::temporarySignedRoute('invoice.document',now()->addDays(1),['invoice'=>$invoice->id]);
  $this->actingAs($u)->get(route('admin.invoices.show',$invoice))->assertRedirect();
  $this->get($signed)->assertOk()->assertSee($invoice->label);
  $o->invoice()->delete();$o->updateQuietly(['status'=>'delivered']);
  $this->artisan('invoices:backfill')->assertSuccessful();$this->artisan('invoices:backfill')->assertSuccessful();
  $this->assertDatabaseCount('customer_invoices',1);$this->assertTrue($o->invoice()->first()->historical);
 }
 public function test_website_invoice_waits_for_packing_confirmation(){
  [$a,$u,$c,$p]=$this->setupSale();
  $o=$this->sale($c,$p,$a,'cheque');$o->invoice()->delete();
  $o->update(['source'=>'website','status'=>'warehouse_pending']);$o->items()->update(['packed'=>false]);
  $items=$o->items()->get()->mapWithKeys(fn($i)=>[$i->id=>['packed'=>true,'note'=>'']])->all();
  $this->assertNull($o->invoice()->first());
  app(\App\Services\WarehousePacking::class)->save($o,['revision'=>$o->revision,'items'=>$items,'action'=>'ready','note'=>''],$a->id);
  $this->assertNotNull($o->invoice()->first());$this->assertSame('unpaid',$o->fresh()->payment_status);
 }

 public function test_shared_a4_receipts_require_received_funds_and_correct_order(){
  [$a,$u,$c,$p]=$this->setupSale();$cash=$this->sale($c,$p,$a,'cash');$credit=$this->sale($c,$p,$a,'cheque');
  $this->get(route('admin.orders.initial-payment-document',$cash))->assertOk()->assertSee('Payment Receipt')->assertSee('size:A4 portrait')->assertSee('Bill to')->assertSee('Account summary');
  $this->get(route('admin.orders.initial-payment-document',$credit))->assertNotFound();
  $entry=$credit->settlements()->create(['amount_cents'=>2500,'method'=>'cash','note'=>'Received at counter','user_id'=>$a->id]);
  $this->get(route('admin.orders.payment-document',[$credit,$entry]))->assertOk()->assertSee('RCPT-'.$entry->id)->assertSee('$25.00');
  $this->get(route('admin.orders.payment-document',[$cash,$entry]))->assertNotFound();
  $this->actingAs($u)->get(route('admin.orders.payment-document',[$credit,$entry]))->assertRedirect();
 }
}
