<?php
namespace Tests\Feature;
use App\Models\{Order,Product,User,Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class OrderManagementTest extends TestCase {
 use RefreshDatabase;
 private function setupOrder($stock=10):array {
  $p=Product::create(['category_id'=>1,'slug'=>'management-cake','premium_marketing_name'=>'Management Cake','qr_code'=>'MGMT','is_active'=>true,'total_selling_price_cad'=>10]);$p->inventory()->create(['quantity_on_hand'=>$stock]);
  $this->postJson(route('cart.add',$p),['quantity'=>2])->assertOk();$this->get('/checkout')->assertOk();
  $this->post('/checkout',['checkout_token'=>session('checkout_token'),'first_name'=>'Test','last_name'=>'Customer','email'=>'test@example.test','phone'=>'555123','address'=>'10 Example Street','city'=>'Toronto','province'=>'Ontario','postal_code'=>'M1M1M1','country'=>'Canada'])->assertRedirect('/order-success');
  $o=Order::firstOrFail();$user=User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);$this->actingAs($user);
  return [$o,$p,$user];
 }
 private function save($o,$extra=[]){$o->refresh();return $this->patch(route('admin.orders.update',$o),array_merge(['revision'=>$o->revision,'status'=>$o->status,'payment_status'=>$o->payment_status,'delivery'=>$o->delivery_cents===null?'':number_format($o->delivery_cents/100,2,'.',''),'tax'=>$o->tax_cents===null?'':number_format($o->tax_cents/100,2,'.','')],$extra));}
 public function test_order_dates_default_to_current_month_and_filter_inclusive_days():void {
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-15 12:00:00'));
  [$o]=$this->setupOrder();
  $this->get('/admin/orders')->assertOk()->assertViewHas('from','2026-09-01')->assertViewHas('to','2026-09-30')->assertViewHas('orders',fn($orders)=>$orders->total()===1);
  $o->update(['created_at'=>'2026-08-31 23:59:59']);
  $this->get('/admin/orders')->assertOk()->assertViewHas('orders',fn($orders)=>$orders->total()===0);
  $this->get('/admin/orders?from=2026-08-31&to=2026-08-31&q=Customer')->assertOk()->assertViewHas('orders',fn($orders)=>$orders->total()===1);
  $this->get('/admin/orders?from=2026-08-31&to=2026-08-31&q=missing')->assertOk()->assertViewHas('orders',fn($orders)=>$orders->total()===0);
  $this->get('/admin/orders?from=2026-09-01&to=2026-08-31')->assertSessionHasErrors('to');
  $this->get('/admin/orders?from=invalid')->assertSessionHasErrors('from');
  $this->travelBack();
 }
 public function test_status_charges_cash_and_tracking_totals():void {
  [$o,$p]=$this->setupOrder();$this->save($o,['status'=>'preparing'])->assertSessionHasErrors('order');
  $o->update(['delivery_cents'=>null,'tax_cents'=>null]);$this->save($o,['payment_status'=>'paid'])->assertSessionHasErrors('order');
  $this->save($o,['status'=>'confirmed','delivery'=>'5.50','tax'=>'2.25'])->assertRedirect();$this->assertEquals(2775,$o->fresh()->final_total_cents);
  foreach(['preparing','out_for_delivery','delivered'] as $status)$this->save($o,['status'=>$status])->assertRedirect();
  $this->save($o,['payment_status'=>'paid'])->assertRedirect();$this->assertNotNull($o->fresh()->paid_at);
  $this->save($o,['delivery'=>'9'])->assertSessionHasErrors('order');$this->save($o,['status'=>'cancelled'])->assertSessionHasErrors('order');
  $this->get('/track-order/'.$o->tracking_token)->assertOk()->assertSee('Delivered')->assertSee('$27.75')->assertSee('Amount due: $0.00');
  $this->assertDatabaseCount('order_events',5);
 }
 public function test_cancel_restores_deducted_stock_once_and_rejects_stale_saves():void {
  [$o,$p]=$this->setupOrder();$this->assertSame('8.000',$p->inventory()->first()->quantity_on_hand);$this->save($o,['status'=>'cancelled'])->assertRedirect();$this->assertSame('10.000',$p->inventory()->first()->quantity_on_hand);
  $this->save($o)->assertRedirect();$this->assertSame('10.000',$p->inventory()->first()->quantity_on_hand);
  $this->save($o,['revision'=>0])->assertSessionHasErrors('order');$this->save($o,['status'=>'confirmed'])->assertSessionHasErrors('order');
  $this->assertDatabaseCount('inventory_movements',2);
 }
 public function test_unknown_stock_not_invented_and_paid_cancel_requires_refund():void {
  [$o,$p]=$this->setupOrder(null);$this->save($o,['delivery'=>'0','tax'=>'0','payment_status'=>'paid'])->assertRedirect();$this->save($o,['status'=>'cancelled'])->assertSessionHasErrors('order');
  $this->save($o,['status'=>'cancelled','payment_status'=>'refunded'])->assertRedirect();$this->assertNull($p->inventory()->first()->quantity_on_hand);$this->assertSame('refunded',$o->fresh()->payment_status);
 }
 public function test_number_tracking_shows_owner_details_and_email_verification_unlocks_another_session():void {
  [$o]=$this->setupOrder();
  $this->get('/track-order/'.$o->number)->assertOk()->assertSee('10 Example Street')->assertSee('Management Cake');
  auth()->logout();session()->flush();
  $this->get('/track-order/'.$o->number)->assertOk()->assertDontSee('10 Example Street')->assertSee('Show Order Details');
  $this->post('/track-order/'.$o->number.'/verify',['email'=>'wrong@example.test'])->assertSessionHasErrors('email');
  $this->get('/track-order/'.$o->number.'/print')->assertNotFound();
  $this->post('/track-order/'.$o->number.'/verify',['email'=>'TEST@example.test'])->assertRedirect('/track-order/'.$o->number);
  $this->get('/track-order/'.$o->number)->assertOk()->assertSee('10 Example Street')->assertSee('Management Cake')->assertSee('Print / Save as PDF');
  $this->get('/track-order/'.$o->number.'/print')->assertOk()->assertSee('10 Example Street');
 }
 public function test_grid_status_update_preserves_charges_and_permissions():void {
  [$o,$p,$u]=$this->setupOrder();$o->update(['delivery_cents'=>555,'tax_cents'=>222]);
  $this->get('/admin/orders')->assertOk()->assertSee('Status for '.$o->number)->assertSee('Update');
  $url=route('admin.orders.status',$o);
  $this->patch($url,['revision'=>0,'status'=>'confirmed','q'=>'Customer','page'=>1,'tax'=>'0','payment_status'=>'paid'])->assertRedirect(route('admin.orders.index',['q'=>'Customer','page'=>1]));
  $this->assertSame('confirmed',$o->fresh()->status);$this->assertEquals(555,$o->fresh()->delivery_cents);$this->assertEquals(222,$o->fresh()->tax_cents);$this->assertSame('unpaid',$o->fresh()->payment_status);
  $this->patch($url,['revision'=>0,'status'=>'preparing'])->assertSessionHasErrors('order');
  $this->patch($url,['revision'=>1,'status'=>'cancelled'])->assertRedirect();$this->assertSame('10.000',$p->inventory()->first()->quantity_on_hand);
  $role=Role::create(['name'=>'Grid Viewer','permissions'=>['orders.view']]);$u->update(['role_id'=>$role->id]);$u->unsetRelation('role');$this->actingAs($u)->patch($url,['revision'=>2,'status'=>'placed'])->assertForbidden();
 }
 public function test_status_autosave_returns_new_options_and_revision():void {
  [$o]=$this->setupOrder();
  $this->patchJson(route('admin.orders.status',$o),['revision'=>0,'status'=>'confirmed'])->assertOk()->assertJsonPath('status','confirmed')->assertJsonPath('revision',1)->assertJsonPath('options.preparing','Preparing')->assertJsonPath('terminal',false);
  $this->patchJson(route('admin.orders.status',$o),['revision'=>0,'status'=>'preparing'])->assertUnprocessable();
  $this->patchJson(route('admin.orders.status',$o),['revision'=>1,'status'=>'cancelled'])->assertOk()->assertJsonPath('terminal',true)->assertJsonPath('options.cancelled','Cancelled');
 }
 public function test_tracking_needs_secret_and_management_needs_permission():void {
  [$o,$p,$u]=$this->setupOrder();$this->get('/admin/orders/'.$o->id)->assertOk()->assertSee('Manage Order');
  $role=Role::create(['name'=>'Order Viewer','permissions'=>['orders.view'],'is_super'=>false]);$u->update(['role_id'=>$role->id]);$u->unsetRelation('role');$this->actingAs($u);$this->save($o,['status'=>'confirmed'])->assertForbidden();
  auth()->logout();session()->flush();
  $this->get('/track-order/'.$o->tracking_token)->assertOk()->assertHeader('Referrer-Policy','no-referrer')->assertSee('Management Cake');
  $this->get('/track-order/'.$o->tracking_token.'/print')->assertOk()->assertSee('Order Confirmation');
  $this->get('/track-order/'.$o->number)->assertOk()->assertSee('Placed')->assertDontSee('10 Example Street')->assertDontSee('test@example.test')->assertDontSee('Management Cake');
  $this->get('/track-order/'.$o->number.'/print')->assertNotFound();$this->get('/track-order/'.str_repeat('x',48))->assertNotFound();
  $this->post('/track-order',['number'=>'mmc-999999'])->assertSessionHasErrors('order');
  $this->post('/track-order',['number'=>$o->number])->assertRedirect('/track-order/'.$o->number);
 }
}
