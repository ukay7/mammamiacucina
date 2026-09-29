<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Role,Product,Order};
use App\Services\{ProductPricing,ProductPriceWorkflow,StorefrontCart};
class BusinessPricingTest extends TestCase {
 use RefreshDatabase;
 public function test_two_profit_settings_calculate_and_save():void{
  $p=Product::create(['category_id'=>1,'slug'=>'tier','premium_marketing_name'=>'Tier Cake','qr_code'=>'TIER','is_active'=>true,'total_selling_price_cad'=>15]);
  $admin=User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);$this->actingAs($admin);
  $inputs=['purchase_price'=>'10','purchase_basis'=>'piece','currency'=>'CAD','pieces_per_carton'=>'6','discount'=>'0','fx'=>'1','freight_carton'=>'0','other_unit_cost'=>'0','profit_mode'=>'markup','profit_percent'=>'50','business_profit_percent'=>'30','carton_status'=>'working'];
  $r=app(ProductPricing::class)->calculate($inputs);
  $this->assertSame(1500,$r['unit_cents']);$this->assertSame(1300,$r['business_unit_cents']);$this->assertSame(7800,$r['business_carton_cents']);
  $this->postJson(route('admin.products.pricing.save',$p),['inputs'=>$inputs,'revision'=>0,'editor_revision'=>0,'choice'=>'unit'])->assertOk()->assertJsonPath('business_price','13.00000000');
  $this->assertEquals(13,$p->fresh()->business_selling_price_cad);
  $this->get(route('admin.products.edit',$p))->assertOk()->assertSee('Profit setting % (Business)')->assertSee('Total selling price CAD (Business)');
  $inputs['profit_mode']='margin';$inputs['business_profit_percent']='100';
  $this->postJson(route('admin.products.pricing.quote',$p),['inputs'=>$inputs])->assertUnprocessable();
 }
 public function test_verified_business_prices_display_cart_and_charge_match():void{
  $p=Product::create(['category_id'=>1,'slug'=>'tier-cake','premium_marketing_name'=>'Tier Cake','qr_code'=>'TC','is_active'=>true,'total_selling_price_cad'=>15,'business_selling_price_cad'=>12]);
  $this->get(route('catalogue.product',$p->slug))->assertOk()->assertSee('$15.00');
  $u=User::factory()->create(['name'=>'Business Buyer','account_type'=>'business','phone'=>'123','is_active'=>true,'email_verified_at'=>null,'role_id'=>Role::where('name','Customer')->value('id')]);
  $this->actingAs($u)->get(route('catalogue.product',$p->slug))->assertSee('$15.00');
  $u->forceFill(['email_verified_at'=>now()])->save();
  $this->get(route('catalogue.product',$p->slug))->assertOk()->assertSee('$12.00')->assertDontSee('$15.00');
  $this->postJson(route('cart.add',$p),['quantity'=>2])->assertOk();
  $this->assertSame(2400,app(StorefrontCart::class)->snapshot()['total']);
  $this->get('/checkout')->assertOk();
  $this->post('/checkout',['checkout_token'=>session('checkout_token'),'first_name'=>'Business','last_name'=>'Buyer','email'=>$u->email,'phone'=>'123','address'=>'10 Street','city'=>'Toronto','province'=>'ON','postal_code'=>'123','country'=>'Canada','unit_cents'=>1])->assertRedirect('/order-success');
  $o=Order::firstOrFail();$this->assertSame(2400,(int)$o->subtotal_cents);$this->assertSame(1200,(int)$o->items->first()->unit_cents);
  $u->forceFill(['account_type'=>'individual'])->save();
  $this->get(route('catalogue.product',$p->slug))->assertSee('$15.00');
  $p->update(['business_selling_price_cad'=>null]);$u->forceFill(['account_type'=>'business'])->save();$this->assertEquals(15,$p->storefront_price);
 }
 public function test_business_sort_and_changed_quote_are_checked():void{
  $u=User::factory()->create(['account_type'=>'business','is_active'=>true,'role_id'=>Role::where('name','Customer')->value('id')]);
  $a=Product::create(['category_id'=>1,'slug'=>'sort-a','premium_marketing_name'=>'Cheap Business','qr_code'=>'SA','is_active'=>true,'total_selling_price_cad'=>90,'business_selling_price_cad'=>10]);
  $b=Product::create(['category_id'=>1,'slug'=>'sort-b','premium_marketing_name'=>'Expensive Business','qr_code'=>'SB','is_active'=>true,'total_selling_price_cad'=>5,'business_selling_price_cad'=>20]);
  $this->actingAs($u)->get(route('theme.product-grid',['sort'=>'price-low']))->assertOk()->assertSeeInOrder(['Cheap Business','Expensive Business']);
  $this->postJson(route('cart.add',$a),['quantity'=>1])->assertOk();$this->get('/checkout')->assertOk();$token=session('checkout_token');
  $a->update(['business_selling_price_cad'=>11]);
  $this->post('/checkout',['checkout_token'=>$token,'first_name'=>'Test','last_name'=>'Buyer','email'=>$u->email,'phone'=>'123','address'=>'Street','city'=>'City','province'=>'ON','postal_code'=>'123','country'=>'Canada'])->assertSessionHasErrors('cart');
  $this->assertDatabaseCount('orders',0);
 }
}
