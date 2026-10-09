<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Mail};
use App\Models\{User,Role,ThemeSetting,SmtpSetting};
use App\Services\OutgoingEmail;
class ThemeAndApiEmailTest extends TestCase {
 use RefreshDatabase;
 private function admin(){return User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);}
 private function apiData(){return ['delivery_method'=>'resend','api_key'=>'re_test_secret','enabled'=>1,'revision'=>0,'from_address'=>'info@example.test','from_name'=>'Mamma Mia'];}
 public function test_theme_permissions_save_validation_and_reset():void {
  $this->get(route('admin.theme.edit'))->assertRedirect();
  $this->actingAs(User::factory()->create(['is_active'=>true,'role_id'=>Role::create(['name'=>'Limited','permissions'=>[]])->id]));
  $this->get(route('admin.theme.edit'))->assertForbidden();
  $this->actingAs($this->admin());
  $this->get(route('admin.theme.edit'))->assertOk()->assertSee('Reset to Original');
  $c=config('theme_colors.colors');$c['header']='#123456';
  $d=['revision'=>0,'action'=>'save','website'=>$c,'admin'=>$c];
  $this->put(route('admin.theme.update'),$d)->assertSessionHasNoErrors();
  $this->get('/about')->assertOk()->assertSee('--theme-header: #123456',false);
  $this->put(route('admin.theme.update'),$d)->assertSessionHasErrors('theme');
  $d['revision']=1;$d['website']['header']='red;display:none';
  $this->put(route('admin.theme.update'),$d)->assertSessionHasErrors('website.header');
  $this->put(route('admin.theme.update'),['revision'=>1,'action'=>'reset'])->assertSessionHasNoErrors();
  $this->assertNull(ThemeSetting::find(1)->website);
  $this->get('/about')->assertDontSee('id="mmc-custom-theme"',false);
 }
 public function test_api_settings_encrypted_hidden_and_successful_test_marks_ready():void {
  Http::preventStrayRequests();Http::fake(['api.resend.com/emails'=>Http::response(['id'=>'mail-123'],200)]);
  $this->actingAs($this->admin());
  $this->put(route('admin.email.update'),$this->apiData())->assertSessionHasNoErrors();
  $this->assertNotSame('re_test_secret',DB::table('smtp_settings')->value('api_key'));
  $this->assertArrayNotHasKey('api_key',SmtpSetting::find(1)->toArray());
  $this->get(route('admin.email.edit'))->assertOk()->assertDontSee('re_test_secret');
  $this->post(route('admin.email.test'),['recipient'=>'receiver@example.test'])->assertSessionHasNoErrors();
  $this->assertTrue(SmtpSetting::find(1)->ready());
  Http::assertSent(fn($r)=>$r->url()==='https://api.resend.com/emails' && $r->hasHeader('Authorization','Bearer re_test_secret') && $r['to']===['receiver@example.test'] && $r->hasHeader('Idempotency-Key'));
  $this->assertDatabaseHas('email_history',['mailer'=>'resend_api','status'=>'accepted','message_id'=>'mail-123']);
  $this->put(route('admin.email.update'),array_replace($this->apiData(),['revision'=>1,'api_key'=>'']))->assertSessionHasNoErrors();
  $this->assertSame('re_test_secret',SmtpSetting::find(1)->api_key);$this->assertFalse(SmtpSetting::find(1)->ready());
 }
 public function test_api_errors_never_leak_credentials_or_mark_ready():void {
  Http::preventStrayRequests();Http::fake(['api.resend.com/emails'=>Http::response(['message'=>'secret provider response re_test_secret'],403)]);
  $this->actingAs($this->admin());$this->put(route('admin.email.update'),$this->apiData())->assertSessionHasNoErrors();
  $this->post(route('admin.email.test'),['recipient'=>'receiver@example.test'])->assertSessionHasErrors('smtp');
  $this->assertFalse(SmtpSetting::find(1)->ready());
  $this->assertDatabaseHas('email_history',['status'=>'failed','mailer'=>'resend_api']);
  $this->put(route('admin.email.update'),array_replace($this->apiData(),['from_address'=>'bad']))->assertSessionMissing('_old_input.api_key');
 }
 public function test_api_registration_and_reset_use_existing_templates():void {
  Http::preventStrayRequests();Http::fake(['api.resend.com/emails'=>Http::response(['id'=>'mail-456'],200)]);
  SmtpSetting::forceCreate($this->apiData()+['id'=>1,'tested_at'=>now(),'tested_revision'=>0]);
  $this->post(route('customer.register.store'),['name'=>'API Buyer','email'=>'buyer@example.test','phone'=>'123','account_type'=>'individual','password'=>'long-password-123','password_confirmation'=>'long-password-123'])->assertSessionHasNoErrors();
  $u=User::where('email','buyer@example.test')->firstOrFail();$this->assertNull($u->email_verified_at);
  $this->assertDatabaseHas('email_history',['type'=>'verification','mailer'=>'resend_api','status'=>'accepted']);
  app(OutgoingEmail::class)->template('password_reset',$u->email,['reset_url'=>'https://example.test/reset','expires_minutes'=>60],$u);
  Http::assertSentCount(2);
 }

 public function test_product_detail_shows_code_without_sku():void {
  $p=\App\Models\Product::create(['category_id'=>1,'slug'=>'code-cake','premium_marketing_name'=>'Code Cake','product_code'=>'MMC-ABC123','is_active'=>true,'total_selling_price_cad'=>15]);
  $p->categories()->sync([1]);
  $this->get('/products/code-cake')->assertOk()->assertSee('Product code:')->assertSee('MMC-ABC123')->assertDontSee('SKU:');
 }
}
