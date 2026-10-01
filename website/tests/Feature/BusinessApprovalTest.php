<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Role,Product};
class BusinessApprovalTest extends TestCase {
 use RefreshDatabase;
 private function business(){
  $user=User::factory()->create(['account_type'=>'business','is_active'=>true,'role_id'=>Role::where('name','Customer')->value('id')]);
  $user->customerRecord()->update(['business_name'=>'Buyer Ltd','business_bin'=>'BIN123','business_phone'=>'12345','business_email'=>'business@example.test']);
  return $user;
 }
 public function test_pending_account_is_retail_priced_and_cannot_checkout_or_access_dashboard():void {
  $user=$this->business();$this->actingAs($user);
  $product=new Product(['total_selling_price_cad'=>15,'business_selling_price_cad'=>10]);
  $this->assertEquals(15,$product->storefront_price);
  foreach(['theme.checkout','customer.orders','customer.profile'] as $route)$this->get(route($route))->assertRedirect(route('customer.business.pending'));
  $this->post(route('checkout.store'),['business_approved_at'=>now()])->assertRedirect(route('customer.business.pending'));
  $this->postJson(route('checkout.store'),[])->assertForbidden();
  $this->get(route('customer.business.pending'))->assertOk()->assertSee('You cannot place an order');
  $this->assertDatabaseCount('orders',0);
 }
 public function test_only_authorized_admin_approves_and_prices_and_access_change():void {
  $user=$this->business();$url=route('admin.customers.approve-business',$user->customerRecord());
  $this->actingAs($user)->post($url)->assertRedirect();$this->assertNull($user->fresh()->business_approved_at);
  $staff=User::factory()->create(['is_active'=>true,'role_id'=>Role::create(['name'=>'Viewer','permissions'=>['orders.view']])->id]);
  $this->actingAs($staff)->post($url)->assertForbidden();
  $admin=User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);
  $this->actingAs($admin)->get(route('admin.customers.index'))->assertOk()->assertSee('Buyer Ltd')->assertSee('BIN123')->assertSee('Approve business');
  $this->post($url)->assertSessionHasNoErrors();$approved=$user->fresh();
  $this->assertNotNull($approved->business_approved_at);$this->assertEquals($admin->id,$approved->business_approved_by);
  $this->actingAs($approved)->get(route('customer.orders'))->assertOk();
  $product=new Product(['total_selling_price_cad'=>15,'business_selling_price_cad'=>10]);$this->assertEquals(10,$product->storefront_price);
  $approved->forceFill(['email_verified_at'=>null])->save();$this->assertEquals(15,$product->storefront_price);
  $this->get(route('customer.orders'))->assertRedirect(route('customer.verify.notice'));
 }
 public function test_incomplete_or_inactive_business_cannot_be_approved():void {
  $user=$this->business();$user->customerRecord()->update(['business_bin'=>null]);
  $admin=User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);
  $this->actingAs($admin)->post(route('admin.customers.approve-business',$user->customerRecord()))->assertSessionHasErrors('business_bin');
  $user->forceFill(['is_active'=>false])->save();
  $this->post(route('admin.customers.approve-business',$user->customerRecord()))->assertSessionHasErrors('business');
  $this->assertNull($user->fresh()->business_approved_at);
 }
}
