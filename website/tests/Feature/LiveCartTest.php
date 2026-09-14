<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveCartTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $p = Product::create(['category_id' => 1, 'slug' => 'cake', 'premium_marketing_name' => 'Test Cake', 'qr_code' => 'CART', 'is_active' => true, 'total_selling_price_cad' => '10.695']);
        $p->inventory()->create(['quantity_on_hand' => 10]);

        return $p;
    }

    public function test_cart_add_updates_header_and_persists_without_trusting_client_price(): void
    {
        $p = $this->product();
        $url = route('cart.add', $p);
        $this->postJson($url, ['quantity' => 2, 'price' => 0.01])->assertOk()->assertJsonPath('count', 2)->assertSessionHas('storefront_cart.'.$p->id, 2);
        $this->get('/cart')->assertOk()->assertSee('Test Cake')->assertSee('$21.40');
        $this->postJson($url, ['quantity' => 1])->assertJsonPath('count', 3);
        $this->patchJson(route('cart.update', $p), ['quantity' => 1])->assertJsonPath('count', 1);
        $this->deleteJson(route('cart.remove', $p))->assertJsonPath('count', 0);
        $this->assertSame('10.000', $p->inventory->quantity_on_hand);
    }

    public function test_cart_validation_and_unavailable_products(): void
    {
        $p = $this->product();
        $url = route('cart.add', $p);
        foreach ([0, -1, 100, 1.5] as $qty) {
            $this->postJson($url, ['quantity' => $qty])->assertUnprocessable();
        }
        $this->postJson($url, ['quantity' => 11])->assertUnprocessable();
        $p->update(['total_selling_price_cad' => null]);
        $this->postJson($url, ['quantity' => 1])->assertUnprocessable();
        $p->update(['is_active' => false]);
        $this->postJson($url, ['quantity' => 1])->assertNotFound();
    }

    public function test_gallery_shows_one_media_item_and_four_ordered_thumbnails(): void
    {
        $p = $this->product();
        for ($i = 1; $i <= 4; $i++) {
            $p->media()->create(['path' => 'image'.$i.'.png', 'original_name' => 'image', 'kind' => 'image', 'mime_type' => 'image/png', 'sort_order' => $i]);
        }
        $response = $this->get('/products/cake')->assertOk()->assertSee('Add to Cart');
        $html = $response->getContent();
        $this->assertSame(4, substr_count($html, 'data-live-thumb="'));
        $this->assertSame(3,substr_count($html,'data-live-slide  hidden'));
    }
}
