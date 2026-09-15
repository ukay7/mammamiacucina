<?php

namespace Tests\Feature;

use App\Models\{GeneralSetting, Order, Product, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSaleTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $user = User::factory()->create(['role_id' => Role::where('is_super', true)->value('id'), 'is_active' => true]);
        $this->actingAs($user);
        return $user;
    }

    private function product(string $code = 'POS-TEST', ?int $stock = 10): Product
    {
        $product = Product::create(['category_id' => 1, 'slug' => strtolower($code), 'premium_marketing_name' => 'Counter Cake '.$code,
            'qr_code' => $code, 'product_code' => 'SUP-'.$code, 'is_active' => true, 'total_selling_price_cad' => '10.695']);
        $product->inventory()->create(['quantity_on_hand' => $stock]);
        return $product;
    }

    private function quote(Product $product, string $fulfillment = 'pickup', int $quantity = 2): array
    {
        return $this->postJson(route('admin.pos.quote'), ['items' => [['id' => $product->id, 'quantity' => $quantity]], 'fulfillment' => $fulfillment])
            ->assertOk()->json();
    }

    private function sale(array $quote, array $extra = [])
    {
        return $this->postJson(route('admin.pos.store'), array_merge(['quote' => $quote['quote'], 'payment_method' => 'cash', 'payment_status' => 'paid'], $extra));
    }

    public function test_barcode_label_uses_only_qr_field_and_preserves_leading_zeros(): void
    {
        $this->staff();
        $product = $this->product('QR-LABEL');
        $product->update(['qr_code' => '00100068']);
        $this->assertSame('00100068', $product->barcode_number);
        $this->get(route('admin.products.labels', ['product_ids' => [$product->id]]))
            ->assertOk()->assertSee('data-code="00100068"', false)->assertDontSee('MMC-P-');
        $this->getJson(route('admin.pos.products', ['q' => '00100068', 'scan' => 1]))
            ->assertOk()->assertJsonPath('products.0.id', $product->id)->assertJsonPath('products.0.barcode', '00100068');
        $this->get('/admin/products')->assertOk()->assertSee('fa-eye')->assertSee('fa-edit')->assertSee('fa-barcode');
    }
    public function test_counter_sale_uses_selling_price_tax_no_delivery_and_retries_once(): void
    {
        $this->staff();
        GeneralSetting::findOrFail(1)->update(['delivery_cents' => 500, 'tax_basis_points' => 500]);
        $product = $this->product();
        $quote = $this->quote($product);
        $this->assertSame(2140, $quote['subtotal']);
        $this->assertSame(107, $quote['tax']);
        $this->assertSame(0, $quote['delivery']);
        $response = $this->sale($quote)->assertOk()->assertJsonPath('total',2247);
        $order = Order::firstOrFail();
        $this->assertSame('8.000', $product->inventory()->first()->quantity_on_hand);
        $this->assertSame('delivered', $order->status);
        $this->assertSame('Walk-in customer', $order->first_name);
        $this->assertSame('pos', $order->source);
        $this->assertEquals(1070, $order->items()->first()->unit_cents);
        $this->sale($quote)->assertOk()->assertJsonPath('number', $response->json('number'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->get($response->json('print_url'))->assertOk()->assertSee('Sales Receipt')->assertSee('Collected in store')->assertDontSee('Cash on delivery');
        $this->get('/admin/orders')->assertOk()->assertSee('POS');
    }

    public function test_delivery_sale_requires_address_and_cancellation_restocks_once(): void
    {
        $this->staff();
        GeneralSetting::findOrFail(1)->update(['delivery_cents' => 500]);
        $product = $this->product();
        $quote = $this->quote($product, 'delivery');
        $this->assertSame(500, $quote['delivery']);
        $this->sale($quote)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->sale($quote, ['first_name' => 'Customer', 'phone' => '555123', 'address' => '10 Test Street', 'city' => 'Toronto',
            'province' => 'ON', 'postal_code' => 'M1M1M1', 'country' => 'Canada', 'payment_status' => 'unpaid'])->assertOk();
        $order = Order::firstOrFail();
        $this->assertSame('placed', $order->status);
        $payload = ['status'=>'cancelled','payment_status'=>'unpaid','revision'=>0,'delivery'=>'5.00','tax'=>number_format($order->tax_cents/100,2,'.','')];
        $this->patch(route('admin.orders.update',$order),$payload)->assertRedirect();
        $this->assertSame('10.000',$product->inventory()->first()->quantity_on_hand);
        $payload['revision']=1;
        $this->patch(route('admin.orders.update',$order),$payload)->assertRedirect();
        $this->assertSame('10.000',$product->inventory()->first()->quantity_on_hand);
    }

    public function test_zero_stock_and_insufficient_stock_block_checkout_without_partial_changes(): void
    {
        $this->staff();
        $product=$this->product();
        $quote=$this->quote($product);
        $product->inventory()->update(['quantity_on_hand'=>1]);
        $this->sale($quote)->assertUnprocessable();
        $this->assertSame('1.000',$product->inventory()->first()->quantity_on_hand);
        $this->assertDatabaseCount('orders',0);
        $this->assertDatabaseCount('inventory_movements',0);
        $product->inventory()->update(['quantity_on_hand'=>0]);
        $this->postJson(route('admin.pos.quote'),['items'=>[['id'=>$product->id,'quantity'=>1]],'fulfillment'=>'pickup'])->assertUnprocessable();
    }

    public function test_prices_settings_and_expired_quotes_require_review_again(): void
    {
        $this->staff();$product=$this->product();$quote=$this->quote($product);
        $product->update(['total_selling_price_cad'=>25]);
        $this->sale($quote)->assertUnprocessable();
        $quote=$this->quote($product);
        GeneralSetting::findOrFail(1)->update(['tax_basis_points'=>1300]);
        $this->sale($quote)->assertUnprocessable();
        $quote=$this->quote($product);$this->travel(31)->minutes();
        $this->sale($quote)->assertUnprocessable();$this->travelBack();
        $this->assertDatabaseCount('orders',0);
        $this->assertSame('10.000',$product->inventory()->first()->quantity_on_hand);
    }

    public function test_untracked_stock_and_external_card_payment_are_supported(): void
    {
        $this->staff();$product=$this->product('UNTRACKED',null);$quote=$this->quote($product);
        $this->sale($quote,['payment_status'=>'unpaid'])->assertUnprocessable();
        $response=$this->sale($quote,['payment_method'=>'card'])->assertOk();
        $this->assertNull($product->inventory()->first()->quantity_on_hand);
        $this->assertDatabaseCount('inventory_movements',0);
        $this->get($response->json('print_url'))->assertOk()->assertSee('external terminal');
        $this->assertSame('card',Order::firstOrFail()->payment_method);
    }

    public function test_scan_matches_generated_barcode_qr_and_supplier_code_and_flags_ambiguity(): void
    {
        $this->staff();$product=$this->product();
        foreach([$product->barcode_number,$product->qr_code,$product->product_code] as $code){
            $this->getJson(route('admin.pos.products',['q'=>$code,'scan'=>1]))->assertOk()->assertJsonCount(1,'products')->assertJsonPath('products.0.id',$product->id);
        }
        $other=$this->product('OTHER');
        $other->update(['product_code'=>$product->product_code]);
        $this->getJson(route('admin.pos.products',['q'=>$product->product_code,'scan'=>1]))->assertOk()->assertJsonCount(2,'products');
        $this->getJson(route('admin.pos.products',['q'=>'Counter Cake']))->assertOk()->assertJsonCount(2,'products');
        $this->get('/admin/pos')->assertOk()->assertSee('Scan with camera');
        $this->get(route('admin.products.labels',['product_ids'=>[$product->id],'copies'=>2]))->assertOk()->assertSee($product->barcode_number)->assertSee('Print / Save as PDF');
        $this->get('/admin/products')->assertOk()->assertSee($product->barcode_number);
        $this->get(route('admin.products.index',['q'=>$product->barcode_number]))->assertOk()->assertViewHas('products',fn ($rows)=>$rows->contains('id', $product->id));
    }

    public function test_permissions_quote_ownership_and_tampering(): void
    {
        $this->staff();$product=$this->product();$quote=$this->quote($product);
        $this->sale(['quote'=>'tampered'])->assertUnprocessable();
        $other=$this->staff();
        $this->sale($quote)->assertUnprocessable();
        $role=Role::create(['name'=>'POS only','permissions'=>['pos.manage']]);
        $other->update(['role_id'=>$role->id]);$other->unsetRelation('role');
        $this->get('/admin/pos')->assertOk();
        $this->get(route('admin.products.labels',['product_ids'=>[$product->id]]))->assertForbidden();
        $quote=$this->quote($product);
        $response=$this->sale($quote)->assertOk();
        $this->get($response->json('print_url'))->assertOk();
        $role->update(['permissions'=>['products.view']]);$other->unsetRelation('role');
        $this->get('/admin/pos')->assertForbidden();
        $this->sale($quote)->assertForbidden();
        $this->get(route('admin.products.labels',['product_ids'=>[$product->id]]))->assertOk();
    }

    public function test_invalid_quantities_duplicate_products_and_inactive_products_are_rejected(): void
    {
        $this->staff();$product=$this->product();
        foreach([0,-1,1.5,10000] as $quantity){
            $this->postJson(route('admin.pos.quote'),['items'=>[['id'=>$product->id,'quantity'=>$quantity]],'fulfillment'=>'pickup'])->assertUnprocessable();
        }
        $this->postJson(route('admin.pos.quote'),['items'=>[['id'=>$product->id,'quantity'=>1],['id'=>$product->id,'quantity'=>1]],'fulfillment'=>'pickup'])->assertUnprocessable();
        $quote=$this->quote($product);$product->update(['is_active'=>false]);$this->sale($quote)->assertUnprocessable();
        $this->assertDatabaseCount('orders',0);
    }
}
