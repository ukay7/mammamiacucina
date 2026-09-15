<?php
namespace Tests\Feature;
use App\Models\{Category,Product,Role,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CategoryVisibilityTest extends TestCase {
 use RefreshDatabase;
 public function test_hidden_collection_stays_in_admin_but_not_customer_navigation_or_labels():void {
  $user=User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);
  $this->actingAs($user);
  $this->post('/admin/categories',['name'=>'Internal Picks','is_active'=>1,'sort_order'=>0,'show_to_customer'=>0])->assertSessionHasNoErrors();
  $c=Category::where('name','Internal Picks')->firstOrFail();$this->assertFalse($c->show_to_customer);
  $p=Product::create(['category_id'=>$c->id,'slug'=>'visibility-cake','premium_marketing_name'=>'Visibility Cake','is_active'=>true,'total_selling_price_cad'=>10]);
  $this->get('/admin/categories')->assertOk()->assertSee('Internal Picks')->assertSee('Show to Customer');
  $this->get('/product-grid')->assertOk()->assertDontSee('Internal Picks')->assertSee('Visibility Cake');
  $this->get('/products/'.$p->slug)->assertOk()->assertDontSee('Internal Picks');
  $this->getJson('/product-grid?category='.$c->slug)->assertOk()->assertJsonPath('total',0);
  $this->put('/admin/categories/'.$c->id,['name'=>$c->name,'is_active'=>1,'sort_order'=>0,'show_to_customer'=>1])->assertSessionHasNoErrors();
  $this->get('/product-grid')->assertOk()->assertSee('Internal Picks');
  $this->get('/products/'.$p->slug)->assertOk()->assertSee('Internal Picks');
  $this->assertTrue(Category::find(1)->show_to_customer);
 }
}
