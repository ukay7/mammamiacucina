<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Mail};
use App\Models\{Role,User,SmtpSetting};
use App\Services\SmtpConfiguration;
class SmtpSettingsTest extends TestCase {
 use RefreshDatabase;
 private function admin(){return User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);}
 private function data():array{return ['revision'=>0,'enabled'=>1,'host'=>'smtp.gmail.com','port'=>587,'encryption'=>'tls','username'=>'info@mammamiacucina.ca','password'=>'abcdefghijklmnop','from_address'=>'info@mammamiacucina.ca','from_name'=>'Mamma Mia Cucina'];}
 public function test_permissions_and_encrypted_secret_preservation():void {
  $this->get(route('admin.email.edit'))->assertRedirect();
  $role=Role::create(['name'=>'No SMTP','permissions'=>[]]);$this->actingAs(User::factory()->create(['role_id'=>$role->id,'is_active'=>true]));
  $this->get(route('admin.email.edit'))->assertForbidden();$this->put(route('admin.email.update'),$this->data())->assertForbidden();$this->post(route('admin.email.test'),['recipient'=>'a@example.test'])->assertForbidden();
  $this->actingAs($this->admin());$this->get(route('admin.email.edit'))->assertOk()->assertSee('smtp.gmail.com');
  $this->put(route('admin.email.update'),$this->data())->assertSessionHasNoErrors();
  $this->assertNotSame($this->data()['password'],DB::table('smtp_settings')->value('password'));
  $this->assertSame($this->data()['password'],SmtpSetting::first()->password);
  $this->get(route('admin.email.edit'))->assertOk()->assertDontSee($this->data()['password'])->assertSee('A password is saved');
  $this->assertArrayNotHasKey('password',SmtpSetting::first()->toArray());
  $this->put(route('admin.email.update'),array_replace($this->data(),['revision'=>1,'password'=>'','from_name'=>'Updated']))->assertSessionHasNoErrors();
  $this->assertSame($this->data()['password'],SmtpSetting::first()->password);
  $this->put(route('admin.email.update'),$this->data())->assertSessionHasErrors('settings');
 }
 public function test_validation_does_not_flash_password_or_enable_incomplete_settings():void {
  $this->actingAs($this->admin());
  $this->from(route('admin.email.edit'))->put(route('admin.email.update'),array_replace($this->data(),['host'=>'bad://host']))->assertSessionHasErrors('host')->assertSessionMissing('_old_input.password');
  $this->put(route('admin.email.update'),array_replace($this->data(),['password'=>'']))->assertSessionHasErrors('password');
  $this->assertDatabaseCount('smtp_settings',0);
  $this->post(route('admin.email.test'),['recipient'=>'test@example.test'])->assertSessionHasErrors('smtp');
 }
 public function test_runtime_configuration_applies_and_can_disable():void {
  config(['mail.default'=>'array','mail.mailers.smtp.url'=>'smtp://stale-env.invalid']);
  app(SmtpConfiguration::class)->apply();$this->assertSame('array',config('mail.default'));
  $this->actingAs($this->admin())->put(route('admin.email.update'),$this->data())->assertSessionHasNoErrors();
  // Resolving the mail manager loads saved configuration, even when config is cached.
  app('mail.manager');$this->assertSame('configured_smtp',config('mail.default'));
  $this->assertSame('smtp.gmail.com',config('mail.mailers.configured_smtp.host'));
  $this->assertNull(config('mail.mailers.configured_smtp.url'));$this->assertTrue(config('mail.mailers.configured_smtp.require_tls'));
  $this->assertSame('info@mammamiacucina.ca',config('mail.from.address'));
  SmtpSetting::first()->update(['encryption'=>'ssl','port'=>465]);
  event(new \Illuminate\Queue\Events\JobProcessing('sync',\Mockery::mock(\Illuminate\Contracts\Queue\Job::class,['payload'=>[]])));
  $this->assertSame('smtps',config('mail.mailers.configured_smtp.scheme'));
  SmtpSetting::first()->update(['enabled'=>false]);app(SmtpConfiguration::class)->apply();$this->assertSame('log',config('mail.default'));
 }
 public function test_saved_settings_send_test_and_real_registration_via_selected_transport():void {
  $this->actingAs($this->admin())->put(route('admin.email.update'),$this->data())->assertSessionHasNoErrors();
  Mail::extend('smtp',fn()=>new \Illuminate\Mail\Transport\ArrayTransport());
  $this->post(route('admin.email.test'),['recipient'=>'recipient@example.test'])->assertSessionHas('status');
  $messages=Mail::mailer('configured_smtp')->getSymfonyTransport()->messages();$this->assertCount(1,$messages);
  $this->assertSame('info@mammamiacucina.ca',$messages->first()->getOriginalMessage()->getFrom()[0]->getAddress());
  auth()->logout();$this->post(route('customer.register.store'),['name'=>'Email Customer','email'=>'newmail@example.test','phone'=>'12345','account_type'=>'individual','password'=>'test-password-123','password_confirmation'=>'test-password-123'])->assertRedirect(route('theme.index'));
  $this->assertCount(2,Mail::mailer('configured_smtp')->getSymfonyTransport()->messages());
  auth()->logout();$this->post(route('customer.password.send'),['email'=>'newmail@example.test'])->assertSessionHas('status');
  $this->assertCount(3,Mail::mailer('configured_smtp')->getSymfonyTransport()->messages());
  $this->assertDatabaseHas('email_history',['type'=>'smtp_test','recipient'=>'recipient@example.test']);
  $this->assertDatabaseHas('email_history',['type'=>'verification','recipient'=>'newmail@example.test']);
  $this->assertDatabaseHas('email_history',['type'=>'password_reset','recipient'=>'newmail@example.test']);
 }
 public function test_connection_error_never_displays_provider_exception_or_credentials():void {
  $this->actingAs($this->admin())->put(route('admin.email.update'),$this->data())->assertSessionHasNoErrors();
  Mail::shouldReceive('purge')->once();Mail::shouldReceive('mailer')->once()->with('configured_smtp')->andThrow(new \RuntimeException('secret-provider-conversation abcdefghijklmnop'));
  $response=$this->post(route('admin.email.test'),['recipient'=>'recipient@example.test'])->assertSessionHasErrors('smtp');
  $this->assertStringNotContainsString('secret-provider',session('errors')->first('smtp'));
  $this->assertStringNotContainsString('abcdefghijklmnop',session('errors')->first('smtp'));
 }
}
