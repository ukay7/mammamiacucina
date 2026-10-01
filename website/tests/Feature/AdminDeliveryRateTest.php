<?php
namespace Tests\Feature;
use App\Models\{Role,User};
use App\Services\DeliveryQuote;
use Database\Seeders\DeliveryRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class AdminDeliveryRateTest extends TestCase {
 use RefreshDatabase;
 private function staff(array $permissions=['settings.manage']):User {
  return User::factory()->create(['is_active'=>true,'role_id'=>Role::create(['name'=>'Delivery Staff '.Role::count(),'permissions'=>$permissions])->id]);
 }
 public function test_permissions_apply_to_browsing_and_editing():void {
  $this->get('/admin/delivery-rates')->assertRedirect('/admin/login');
  $this->actingAs($this->staff([]));
  $this->get('/admin/delivery-rates')->assertForbidden();
  $this->put('/admin/delivery-rates/1',[])->assertForbidden();
 }
 public function test_matrix_comparison_directory_and_editor_render():void {
  $this->seed(DeliveryRateSeeder::class);$this->actingAs($this->staff());
  $this->get('/admin/delivery-rates?from=M2N&to=M1P')->assertOk()->assertSee('$58.76')->assertSee('$37.34')->assertSee('$29.83')->assertSee('$18.65')->assertSee('$14.90')->assertSee('VISUAL RATE MATRIX');
 }
 public function test_filters_unavailable_route_and_edit_form():void {
  $this->seed(DeliveryRateSeeder::class);$this->actingAs($this->staff());
  $this->get('/admin/delivery-rates?service=overnight&from=L6R&to=M2N&search=M2N')->assertOk()->assertSee('Unavailable')->assertSee('1 prefixes shown');
  $rate=DB::table('delivery_rates')->where('service_code','bullet')->first();
  $this->get('/admin/delivery-rates?service=bullet&edit='.$rate->id)->assertOk()->assertSee('Save delivery rate');
  $this->get('/admin/delivery-rates?from=K1A&to=M2N')->assertOk()->assertSee('outside the delivery coverage');
  $this->get('/admin/delivery-rates?from=123&to=M2N')->assertOk()->assertSee('valid Canadian postal');
 }
 public function test_rate_edits_are_exact_directional_and_survive_seed():void {
  $this->seed(DeliveryRateSeeder::class);$this->actingAs($this->staff());
  $rate=DB::table('delivery_rates')->where(['service_code'=>'bullet','from_zone'=>8,'to_zone'=>3])->first();
  $url='/admin/delivery-rates/'.$rate->id;
  $this->put($url,['revision'=>0,'available'=>1,'amount'=>'42.19'])->assertRedirect('/admin/delivery-rates?service=bullet');
  $this->assertSame(4219,app(DeliveryQuote::class)->quote('M2N','M1P','bullet')['delivery_cents']);
  $this->assertSame(5876,app(DeliveryQuote::class)->quote('M1P','M2N','bullet')['delivery_cents']);
  $this->put($url,['revision'=>0,'available'=>1,'amount'=>'1'])->assertSessionHasErrors('rate');
  $this->put($url,['revision'=>1,'available'=>1,'amount'=>'-1'])->assertSessionHasErrors('amount');
  $this->put($url,['revision'=>1,'available'=>1,'amount'=>'1.234'])->assertSessionHasErrors('amount');
  $this->put($url,['revision'=>1,'available'=>1])->assertSessionHasErrors('amount');
  $this->seed(DeliveryRateSeeder::class);
  $this->assertDatabaseHas('delivery_rates',['id'=>$rate->id,'amount_cents'=>4219,'revision'=>1]);
  $this->put($url,['revision'=>1,'available'=>0])->assertRedirect();
  $this->assertDatabaseHas('delivery_rates',['id'=>$rate->id,'amount_cents'=>null,'revision'=>2]);
  $this->put($url,['revision'=>2,'available'=>1,'amount'=>'0'])->assertRedirect();
  $this->assertSame(0,app(DeliveryQuote::class)->quote('M2N','M1P','bullet')['delivery_cents']);
 }
 public function test_service_descriptions_are_editable_authorized_and_survive_deployment_seed():void {
  $this->seed(DeliveryRateSeeder::class);
  $url=route('admin.delivery.description','bullet');
  $this->put($url,['description'=>'Forbidden'])->assertRedirect('/admin/login');
  $this->actingAs($this->staff([]))->put($url,['description'=>'Forbidden'])->assertForbidden();
  $this->actingAs($this->staff())->put($url,['description'=>'Delivery will happen in 60 mins'])->assertRedirect();
  $this->get(route('admin.delivery.index'))->assertOk()->assertSee('Save service settings')->assertSee('Delivery will happen in 60 mins');
  $this->seed(DeliveryRateSeeder::class);
  $options=app(DeliveryQuote::class)->options('M2N','M1P','Canada');
  $this->assertSame('Delivery will happen in 60 mins',collect($options['services'])->firstWhere('code','bullet')['description']);
  $this->put($url,['description'=>str_repeat('a',1001)])->assertSessionHasErrors('description');
  $this->put($url,['description'=>''])->assertRedirect();
  $this->assertDatabaseHas('delivery_services',['code'=>'bullet','description'=>'']);
 }
 public function test_inactive_services_are_hidden_rejected_and_settings_survive_seed():void {
  $this->seed(DeliveryRateSeeder::class);$this->actingAs($this->staff());
  $this->put(route('admin.delivery.description','bullet'),['description'=>'Priority delivery','is_active'=>0,'notes'=>'Call courier first'])->assertSessionHasNoErrors();
  $this->seed(DeliveryRateSeeder::class);
  $this->assertDatabaseHas('delivery_services',['code'=>'bullet','is_active'=>0,'notes'=>'Call courier first']);
  $options=app(DeliveryQuote::class)->options('M2N','M1P','Canada');
  $this->assertNotContains('bullet',array_column($options['services'],'code'));
  try{app(DeliveryQuote::class)->quote('M2N','M1P','bullet');$this->fail('Inactive quote accepted');}catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('delivery_service',$e->errors());}
  $this->put(route('admin.delivery.description','bullet'),['description'=>'Priority delivery','is_active'=>1,'notes'=>'Call courier first'])->assertSessionHasNoErrors();
  $this->assertSame(5876,app(DeliveryQuote::class)->quote('M2N','M1P','bullet')['delivery_cents']);
  $this->assertStringNotContainsString('Call courier first',json_encode(app(DeliveryQuote::class)->options('M2N','M1P','Canada')));
 }
}
