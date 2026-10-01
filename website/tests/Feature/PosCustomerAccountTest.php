<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Role,Product,Order};
use Illuminate\Support\Facades\{Mail,Password,Hash};
class PosCustomerAccountTest extends TestCase {
 use RefreshDatabase;
 public function test_new_pos_account_verifies_resets_and_sees_order():void {
  $messages=[];Mail::shouldReceive('raw')->andReturnUsing(function($text,$callback)use(&$messages){$messages[]=$text;});
  $admin=User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);$this->actingAs($admin);
  $p=Product::create(['category_id'=>1,'slug'=>'pos-account','premium_marketing_name'=>'Cake','qr_code'=>'PC1','is_active'=>true,'total_selling_price_cad'=>10]);
  $quote=$this->postJson(route('admin.pos.quote'),['items'=>[['id'=>$p->id,'quantity'=>1]],'fulfillment'=>'pickup'])->assertOk()->json('quote');
  $data=['quote'=>$quote,'first_name'=>'New','last_name'=>'Customer','email'=>'new@example.test','payment_method'=>'cash','payment_status'=>'paid'];
  $this->postJson(route('admin.pos.store'),array_diff_key($data,['email'=>1]))->assertUnprocessable();
  $this->postJson(route('admin.pos.store'),$data)->assertOk();
  $u=User::where('email','new@example.test')->firstOrFail();$o=Order::firstOrFail();
  $this->assertEquals($u->customerRecord()->id,$o->customer_id);$this->assertEquals($admin->id,$o->created_by);$this->assertNull($u->email_verified_at);
  $this->postJson(route('admin.pos.store'),$data)->assertOk();$this->assertDatabaseCount('orders',1);
  // afterCommit mail runs on a real commit; RefreshDatabase wraps this test in a transaction.
  $url=\Illuminate\Support\Facades\URL::temporarySignedRoute('customer.invite',now()->addHour(),['user'=>$u->id,'hash'=>sha1($u->email)]);
  auth()->logout();$this->get($url)->assertRedirect(route('customer.password.forgot'));$this->assertNotNull($u->fresh()->email_verified_at);
  $this->post(route('customer.password.send'),['email'=>$u->email])->assertSessionHas('status');
  $this->assertNotEmpty($messages);
  $token=Password::createToken($u);
  $payload=['email'=>$u->email,'token'=>$token,'password'=>'new-password-123','password_confirmation'=>'new-password-123'];
  $this->post(route('customer.password.update'),$payload)->assertRedirect(route('customer.login'));
  $this->assertTrue(Hash::check('new-password-123',$u->fresh()->password));
  $this->post(route('customer.password.update'),$payload)->assertSessionHasErrors();
  $this->actingAs($u->fresh())->get(route('customer.orders'))->assertOk()->assertSee($o->number);
  $this->get(route('customer.order',$o))->assertOk();
  $this->actingAs($admin);
  $quote=$this->postJson(route('admin.pos.quote'),['items'=>[['id'=>$p->id,'quantity'=>1]],'fulfillment'=>'pickup'])->json('quote');
  $this->postJson(route('admin.pos.store'),['quote'=>$quote,'customer_id'=>$u->customerRecord()->id,'email'=>'spoof@example.test','payment_method'=>'cash','payment_status'=>'paid'])->assertOk();
  $this->assertSame(2,$u->customerRecord()->orders()->count());
  $this->assertSame($u->email,Order::latest('id')->first()->email);
 }
 public function test_reset_does_not_disclose_or_reset_staff():void {
  Mail::shouldReceive('raw')->never();
  $u=User::factory()->create();
  $this->post(route('customer.password.send'),['email'=>$u->email])->assertSessionHas('status');
  $this->post(route('customer.password.send'),['email'=>'missing@example.test'])->assertSessionHas('status');
  $this->post(route('customer.password.update'),['email'=>$u->email,'token'=>Password::createToken($u),'password'=>'new-password-123','password_confirmation'=>'new-password-123'])->assertSessionHasErrors();
 }
 public function test_pos_business_customer_requires_and_persists_business_details():void {
  Mail::fake();$admin=User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);$this->actingAs($admin);
  $p=Product::create(['category_id'=>1,'slug'=>'business-pos','premium_marketing_name'=>'Business Cake','qr_code'=>'BIZ-POS','is_active'=>true,'total_selling_price_cad'=>10]);
  $quote=$this->postJson(route('admin.pos.quote'),['items'=>[['id'=>$p->id,'quantity'=>1]],'fulfillment'=>'pickup'])->assertOk()->json('quote');
  $data=['quote'=>$quote,'first_name'=>'Business','email'=>'pos-business@example.test','account_type'=>'business','payment_method'=>'cash','payment_status'=>'paid'];
  $this->postJson(route('admin.pos.store'),$data)->assertUnprocessable()->assertJsonValidationErrors(['business_bin','business_name','business_phone','business_email']);
  $this->assertDatabaseCount('orders',0);
  $fields=['business_bin'=>'BIN-POS','business_name'=>'POS Company','business_phone'=>'555123','business_email'=>'office@example.test'];
  $this->postJson(route('admin.pos.store'),$data+$fields)->assertOk();
  $u=User::where('email',$data['email'])->firstOrFail();$this->assertDatabaseHas('customers',$fields+['user_id'=>$u->id]);
  $this->assertSame($u->customerRecord()->id,Order::firstOrFail()->customer_id);
 }
}
