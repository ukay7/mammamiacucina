<?php
namespace Tests\Feature;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class MostLovedCollectionTest extends TestCase {
 use RefreshDatabase;
 public function test_live_collection_products_and_stable_category_reference():void {
  $this->seed(\Database\Seeders\MostLovedSeeder::class);
  $c=Category::where('homepage_key','most_loved')->firstOrFail();
  $this->assertTrue($c->is_protected);$this->assertFalse($c->show_to_customer);$this->assertCount(6,$c->products);
  $p=$c->products->first();$p->update(['premium_marketing_name'=>'Live Featured Cake','total_selling_price_cad'=>12.34]);
  $c->update(['name'=>'Renamed Collection']);
  $this->get('/')->assertOk()->assertSee('Live Featured Cake')->assertSee('$12.34')->assertSee(route('catalogue.product',$p->slug));
  $c->products()->detach($p);
  $this->get('/')->assertOk()->assertDontSee('Live Featured Cake');
  $c->update(['is_active'=>false]);
  $this->get('/')->assertOk()->assertDontSee('id="mmc-loved-title"',false);
 }
}
