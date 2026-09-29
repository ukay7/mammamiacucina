<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\{User,Role,Product,GeneralSetting,Order};
class DeliveryQuoteTest extends TestCase {
 use RefreshDatabase;
 public function test_quote_and_checkout_snapshot_are_revalidated():void {
  DB::table('delivery_postal_zones')->insert([['prefix'=>'M2N','zone'=>8],['prefix'=>'M1P','zone'=>3]]);
  DB::table('delivery_services')->insert(['code'=>'bullet','name'=>'Bullet']);
  DB::table('delivery_rates')->insert(['service_code'=>'bullet','from_zone'=>8,'to_zone'=>3,'amount_cents'=>5876]);
  GeneralSetting::findOrFail(1)->update(['matrix_delivery_enabled'=>true,'warehouse_postal_code'=>'M2N','tax_basis_points'=>500]);
  $u=User::factory()->create(['account_type'=>'individual','is_active'=>true,'role_id'=>Role::where('name','Customer')->value('id')]);$this->actingAs($u);
  $p=Product::create(['category_id'=>1,'slug'=>'delivery-test','premium_marketing_name'=>'Cake','qr_code'=>'DEL','is_active'=>true,'total_selling_price_cad'=>10]);
  $this->postJson(route('cart.add',$p),['quantity'=>1])->assertOk();$this->get('/checkout')->assertOk()->assertSee('Delivery service');
  $data=['postal_code'=>'m1p 1a1','delivery_service'=>'bullet','country'=>'Canada'];
  $this->postJson(route('checkout.delivery-quote'),$data)->assertOk()->assertJsonPath('delivery_cents',5876)->assertJsonPath('delivery_from_zone',8)->assertJsonPath('delivery_to_zone',3);
  $this->postJson(route('checkout.delivery-options'),$data)->assertOk()->assertJsonPath('services.0.amount_cents',5876);
  $order=['checkout_token'=>session('checkout_token'),'first_name'=>'Test','last_name'=>'Customer','email'=>$u->email,'phone'=>'123','address'=>'Street','city'=>'Toronto','province'=>'ON']+$data;
  DB::table('delivery_rates')->update(['amount_cents'=>6000]);
  $this->post('/checkout',$order)->assertSessionHasErrors('delivery_service');$this->assertDatabaseCount('orders',0);
  $this->postJson(route('checkout.delivery-quote'),$data)->assertOk();
  GeneralSetting::findOrFail(1)->update(['matrix_delivery_enabled'=>false]);
  $this->post('/checkout',$order)->assertSessionHasErrors('cart');$this->assertDatabaseCount('orders',0);
  GeneralSetting::findOrFail(1)->update(['matrix_delivery_enabled'=>true]);
  $this->postJson(route('checkout.delivery-quote'),array_replace($data,['country'=>'USA']))->assertUnprocessable();
  $this->postJson(route('checkout.delivery-quote'),array_replace($data,['postal_code'=>'70896']))->assertUnprocessable();
  $this->post('/checkout',array_replace($order,['delivery_service'=>'overnight']))->assertSessionHasErrors('delivery_service');
  $this->postJson(route('checkout.delivery-quote'),$data)->assertOk();
  $this->post('/checkout',$order)->assertRedirect('/order-success');
  $o=Order::firstOrFail();$this->assertEquals(6000,$o->delivery_cents);$this->assertSame('bullet',$o->delivery_service);$this->assertEquals(7050,$o->final_total_cents);
  $this->assertSame('M1P 1A1',$o->delivery_to_postal);$this->assertEquals(6000,$o->delivery_rate_cents);
  $this->get('/order-success')->assertOk()->assertSee('Bullet')->assertSee('M2N')->assertSee('M1P 1A1')->assertSee('Zone 8');
  $this->get(route('order.print'))->assertOk()->assertSee('M2N')->assertSee('M1P 1A1')->assertSee('60.00');
  $this->get('/admin/my-orders/'.$o->id)->assertOk()->assertSee('Bullet');
  $this->postJson(route('checkout.delivery-quote'),array_replace($data,['postal_code'=>'K1A 0B1']))->assertUnprocessable();
  DB::table('delivery_rates')->update(['amount_cents'=>null]);$this->postJson(route('checkout.delivery-quote'),$data)->assertUnprocessable();
 }
}
