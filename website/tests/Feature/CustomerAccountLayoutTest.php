<?php
namespace Tests\Feature;
use App\Models\{User,Role,CustomerNote};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CustomerAccountLayoutTest extends TestCase {
 use RefreshDatabase;
 private function admin(){return User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);}
 private function customer(){return User::factory()->create(['account_type'=>'individual','role_id'=>Role::where('name','Customer')->value('id'),'is_active'=>true])->customerRecord();}
 public function test_profile_tabs_and_notes_are_scoped_and_escaped(){
  $c=$this->customer();$other=$this->customer();$admin=$this->admin();$this->actingAs($admin);
  $this->get(route('admin.customers.show',$c))->assertOk()->assertSee('customer-tab-details')->assertDontSee('customer-tab-profile')->assertSee('Save Customer')->assertDontSee('Not configured')->assertSee('Open receivables')->assertSee('customer-note-modal')->assertSee('customer-tab-settings')->assertSee('customer-settings')->assertDontSee('customer-settings-modal')->assertDontSee('customer-tab-reports')->assertDontSee('customer-tab-history')->assertSee('customer-orders')->assertSee('customer-invoices');
  $this->post(route('admin.customers.notes.store',$c),['body'=>'<script>alert(1)</script> Follow up','customer_id'=>$other->id,'user_id'=>$other->user_id])->assertRedirect(route('admin.customers.show',$c));
  $this->assertDatabaseHas('customer_notes',['customer_id'=>$c->id,'user_id'=>$admin->id,'body'=>'<script>alert(1)</script> Follow up']);
  $this->get(route('admin.customers.show',$c))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
  $this->get(route('admin.customers.show',$other))->assertOk()->assertDontSee('Follow up');
  $this->post(route('admin.customers.notes.store',$c),['body'=>''])->assertSessionHasErrors('body');
  $this->post(route('admin.customers.notes.store',$c),['body'=>str_repeat('a',5001)])->assertSessionHasErrors('body');
  $this->assertSame(1,CustomerNote::count());
 }
 public function test_read_only_customer_view_cannot_add_notes(){
  $c=$this->customer();$staff=User::factory()->create(['is_active'=>true,'role_id'=>Role::create(['name'=>'Account viewer','permissions'=>['orders.view']])->id]);
  $this->actingAs($staff)->get(route('admin.customers.show',$c))->assertOk()->assertDontSee('id="customer-note-modal"',false)->assertDontSee('customer-tab-settings');
  $this->post(route('admin.customers.notes.store',$c),['body'=>'Unauthorized'])->assertForbidden();
  $this->actingAs($c->user)->post(route('admin.customers.notes.store',$c),['body'=>'Unauthorized'])->assertRedirect();
  $this->assertSame(0,CustomerNote::count());
 }
 public function test_directory_icons_keep_accessible_names_and_cooldown(){
  $c=$this->customer();$c->user->forceFill(['email_verified_at'=>null])->save();$this->actingAs($this->admin());
  $this->get(route('admin.customers.index'))->assertOk()->assertSee('aria-label="View Detail"',false)->assertSee('aria-label="Edit Customer"',false)->assertSee('data-verification-icon="1"',false)->assertSee('data-verification-cooldown=',false);
 }
 public function test_order_history_includes_old_archived_orders_and_is_customer_scoped(){
  $c=$this->customer();$other=$this->customer();$this->actingAs($this->admin());
  for($i=0;$i<21;$i++)\App\Models\Order::create(['customer_id'=>$c->id,'checkout_token'=>(string)\Illuminate\Support\Str::uuid(),'number'=>'HISTORY-'.$i,'first_name'=>'Buyer','last_name'=>'Test','email'=>$c->email,'phone'=>'123','address'=>'Street','city'=>'Toronto','province'=>'ON','postal_code'=>'M5V','country'=>'Canada','subtotal_cents'=>1000,'delivery_cents'=>0,'tax_cents'=>0,'currency'=>'CAD','payment_method'=>'cash','payment_status'=>'paid','status'=>'completed','created_at'=>now()->subYears(2),'archived_at'=>$i===0?now():null]);
  $this->get(route('admin.customers.show',$c))->assertOk()->assertViewHas('orders',fn($p)=>$p->total()===21 && $p->count()===20);
  $this->get(route('admin.customers.show',[$c,'page'=>2]))->assertOk()->assertSee('HISTORY-0')->assertSee('Archived')->assertViewHas('orders',fn($p)=>$p->count()===1);
  $this->get(route('admin.customers.show',$other))->assertOk()->assertDontSee('HISTORY-0')->assertViewHas('orders',fn($p)=>$p->total()===0);
 }
}
