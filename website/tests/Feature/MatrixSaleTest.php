<?php
namespace Tests\Feature;
use App\Models\{GeneralSetting,Order,Product,Role,User};
use App\Services\{DeliveryQuote,OrderAmendment};
use Database\Seeders\DeliveryRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class MatrixSaleTest extends TestCase {
 use RefreshDatabase;
 private function setupSale():array {
  $this->seed(DeliveryRateSeeder::class);
  GeneralSetting::findOrFail(1)->update(['warehouse_postal_code'=>'M2N','matrix_delivery_enabled'=>true,'delivery_cents'=>1,'tax_basis_points'=>500]);
  $u=User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);$this->actingAs($u);
  $p=Product::create(['category_id'=>1,'slug'=>'matrix-sale','premium_marketing_name'=>'Matrix Cake','qr_code'=>'MATRIX','is_active'=>true,'total_selling_price_cad'=>10]);
  $profile=User::factory()->create(['account_type'=>'individual','is_active'=>true,'phone'=>'555123'])->customerRecord();
  $quote=['customer_id'=>$profile->id,'items'=>[['id'=>$p->id,'quantity'=>1]],'fulfillment'=>'delivery','postal_code'=>'m1p 1a1','country'=>'Canada','delivery_service'=>'bullet'];
  $customer=['customer_id'=>$profile->id,'first_name'=>'Customer','last_name'=>'Test','email'=>'matrix@example.test','phone'=>'555123','address'=>'10 Test Street','city'=>'Toronto','province'=>'ON','postal_code'=>'m1p 1a1','country'=>'Canada','delivery_service'=>'bullet','payment_method'=>'cash','payment_status'=>'unpaid'];
  return [$u,$p,$quote,$customer];
 }
 public function test_pos_requires_postal_and_service_and_persists_exact_quote_on_every_receipt():void {
  [$u,$p,$q,$customer]=$this->setupSale();
  $this->postJson(route('admin.pos.quote'),array_diff_key($q,['postal_code'=>true]))->assertUnprocessable();
  $this->postJson(route('admin.pos.quote'),array_diff_key($q,['delivery_service'=>true]))->assertUnprocessable();
  $this->postJson(route('admin.pos.delivery-options'),['postal_code'=>'M1P','country'=>'Canada'])->assertOk()->assertJsonCount(5,'services')->assertJsonPath('services.0.amount_cents',5876);
  $quote=$this->postJson(route('admin.pos.quote'),$q)->assertOk()->assertJsonPath('delivery',5876)->assertJsonPath('total',6926)->json();
  $this->postJson(route('admin.pos.store'),$customer+['quote'=>$quote['quote']])->assertOk()->assertJsonPath('total',6926);
  $order=Order::firstOrFail();
  $this->assertDatabaseHas('orders',['id'=>$order->id,'delivery_service_name'=>'Bullet','delivery_from_postal'=>'M2N','delivery_to_postal'=>'M1P 1A1','delivery_from_zone'=>8,'delivery_to_zone'=>3,'delivery_rate_cents'=>5876]);
  foreach([route('admin.pos.receipt',$order),route('admin.orders.print',$order),route('admin.orders.show',$order)] as $url)$this->get($url)->assertOk()->assertSee('Bullet')->assertSee('M2N')->assertSee('M1P 1A1')->assertSee('Zone 8')->assertSee('58.76');
  GeneralSetting::findOrFail(1)->update(['warehouse_postal_code'=>'M1P']);DB::table('delivery_rates')->update(['amount_cents'=>100]);
  $this->get(route('admin.pos.receipt',$order))->assertOk()->assertSee('M2N')->assertSee('58.76');
  $this->postJson(route('admin.pos.store'),$customer+['quote'=>$quote['quote']])->assertOk();$this->assertDatabaseCount('orders',1);
 }
 public function test_pos_rejects_changed_postal_service_price_origin_and_unavailable_route():void {
  [$u,$p,$q,$customer]=$this->setupSale();$quote=$this->postJson(route('admin.pos.quote'),$q)->assertOk()->json();
  foreach([['postal_code'=>'M2N'],['delivery_service'=>'direct'],['country'=>'USA']] as $change)$this->postJson(route('admin.pos.store'),array_replace($customer+['quote'=>$quote['quote']],$change))->assertUnprocessable();
  DB::table('delivery_rates')->where(['service_code'=>'bullet','from_zone'=>8,'to_zone'=>3])->update(['amount_cents'=>6000]);
  $this->postJson(route('admin.pos.store'),$customer+['quote'=>$quote['quote']])->assertUnprocessable();
  GeneralSetting::findOrFail(1)->update(['warehouse_postal_code'=>'L6R']);
  $this->postJson(route('admin.pos.quote'),array_replace($q,['postal_code'=>'M2N','delivery_service'=>'overnight']))->assertUnprocessable();
  $this->postJson(route('admin.pos.store'),$customer+['quote'=>$quote['quote']])->assertUnprocessable();$this->assertDatabaseCount('orders',0);
 }
 public function test_matrix_mode_pickup_remains_free():void {
  [$u,$p,$q,$customer]=$this->setupSale();$q=['customer_id'=>$q['customer_id'],'items'=>$q['items'],'fulfillment'=>'pickup'];
  $quote=$this->postJson(route('admin.pos.quote'),$q)->assertOk()->assertJsonPath('delivery',0)->assertJsonPath('total',1050)->json();
  $this->postJson(route('admin.pos.store'),array_replace($customer,['quote'=>$quote['quote'],'payment_status'=>'paid']))->assertOk();
  $o=Order::firstOrFail();$this->assertNull($o->delivery_service);$this->assertNull($o->delivery_to_postal);
  $this->get(route('admin.pos.receipt',$o))->assertOk()->assertSee('Pick up')->assertDontSee('Zone 8');
 }
 public function test_admin_amendment_reprices_route_and_clears_pickup_snapshot():void {
  [$u,$p,$q,$customer]=$this->setupSale();$route=app(DeliveryQuote::class)->quote('M2N','M1P 1A1','bullet');
  $o=Order::create(array_merge($customer,$route,['checkout_token'=>(string)\Illuminate\Support\Str::uuid(),'number'=>'matrix-order','source'=>'website','status'=>'placed','fulfillment'=>'delivery','subtotal_cents'=>1000,'tax_cents'=>50,'tax_basis_points'=>500]));
  $item=$o->items()->create(['product_id'=>$p->id,'name'=>'Cake','quantity'=>1,'unit_cents'=>1000,'line_cents'=>1000]);
  $d=$customer+['revision'=>0,'fulfillment'=>'delivery','delivery'=>'37.34','tax'=>'0.50','tax_mode'=>'recalculate','reason'=>'Change courier','items'=>[$item->id=>['quantity'=>1,'unit_price'=>'10.00']]];$d['delivery_service']='direct';
  $this->post(route('admin.orders.amend',$o),$d)->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame('Direct',$o->fresh()->delivery_service_name);$this->assertEquals(3734,$o->fresh()->delivery_rate_cents);
  $d['revision']=1;$d['fulfillment']='pickup';$this->post(route('admin.orders.amend',$o),$d)->assertRedirect()->assertSessionHasNoErrors();
  $this->assertNull($o->fresh()->delivery_service);$this->assertNull($o->fresh()->delivery_to_postal);$this->assertEquals(0,$o->fresh()->delivery_cents);
 }
}
