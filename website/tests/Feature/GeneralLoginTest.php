<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail,Password,Hash};
use App\Models\{User,Role};
class GeneralLoginTest extends TestCase {
 use RefreshDatabase;
 public function test_general_login_routes_staff_by_permissions():void {
  foreach(['Super Admin'=>'admin.dashboard','Warehouse User'=>'admin.orders.index'] as $role=>$route){
   $user=User::factory()->create(['role_id'=>Role::where('name',$role)->value('id'),'is_active'=>true,'password'=>'strong-password-123']);
   $this->post(route('customer.login.store'),['email'=>$user->email,'password'=>'strong-password-123'])->assertRedirect(route($route));
   $this->assertAuthenticatedAs($user);auth()->logout();
  }
  $this->get('/app')->assertRedirect(route('customer.login'));
 }
 public function test_staff_reset_uses_email_link_and_preserves_role():void {
  $user=User::factory()->create(['role_id'=>Role::where('name','Warehouse User')->value('id'),'is_active'=>true]);
  $message='';Mail::shouldReceive('raw')->once()->andReturnUsing(function($body,$callback)use(&$message){$message=$body;});
  $this->post(route('customer.password.send'),['email'=>$user->email])->assertSessionHas('status');
  preg_match('~/account/reset-password/([^?\s]+)~',$message,$match);
  $this->assertNotEmpty($match[1]);
  $data=['email'=>$user->email,'token'=>$match[1],'password'=>'new-strong-password-123','password_confirmation'=>'new-strong-password-123'];
  $this->post(route('customer.password.update'),$data)->assertRedirect(route('customer.login'));
  $this->assertTrue(Hash::check($data['password'],$user->fresh()->password));
  $this->assertEquals($user->role_id,$user->fresh()->role_id);
  $this->post(route('customer.password.update'),$data)->assertSessionHasErrors('email');
 }
 public function test_disabled_staff_cannot_login_or_reset():void {
  Mail::shouldReceive('raw')->never();
  $user=User::factory()->create(['role_id'=>Role::where('name','Staff')->value('id'),'is_active'=>false,'password'=>'strong-password-123']);
  $this->post(route('customer.login.store'),['email'=>$user->email,'password'=>'strong-password-123'])->assertSessionHasErrors('email');
  $this->post(route('customer.password.send'),['email'=>$user->email])->assertSessionHas('status');
  $this->assertGuest();
 }
}
