<?php
namespace Tests\Feature;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class RandomArrivalsTest extends TestCase {
 use RefreshDatabase;
 public function test_random_cards_have_images_live_prices_and_real_links():void {
  $this->seed(\Database\Seeders\MostLovedSeeder::class);
  $response=$this->get('/')->assertOk();
  $html=explode('</section>',explode('aria-labelledby="mmc-arrivals-title">',$response->getContent())[1])[0];
  $this->assertStringContainsString('/products/',$html);$this->assertStringContainsString('/product-media/',$html);
  $this->assertStringNotContainsString('Wishlist',$html);
  Product::query()->update(['is_active'=>false]);
  $p=Product::firstOrFail();$p->update(['is_active'=>true,'premium_marketing_name'=>'Random Live Cake','total_selling_price_cad'=>17.25]);
  $this->get('/')->assertOk()->assertSee('Random Live Cake')->assertSee('$17.25');
  $p->media()->delete();
  $html=$this->get('/')->assertOk()->getContent();
  $this->assertStringNotContainsString('id="mmc-arrivals-title"',$html);
 }
}
