<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorefrontCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, ?string $price = null): Product
    {
        return Product::create(['category_id' => 1, 'slug' => strtolower($name), 'premium_marketing_name' => $name, 'supplier' => 'Private Supplier', 'qr_code' => $name, 'is_active' => true, 'total_selling_price_cad' => $price]);
    }

    public function test_grid_and_ajax_use_active_database_products_and_multiple_categories(): void
    {
        $cake = Category::create(['name' => 'Cakes', 'slug' => 'cakes-test', 'is_active' => true]);
        $other = Category::create(['name' => 'Specials', 'slug' => 'specials', 'is_active' => true]);
        $product = $this->product('Chocolate', '25');
        $product->categories()->sync([$cake->id, $other->id]);
        $hidden = $this->product('Hidden');
        $hidden->update(['is_active' => false]);
        $this->get('/product-grid')->assertOk()->assertSee('Chocolate')->assertDontSee('Hidden')->assertDontSee('Private Supplier');
        foreach (['cakes', 'cakes-test', 'specials'] as $cat) {
            $r = $this->getJson('/product-grid?category='.$cat)->assertOk()->assertJsonPath('total', 1);
            $this->assertStringContainsString('Chocolate', $r->json('html'));
        }
        $this->getJson('/product-grid?category=missing')->assertOk()->assertJsonPath('total', 0);
        $this->get('/products/chocolate')->assertOk()->assertSee('Chocolate')->assertDontSee('Private Supplier');
        $this->get('/products/hidden')->assertNotFound();
    }

    public function test_price_sort_pagination_and_validation(): void
    {
        $this->product('Cheap', '10');
        $this->product('Dear', '20');
        $this->product('Unknown');
        $r = $this->getJson('/product-grid?sort=price-low')->assertOk();
        $this->assertTrue(strpos($r->json('html'), '<h3>Cheap') < strpos($r->json('html'), '<h3>Dear'));
        $this->getJson('/product-grid?min=11&max=21')->assertJsonPath('total', 1);
        $this->getJson('/product-grid?min=20&max=10')->assertUnprocessable();
        $this->getJson('/product-grid?show=999')->assertUnprocessable();
        for ($i = 0; $i < 10; $i++) {
            $this->product('Extra'.$i, '12');
        }
        $r = $this->getJson('/product-grid?show=6&page=2')->assertOk()->assertJsonPath('total', 13);
        $this->assertSame(6, substr_count($r->json('html'), '<article class="mmc-product-card'));
        $r = $this->getJson('/product-grid?show=6&page=999')->assertOk();
        $this->assertSame(1, substr_count($r->json('html'), '<article class="mmc-product-card'));
    }

    public function test_public_media_and_inactive_category_protection(): void
    {
        Storage::fake('local');
        $product = $this->product('Cake', '15');
        $video = $product->media()->create(['path' => 'video.mp4', 'original_name' => 'video', 'kind' => 'video', 'mime_type' => 'video/mp4', 'sort_order' => 0]);
        $image = $product->media()->create(['path' => 'cake.png', 'original_name' => 'cake', 'kind' => 'image', 'mime_type' => 'image/png', 'sort_order' => 1]);
        Storage::disk('local')->put('cake.png', 'image');
        $r = $this->getJson('/product-grid')->assertOk();
        $this->assertStringContainsString(route('catalogue.media', [$product, $image]), $r->json('html'));
        $this->assertStringNotContainsString(route('catalogue.media', [$product, $video]), $r->json('html'));
        $this->get(route('catalogue.media', [$product, $image]))->assertOk();
        $other = $this->product('Other');
        $this->get(route('catalogue.media', [$other, $image]))->assertNotFound();
        Category::find(1)->update(['is_active' => false]);
        $this->getJson('/product-grid')->assertJsonPath('total', 0);
        $this->get(route('catalogue.media',[$product, $image]))->assertNotFound();
    }
}
