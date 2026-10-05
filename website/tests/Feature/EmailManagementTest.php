<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail,DB,URL};
use App\Models\{User,Role,SmtpSetting,EmailTemplate,EmailHistory};
use App\Services\OutgoingEmail;
class EmailManagementTest extends TestCase {
 use RefreshDatabase;
 private function admin(){return User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);}
 private function settings():SmtpSetting{return SmtpSetting::forceCreate(['id'=>1,'enabled'=>true,'host'=>'smtp.gmail.com','port'=>587,'encryption'=>'tls','username'=>'test@example.test','password'=>'test-secret','from_address'=>'test@example.test','from_name'=>'Test','revision'=>1]);}
 public function test_readiness_requires_successful_test_for_current_revision():void {
  $this->actingAs($this->admin());$s=$this->settings();$this->assertTrue(SmtpSetting::autoVerifyIndividual());
  $this->mock(OutgoingEmail::class)->shouldReceive('template')->once()->andReturn(new EmailHistory(['status'=>'accepted']));
  $this->post(route('admin.email.test'),['recipient'=>'test@example.test'])->assertSessionHasNoErrors();$this->assertTrue($s->fresh()->ready());$this->assertFalse(SmtpSetting::autoVerifyIndividual());
  $data=$s->fresh()->only(['enabled','host','port','encryption','username','from_address','from_name','revision']);$data['password']='';
  $this->put(route('admin.email.update'),$data)->assertSessionHasNoErrors();$this->assertFalse($s->fresh()->ready());
  $data['revision']=2;$data['encryption']='ssl';$this->put(route('admin.email.update'),$data)->assertSessionHasErrors('port');
 }
 public function test_registration_requires_link_when_smtp_ready_and_send_failure_does_not_verify():void {
  $s=$this->settings();$s->update(['tested_at'=>now(),'tested_revision'=>1]);
  Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('failure'));
  $this->post(route('customer.register.store'),['name'=>'Email Buyer','email'=>'buyer@example.test','phone'=>'123','account_type'=>'individual','password'=>'long-password-123','password_confirmation'=>'long-password-123'])->assertSessionHasErrors('email');
  $user=User::where('email','buyer@example.test')->firstOrFail();$this->assertNull($user->email_verified_at);$this->assertTrue($s->fresh()->ready());
  $this->actingAs($user)->get(URL::temporarySignedRoute('customer.verify',now()->addMinutes(60),['id'=>$user->id,'hash'=>sha1($user->email)]))->assertRedirect();$this->assertNotNull($user->fresh()->email_verified_at);
 }
 public function test_manual_verification_is_audited_and_does_not_approve_business():void {
  $customer=User::factory()->create(['account_type'=>'business','is_active'=>true,'email_verified_at'=>null,'business_approved_at'=>null]);$profile=$customer->customerRecord();$url=route('admin.customers.verify-email',$profile);
  $this->actingAs($customer)->post($url,['reason'=>'Confirmed by phone'])->assertRedirect();$this->assertNull($customer->fresh()->email_verified_at);
  $staff=User::factory()->create(['is_active'=>true,'role_id'=>Role::create(['name'=>'No customer permission','permissions'=>[]])->id]);$this->actingAs($staff)->post($url,['reason'=>'Confirmed by phone'])->assertForbidden();
  $admin=$this->admin();$this->actingAs($admin)->get(route('admin.customers.edit',$profile))->assertOk()->assertSee('Verify email manually');
  $this->post($url,[])->assertSessionHasErrors('reason');$this->post($url,['reason'=>'Confirmed identity by phone'])->assertSessionHasNoErrors();$this->assertNotNull($customer->fresh()->email_verified_at);$this->assertNull($customer->fresh()->business_approved_at);$this->assertDatabaseHas('customer_email_verifications',['user_id'=>$customer->id,'verified_by'=>$admin->id,'reason'=>'Confirmed identity by phone']);
  $this->post($url,['reason'=>'Confirmed identity again'])->assertSessionHasNoErrors();$this->assertDatabaseCount('customer_email_verifications',1);
 }
 public function test_template_edit_validation_rendering_and_permissions():void {
  $this->get(route('admin.email.templates.index'))->assertRedirect();$this->actingAs($this->admin());$this->get(route('admin.email.templates.index'))->assertOk()->assertSee('Website registration');
  $url=route('admin.email.templates.update','verification');$this->get(route('admin.email.templates.edit','verification'))->assertOk();
  $d=['revision'=>0,'subject'=>'Hello {{name}}','body'=>'Welcome {{name}}. Click {{verification_url}}'];$this->put($url,$d)->assertSessionHasNoErrors();$this->put($url,$d)->assertSessionHasErrors('revision');
  $this->put($url,['revision'=>1,'subject'=>'Test','body'=>'Missing link'])->assertSessionHasErrors('body');$this->put($url,['revision'=>1,'subject'=>'{{password}}','body'=>'{{verification_url}}'])->assertSessionHasErrors('body');
  $message=EmailTemplate::renderMessage('verification',['name'=>'Buyer','verification_url'=>'https://example.test/verify']);$this->assertSame('Hello Buyer',$message['subject']);$this->assertStringContainsString('https://example.test/verify',$message['body']);
  config(['mail.default'=>'array']);app(OutgoingEmail::class)->template('verification','buyer@example.test',['name'=>'Buyer','verification_url'=>'https://example.test/verify']);$this->assertDatabaseHas('email_history',['subject'=>'Hello Buyer','recipient'=>'buyer@example.test']);
 }
 public function test_unaccepted_test_does_not_enable_verification():void {
  $this->actingAs($this->admin());$s=$this->settings();$this->mock(OutgoingEmail::class)->shouldReceive('template')->once()->andReturn(new EmailHistory(['status'=>'logged_only']));$this->post(route('admin.email.test'),['recipient'=>'test@example.test'])->assertSessionHasErrors('smtp');$this->assertFalse($s->fresh()->ready());
 }
}

