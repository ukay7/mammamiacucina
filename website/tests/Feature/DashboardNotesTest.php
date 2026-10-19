<?php
namespace Tests\Feature;
use App\Models\{Order,OrderSettlement,Payment,Role,StaffNote,User,Product};
use App\Services\AdminDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class DashboardNotesTest extends TestCase {
 use RefreshDatabase;
 private function admin(): User {return User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);}
 private function order(array $extra=[]): Order {return Order::create(array_merge(['checkout_token'=>(string)Str::uuid(),'number'=>'TEST-'.Str::random(8),'first_name'=>'Dashboard','last_name'=>'Customer','email'=>'dashboard@example.test','phone'=>'123','address'=>'Address','city'=>'Toronto','province'=>'ON','postal_code'=>'M4S','country'=>'Canada','subtotal_cents'=>10000,'delivery_cents'=>0,'tax_cents'=>0,'currency'=>'CAD','payment_method'=>'cash','payment_status'=>'unpaid','status'=>'warehouse_pending'], $extra));}
 public function test_notes_are_private_crud_escape_html_and_login_destination_is_lightweight(): void {
  $a=$this->admin();$b=$this->admin();$this->actingAs($a);
  $this->assertSame('admin.notes.index',$a->accountRoute());
  $this->get('/admin')->assertRedirect(route('admin.notes.index'));
  $data=['title'=>'Call supplier','body'=>'<script>alert(1)</script>','color'=>'sage','due_date'=>today()->subDay()->toDateString(),'pinned'=>1];
  $this->post(route('admin.notes.store'),$data)->assertSessionHasNoErrors();$note=StaffNote::firstOrFail();
  $this->get(route('admin.notes.index'))->assertOk()->assertSee('Call supplier')->assertSee('&lt;script&gt;',false)->assertDontSee('<script>alert(1)</script>',false)->assertViewHas('counts',fn($c)=>$c['overdue']===1);
  $this->actingAs($b)->get(route('admin.notes.index'))->assertOk()->assertDontSee('Call supplier');
  $this->put(route('admin.notes.update',$note),$data)->assertNotFound();$this->patch(route('admin.notes.complete',$note),['completed'=>1])->assertNotFound();$this->delete(route('admin.notes.destroy',$note))->assertNotFound();
  $this->actingAs($a)->put(route('admin.notes.update',$note),array_merge($data,['title'=>'Call complete']))->assertSessionHasNoErrors();
  $this->patch(route('admin.notes.complete',$note),['completed'=>1])->assertSessionHasNoErrors();
  $this->get(route('admin.notes.index'))->assertDontSee('Call complete');$this->get(route('admin.notes.index',['filter'=>'done']))->assertSee('Call complete');
  $this->patch(route('admin.notes.complete',$note),['completed'=>0]);$this->delete(route('admin.notes.destroy',$note));$this->assertDatabaseCount('staff_notes',0);
 }
 public function test_balances_match_order_ledger_including_refunds_cancellation_and_unknown_totals(): void {
  $a=$this->admin();$this->actingAs($a);
  $unpaid=$this->order();
  $credit=$this->order(['subtotal_cents'=>5000,'manual_collected_cents'=>10000,'payment_status'=>'paid']);
  OrderSettlement::create(['order_id'=>$credit->id,'user_id'=>$a->id,'amount_cents'=>-2000,'method'=>'cash','note'=>'Actual partial refund']);
  $cancelled=$this->order(['status'=>'cancelled','payment_status'=>'paid','manual_collected_cents'=>1000]);
  $unknown=$this->order(['tax_cents'=>null]);
  $gateway=$this->order(['payment_method'=>'card','payment_status'=>'paid','subtotal_cents'=>4000]);
  Payment::create(['order_id'=>$gateway->id,'reference'=>(string)Str::uuid(),'provider'=>'helcim','expires_at'=>now()->addHour(),'mode'=>'live','status'=>'paid','amount_cents'=>10000,'refunded_cents'=>5000,'currency'=>'CAD','paid_at'=>now()]);
  $sandbox=$this->order(['subtotal_cents'=>999999]);Payment::create(['order_id'=>$sandbox->id,'reference'=>(string)Str::uuid(),'provider'=>'helcim','expires_at'=>now()->addHour(),'mode'=>'sandbox','status'=>'pending','amount_cents'=>999999,'currency'=>'CAD']);
  $rows=app(AdminDashboard::class)->balances()->get()->keyBy('id');
  foreach([$unpaid,$credit,$cancelled,$gateway] as $o)$this->assertEquals($o->fresh()->balance_cents,$rows[$o->id]->balance_cents);
  $this->get(route('admin.dashboard.balances',['kind'=>'refunds']))->assertOk()->assertSee($credit->number)->assertDontSee($unpaid->number)->assertViewHas('total',5000);
  $this->get(route('admin.dashboard.balances',['kind'=>'dues']))->assertOk()->assertSee($unpaid->number)->assertDontSee($credit->number)->assertViewHas('total',10000);
  $this->assertNull($rows[$unknown->id]->balance_cents);$this->assertFalse($rows->has($sandbox->id));
  $this->get(route('admin.dashboard'))->assertOk()->assertSee('The bigger picture.')->assertViewHas('sales',fn($s)=>(int)$s['money']->due===10000 && (int)$s['money']->refunds===5000 && (int)$s['money']->unpriced===1);
 }
 public function test_period_metrics_all_time_alerts_low_stock_and_restricted_roles(): void {
  $this->actingAs($this->admin());
  $old=$this->order(['status'=>'warehouse_issue','created_at'=>now()->subDays(100),'ordered_at'=>now()->subDays(100)]);$this->order(['status'=>'parked_admin']);$this->order(['status'=>'completed']);
  $p=Product::create(['category_id'=>1,'slug'=>'dashboard-stock','premium_marketing_name'=>'Low stock cake','is_active'=>true]);$p->inventory()->create(['quantity_on_hand'=>2,'low_stock_threshold'=>5]);
  $response=$this->get(route('admin.dashboard',['days'=>7]))->assertOk()->assertSee($old->number)->assertSee('Low stock cake')->assertViewHas('stock',fn($s)=>$s['low']===1);
  $sales=$response->viewData('sales');$this->assertSame(2,$sales['orders']);$this->assertSame(10000,$sales['value']);$this->assertSame(3,$sales['due_count']);
  $role=Role::create(['name'=>'Notes only','permissions'=>[]]);$limited=User::factory()->create(['role_id'=>$role->id,'is_active'=>true]);$this->actingAs($limited)->get(route('admin.notes.index'))->assertOk();$this->get(route('admin.dashboard'))->assertForbidden();
  $role->update(['permissions'=>['dashboard.view','warehouse.pack','orders.view']]);$limited->unsetRelation('role');$this->actingAs($limited)->get(route('admin.dashboard'))->assertOk()->assertDontSee($old->number)->assertDontSee('Outstanding balances');
  $this->get(route('admin.dashboard.balances'))->assertForbidden();
  $customer=User::factory()->create(['account_type'=>'individual','role_id'=>Role::where('name','Customer')->value('id'),'is_active'=>true]);$this->actingAs($customer)->get(route('admin.notes.index'))->assertRedirect(route('customer.orders'));
 }
}
