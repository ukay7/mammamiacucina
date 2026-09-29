<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail,Hash};
use App\Models\{User,Role,Product,Order};
class CustomerAccountTest extends TestCase {
 use RefreshDatabase;
 private function customer(array $extra=[]):User{return User::factory()->create(array_merge(['name'=>'Jane Doe','email'=>'jane@example.test','phone'=>'555123','account_type'=>'individual','role_id'=>Role::where('name','Customer')->value('id'),'is_active'=>true],$extra));}
 public function test_checkout_gate():void{
  $this->withSession(['storefront_cart'=>[12=>2]])->get('/checkout')->assertRedirect(route('customer.login'))->assertSessionHas('storefront_cart',[12=>2]);
  $this->post('/checkout')->assertRedirect(route('customer.login'));
  $this->actingAs($this->customer(['email_verified_at'=>null]))->post('/checkout')->assertRedirect(route('customer.verify.notice'));
  $this->assertDatabaseCount('orders',0);
 }
 public function test_registration_and_verification():void{
  $message='';Mail::shouldReceive('raw')->once()->withArgs(function($text,$callback)use(&$message){$message=$text;return true;});
  $this->withSession(['storefront_cart'=>[12=>2]])->post(route('customer.register.store'),['name'=>'New Customer','email'=>'NEW@example.test','phone'=>'12345','account_type'=>'business','password'=>'strong-password-123','password_confirmation'=>'strong-password-123'])->assertRedirect(route('customer.verify.notice'))->assertSessionHas('storefront_cart',[12=>2]);
  $u=User::where('email','new@example.test')->firstOrFail();$this->assertTrue(Hash::check('strong-password-123',$u->password));$this->assertNull($u->email_verified_at);$this->assertFalse($u->hasAdminPermission('orders.manage'));
  preg_match('~https?://[^\s]+~',$message,$m);$this->get($m[0])->assertRedirect('/checkout');$this->assertNotNull($u->fresh()->email_verified_at);
  $this->get($m[0].'x')->assertForbidden();
 }
 public function test_existing_email_login_and_staff_restriction():void{
  $u=$this->customer(['password'=>'strong-password-123']);
  $this->post(route('customer.register.store'),['name'=>'Imposter','email'=>'JANE@example.test','phone'=>'123','account_type'=>'individual','password'=>'strong-password-123','password_confirmation'=>'strong-password-123'])->assertSessionHasErrors('email');$this->assertGuest();
  $this->post(route('customer.login.store'),['email'=>$u->email,'password'=>'bad'])->assertSessionHasErrors('email');
  $this->post(route('customer.login.store'),['email'=>'JANE@example.test','password'=>'strong-password-123'])->assertRedirect('/checkout');$this->assertAuthenticatedAs($u);
  $this->get('/admin/users')->assertRedirect(route('customer.orders'));
 }
 public function test_prefill_order_ownership_and_isolation():void{
  $u=$this->customer();$this->actingAs($u);
  $p=Product::create(['category_id'=>1,'slug'=>'customer-cake','premium_marketing_name'=>'Customer Cake','qr_code'=>'CU1','is_active'=>true,'total_selling_price_cad'=>10]);
  $this->postJson(route('cart.add',$p),['quantity'=>2])->assertOk();
  $this->get('/checkout')->assertOk()->assertSee('jane@example.test')->assertSee('555123');
  $this->post('/checkout',['checkout_token'=>session('checkout_token'),'first_name'=>'Jane','last_name'=>'Doe','email'=>'spoof@example.test','phone'=>'555123','address'=>'10 Test','city'=>'Toronto','province'=>'ON','postal_code'=>'M1M1M1','country'=>'Canada'])->assertRedirect('/order-success');
  $o=Order::firstOrFail();$this->assertEquals($u->customerRecord()->id,$o->customer_id);$this->assertEquals($u->id,$o->created_by);$this->assertSame('jane@example.test',$o->email);
  $this->get(route('customer.orders'))->assertOk()->assertSee($o->number);
  $this->get(route('customer.order',$o))->assertOk()->assertViewIs('customer.order')->assertSee('My Profile')->assertSee('Sign out')->assertSee('My Orders')->assertDontSee('Warehouse packing');
$this->get(route('customer.order.print',$o))->assertOk()->assertViewIs('orders.print')->assertSee('Order Confirmation')->assertSee($o->number)->assertSee('Customer Cake')->assertDontSee('Warehouse packing');
  $this->postJson(route('cart.add',$p),['quantity'=>1])->assertOk();
  $this->get('/checkout')->assertOk()->assertSee('10 Test');
  $this->post('/checkout',['checkout_token'=>session('checkout_token'),'first_name'=>'Jane','last_name'=>'Doe','email'=>$u->email,'phone'=>'555123','address'=>'20 Return Visit','city'=>'Toronto','province'=>'ON','postal_code'=>'M1M1M1','country'=>'Canada'])->assertRedirect('/order-success');
  $this->assertDatabaseCount('customers',1);
  $this->assertSame(2,$u->customerRecord()->orders()->count());
$this->assertSame('20 Return Visit',$u->customerRecord()->address);
$this->assertSame('10 Test',$o->fresh()->address);
$this->postJson(route('cart.add',$p),['quantity'=>1])->assertOk();
$this->get('/checkout')->assertOk()->assertSee('20 Return Visit');
  $admin=User::factory()->create(['role_id'=>Role::create(['name'=>'History viewer','permissions'=>['orders.view']])->id,'is_active'=>true]);
  $this->actingAs($admin)->get(route('admin.customers.show',$u->customerRecord()))->assertOk()->assertSee($o->number)->assertSee(Order::latest('id')->first()->number)->assertSee('2 orders placed')->assertSee('20 Return Visit');
  $this->actingAs($this->customer(['email'=>'other@example.test']))->get(route('customer.order',$o))->assertNotFound();
  $this->get(route('customer.orders'))->assertDontSee($o->number);
$this->get(route('customer.order.print',$o))->assertNotFound();
 }
 public function test_customer_directory_history_and_permissions():void{
  $u=$this->customer();
  $customer=$u->customerRecord();
  $this->assertDatabaseCount('customers',1);
  $u->forceFill(['phone'=>'555999'])->save();
  $this->assertSame($customer->id,$u->customerRecord()->id);
  $this->assertSame('555999',$customer->fresh()->phone);
  $role=Role::create(['name'=>'Order admin','permissions'=>['orders.view']]);
  $admin=User::factory()->create(['role_id'=>$role->id,'is_active'=>true]);
  $this->actingAs($admin)->get(route('admin.customers.index'))->assertOk()->assertSee('Jane Doe');
  $this->get(route('admin.customers.show',$customer))->assertOk()->assertSee('Order History')->assertSee('0 orders placed');
  $this->get(route('admin.customers.index',['search'=>'missing-person']))->assertOk()->assertDontSee('Jane Doe');
  $worker=User::factory()->create(['role_id'=>Role::create(['name'=>'Packing only','permissions'=>['warehouse.pack']])->id,'is_active'=>true]);
  $this->actingAs($worker)->get(route('admin.customers.index'))->assertForbidden();
  $this->get(route('admin.customers.show',$customer))->assertForbidden();
  $this->actingAs($u)->get(route('admin.customers.index'))->assertRedirect(route('customer.orders'));
 }
 public function test_profile_changes_sync_but_cannot_change_email_or_permissions():void{
  $u=$this->customer();$other=$this->customer(['email'=>'someone@example.test']);$verified=$u->email_verified_at->toDateTimeString();$role=$u->role_id;
  $this->actingAs($u)->get(route('customer.profile'))->assertOk()->assertSee('readonly',false)->assertSee($u->email);
  $this->put(route('customer.profile.update'),['name'=>'Updated Name','phone'=>'555777','address'=>'42 Saved Street','city'=>'Toronto','province'=>'ON','postal_code'=>'M1M 1M1','country'=>'Canada','account_type'=>'business','email'=>'hijack@example.test','role_id'=>999,'email_verified_at'=>null,'user_id'=>$other->id])->assertRedirect(route('customer.profile'));
  $u->refresh();$this->assertSame('Updated Name',$u->name);$this->assertSame('555777',$u->phone);$this->assertSame('individual',$u->account_type);$this->assertSame('individual',$u->customerRecord()->account_type);
  $this->assertSame('jane@example.test',$u->email);$this->assertEquals($role,$u->role_id);$this->assertSame($verified,$u->email_verified_at->toDateTimeString());
  $this->assertSame('Updated Name',$u->customerRecord()->name);$this->assertSame('jane@example.test',$u->customerRecord()->email);
  $this->assertSame('Jane Doe',$other->fresh()->name);
$this->assertSame('42 Saved Street',$u->customerRecord()->address);
$this->assertSame('Toronto',$u->customerRecord()->city);
$this->get(route('customer.profile'))->assertOk()->assertSee('42 Saved Street')->assertSee('M1M 1M1');
  $this->put(route('customer.profile.update'),['name'=>'','phone'=>'','account_type'=>'admin'])->assertSessionHasErrors(['name','phone']);
  auth()->logout();$this->get(route('customer.profile'))->assertRedirect(route('customer.login'));
  $this->put(route('customer.profile.update'),[])->assertRedirect(route('customer.login'));
 }
}
