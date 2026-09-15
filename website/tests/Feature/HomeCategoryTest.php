<?php
namespace Tests\Feature;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class HomeCategoryTest extends TestCase {
 use RefreshDatabase;
 public function test_home_categories_use_visibility_images_order_and_real_grid_links():void {
  $this->seed(\Database\Seeders\HomeCategoriesSeeder::class);
  $shown=Category::create(['name'=>'Seasonal Collection','slug'=>'seasonal-collection','is_active'=>true,'show_to_customer'=>true,'sort_order'=>0]);
  Category::create(['name'=>'Internal Homepage Test','slug'=>'internal-home-test','is_active'=>true,'show_to_customer'=>false]);
  Category::create(['name'=>'Inactive Homepage Test','slug'=>'inactive-home-test','is_active'=>false,'show_to_customer'=>true]);
  $html=$this->get('/')->assertOk()->getContent();
  $section=explode('</section>',explode('aria-label="Explore our categories">',$html)[1])[0];
  $this->assertStringContainsString('Seasonal Collection',$section);
  $this->assertStringContainsString(route('theme.product-grid',['category'=>$shown->slug]),$section);
  $this->assertStringNotContainsString('Internal Homepage Test',$section);$this->assertStringNotContainsString('Inactive Homepage Test',$section);
  foreach(['Traditional Cakes','Operetta Pastries','Cannoli & Cornetti'] as $name){
   $c=Category::where('name',$name)->firstOrFail();
   $this->assertStringContainsString(route('category.image',$c),$section);
   $this->get(route('category.image',$c))->assertOk();
  }
  $shown->update(['show_to_customer'=>false]);
  $this->get('/')->assertOk()->assertDontSee('Seasonal Collection');
 }
}
