<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail,Hash};
use App\Models\{User,Role};
class AdminCustomerManagementTest extends TestCase {
 use RefreshDatabase;
 public function test_admin_can_edit_resend_change_password_and_deactivate():void{
  $admin=User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);
  $u=User::factory()->create(['name'=>'Customer','email'=>'manage@example.test','account_type'=>'individual','email_verified_at'=>null,'role_id'=>Role::where('name','Customer')->value('id'),'is_active'=>true]);
  $c=$u->customerRecord();$this->actingAs($admin);
  $this->get(route('admin.customers.edit',$c))->assertOk()->assertSee('Resend verification email');
  $mail='';Mail::shouldReceive('raw')->once()->andReturnUsing(function($text,$callback)use(&$mail){$mail=$text;});
  $this->post(route('admin.customers.resend',$c))->assertSessionHas('status');$this->assertStringContainsString('/account/invitation/',$mail);
  $this->put(route('admin.customers.update',$c),['name'=>'Updated','phone'=>'123','address'=>'Address','email'=>'spoof@example.test','account_type'=>'business'])->assertSessionHas('status');
  $this->assertSame('Updated',$u->fresh()->name);$this->assertSame('Address',$c->fresh()->address);$this->assertSame('manage@example.test',$u->fresh()->email);
  $this->post(route('admin.customers.password',$c),['password'=>'new-password-123','password_confirmation'=>'new-password-123'])->assertSessionHas('status');
  $this->assertTrue(Hash::check('new-password-123',$u->fresh()->password));$this->assertNull($u->fresh()->email_verified_at);
  $this->post(route('admin.customers.active',$c),['is_active'=>0])->assertSessionHas('status');
  $this->assertFalse($u->fresh()->is_active);
  $this->post(route('admin.customers.resend',$c))->assertSessionHasErrors();
  $this->actingAs($u->fresh())->get(route('customer.orders'))->assertForbidden();
  auth()->logout();
  $this->post(route('customer.login.store'),['email'=>$u->email,'password'=>'new-password-123'])->assertSessionHasErrors('email');
  $this->actingAs($admin)->post(route('admin.customers.active',$c),['is_active'=>1])->assertSessionHas('status');
  $this->assertTrue($u->fresh()->is_active);
 }
 public function test_read_only_staff_cannot_modify_accounts():void{
  $staff=User::factory()->create(['role_id'=>Role::create(['name'=>'Reader','permissions'=>['orders.view']])->id,'is_active'=>true]);
  $u=User::factory()->create(['account_type'=>'individual','role_id'=>Role::where('name','Customer')->value('id')]);$c=$u->customerRecord();
  $this->actingAs($staff)->get(route('admin.customers.edit',$c))->assertForbidden();
  $this->put(route('admin.customers.update',$c),['name'=>'Changed'])->assertForbidden();
  foreach(['password','active','resend'] as $action)$this->post(route('admin.customers.'.$action,$c),[])->assertForbidden();
 }
}
