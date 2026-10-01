<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
class IndividualAutoVerificationTest extends TestCase {
 use RefreshDatabase;
 public function test_new_individual_is_verified_without_sending_activation_email():void {
  Mail::shouldReceive('raw')->never();config(['customer_accounts.auto_verify_individuals'=>true]);
  $this->post(route('customer.register.store'),['name'=>'Buyer','email'=>'buyer@example.test','phone'=>'123','account_type'=>'individual','password'=>'strong-password-123','password_confirmation'=>'strong-password-123'])->assertRedirect(route('theme.index'));
  $this->assertNotNull(User::where('email','buyer@example.test')->firstOrFail()->email_verified_at);
  $this->get(route('customer.orders'))->assertOk();
 }
 public function test_business_still_requires_verification_and_approval():void {
  Mail::fake();config(['customer_accounts.auto_verify_individuals'=>true]);
  $this->post(route('customer.register.store'),['name'=>'Buyer','email'=>'biz@example.test','phone'=>'123','account_type'=>'business','business_name'=>'Company','business_bin'=>'BIN','business_phone'=>'123','business_email'=>'biz@example.test','password'=>'strong-password-123','password_confirmation'=>'strong-password-123'])->assertRedirect();
  $user=User::where('email','biz@example.test')->firstOrFail();$this->assertNull($user->email_verified_at);$this->assertNull($user->business_approved_at);
 }
}
