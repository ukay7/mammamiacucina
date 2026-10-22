<?php

namespace Tests\Feature;

use App\Models\{GeneralSetting, Order, Product, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSaleTest extends TestCase
{
    use RefreshDatabase;

    private int $customerId;

    private function staff(): User
    {
        $user = User::factory()->create(['role_id' => Role::where('is_super', true)->value('id'), 'is_active' => true]);
        $customer=User::factory()->create(['name'=>'Walkin Customer','account_type'=>'individual','phone'=>'555123','is_active'=>true]);
        $this->customerId=$customer->customerRecord()->id;
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
        return $this->postJson(route('admin.pos.quote'), ['customer_id'=>$this->customerId,'items' => [['id' => $product->id, 'quantity' => $quantity]], 'fulfillment' => $fulfillment])
            ->assertOk()->json();
    }

    private function sale(array $quote, array $extra = [])
    {
        return $this->postJson(route('admin.pos.store'), array_merge(['customer_id'=>$this->customerId,'first_name'=>'Walk-in customer','email'=>'pos-customer@example.test','quote' => $quote['quote'], 'packing_confirmed'=>1,'payment_method' => 'cash', 'payment_status' => 'paid'], $extra));
    }

    public function test_barcode_label_uses_only_qr_field_and_preserves_leading_zeros(): void
    {
        $this->staff();
        $product = $this->product('QR-LABEL');
        $product->update(['qr_code' => '00100068']);
        $this->assertSame('00100068', $product->barcode_number);
        $this->get(route('admin.products.labels', ['product_ids' => [$product->id]]))
            ->assertOk()->assertSee('data-code="00100068"', false)->assertDontSee('MMC-P-');
        $this->getJson(route('admin.pos.products', ['customer_id'=>$this->customerId,'q' => '00100068', 'scan' => 1]))
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
        $this->assertSame('warehouse_pending', $order->status);
        $this->assertFalse((bool) $order->items()->first()->packed);
        $this->assertNull($order->invoice);
        $this->assertSame('Walkin', $order->first_name);
        $this->assertSame('pos', $order->source);
        $this->assertNotNull($order->ordered_at);
        $this->assertTrue($order->ordered_at->diffInSeconds($order->created_at) < 2);
        $this->assertEquals(1070, $order->items()->first()->unit_cents);
        $this->sale($quote)->assertOk()->assertJsonPath('number', $response->json('number'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->get($response->json('print_url'))->assertOk()->assertSee('Payment Receipt')->assertSee('Cash on pick up')->assertDontSee('Cash on delivery');
        $this->get('/admin/orders')->assertOk()->assertSee('POS');
    }

    public function test_delivery_sale_requires_address_and_goes_to_warehouse(): void
    {
        $this->staff();
        GeneralSetting::findOrFail(1)->update(['delivery_cents' => 500]);
        $product = $this->product();
        $quote = $this->quote($product, 'delivery');
        $this->assertSame(500, $quote['delivery']);
        $this->sale($quote)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->sale($quote, ['first_name' => 'Customer', 'phone' => '555123', 'address' => '10 Test Street', 'city' => 'Toronto',
            'province' => 'ON', 'postal_code' => 'M1M1M1', 'country' => 'Canada', 'payment_status' => 'paid'])->assertOk();
        $order = Order::firstOrFail();
        $this->assertSame('warehouse_pending', $order->status);
        $payload = ['status'=>'cancelled','payment_status'=>'refunded','restock_inventory'=>1,'revision'=>0,'delivery'=>'5.00','tax'=>number_format($order->tax_cents/100,2,'.','')];
        $this->patch(route('admin.orders.update',$order),$payload)->assertSessionHasNoErrors();
        $this->assertSame('10.000',$product->inventory()->first()->quantity_on_hand);

    }

    public function test_zero_and_insufficient_stock_accept_sales_and_record_shortages(): void
    {
        $this->staff();
        $product=$this->product();
        $quote=$this->quote($product);
        $product->inventory()->update(['quantity_on_hand'=>1]);
        $this->sale($quote)->assertOk();
        $this->assertSame('-1.000',$product->inventory()->first()->quantity_on_hand);
        $this->assertDatabaseHas('order_items',['product_id'=>$product->id,'inventory_shortage_quantity'=>1]);
        $this->assertDatabaseCount('orders',1);
        $this->assertDatabaseCount('inventory_movements',1);

        $zero=$this->product('ZERO',0);
        $zeroQuote=$this->quote($zero,'pickup',1);
        $this->sale($zeroQuote)->assertOk();
        $this->assertSame('-1.000',$zero->inventory()->first()->quantity_on_hand);
        $this->assertDatabaseHas('order_items',['product_id'=>$zero->id,'inventory_shortage_quantity'=>1]);
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

    public function test_untracked_stock_is_supported_and_card_payment_is_disabled(): void
    {
        $this->staff();$product=$this->product('UNTRACKED',null);$quote=$this->quote($product);
        $this->sale($quote,['payment_status'=>'refunded'])->assertUnprocessable();
        $this->sale($quote,['payment_method'=>'card'])->assertUnprocessable();
        $response=$this->sale($quote)->assertOk();
        $this->assertNull($product->inventory()->first()->quantity_on_hand);
        $this->assertDatabaseCount('inventory_movements',0);
        $this->get($response->json('print_url'))->assertOk()->assertSee('Cash on pick up');
        $this->assertSame('cash',Order::firstOrFail()->payment_method);
    }

    public function test_scan_matches_generated_barcode_qr_and_supplier_code_and_flags_ambiguity(): void
    {
        $this->staff();$product=$this->product();
        foreach([$product->barcode_number,$product->qr_code,$product->product_code] as $code){
            $this->getJson(route('admin.pos.products',['customer_id'=>$this->customerId,'q'=>$code,'scan'=>1]))->assertOk()->assertJsonCount(1,'products')->assertJsonPath('products.0.id',$product->id);
        }
        $other=$this->product('OTHER');
        $other->update(['product_code'=>$product->product_code]);
        $this->getJson(route('admin.pos.products',['customer_id'=>$this->customerId,'q'=>$product->product_code,'scan'=>1]))->assertOk()->assertJsonCount(2,'products');
        $this->getJson(route('admin.pos.products',['customer_id'=>$this->customerId,'q'=>'Counter Cake']))->assertOk()->assertJsonCount(2,'products');
        $this->get('/admin/pos')->assertOk()->assertSee('Scan with camera');
        $this->get(route('admin.products.labels',['product_ids'=>[$product->id],'copies'=>2]))->assertOk()->assertSee($product->barcode_number)->assertSee('Print / Save as PDF');
        $this->get('/admin/products')->assertOk()->assertSee($product->barcode_number);
        $this->get(route('admin.products.index',['customer_id'=>$this->customerId,'q'=>$product->barcode_number]))->assertOk()->assertViewHas('products',fn ($rows)=>$rows->contains('id', $product->id));
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
            $this->postJson(route('admin.pos.quote'),['customer_id'=>$this->customerId,'items'=>[['id'=>$product->id,'quantity'=>$quantity]],'fulfillment'=>'pickup'])->assertUnprocessable();
        }
        $this->postJson(route('admin.pos.quote'),['customer_id'=>$this->customerId,'items'=>[['id'=>$product->id,'quantity'=>1],['id'=>$product->id,'quantity'=>1]],'fulfillment'=>'pickup'])->assertUnprocessable();
        $quote=$this->quote($product);$product->update(['is_active'=>false]);$this->sale($quote)->assertUnprocessable();
        $this->assertDatabaseCount('orders',0);
    }

    public function test_only_the_warehouse_user_type_skips_the_warehouse_queue(): void
    {
        $this->staff();
        $product = $this->product('WAREHOUSE-FLOW');
        $warehouseRole = Role::where('name', Role::WAREHOUSE_USER)->firstOrFail();
        $warehouseRole->update(['permissions' => ['pos.manage', 'warehouse.pack']]);
        $warehouseUser = User::factory()->create(['role_id' => $warehouseRole->id, 'is_active' => true]);
        $this->actingAs($warehouseUser);

        $this->get(route('admin.pos.index'))->assertOk()->assertSee('All items are packed and ready for dispatch / pickup.');
        $quote = $this->quote($product);
        $payload = ['customer_id'=>$this->customerId,'quote'=>$quote['quote'],'payment_method'=>'cash','payment_status'=>'paid'];
        $this->postJson(route('admin.pos.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('packing_confirmed');
        $this->postJson(route('admin.pos.store'), $payload + ['packing_confirmed'=>1])->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame('ready_to_dispatch', $order->status);
        $this->assertTrue((bool) $order->items()->first()->packed);
        $this->assertNotNull($order->invoice);
    }

    public function test_unpaid_cash_order_reserves_stock_and_enters_the_warehouse_without_recording_payment(): void
    {
        $this->staff();
        $product = $this->product('UNPAID-CASH');
        $quote = $this->quote($product);
        $response = $this->sale($quote, ['payment_status' => 'unpaid'])->assertOk()->assertJsonPath('payment_status', 'unpaid');
        $order = Order::firstOrFail();
        $this->assertSame('warehouse_pending', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertNull($order->paid_at);
        $this->assertSame(0, $order->net_received_cents);
        $this->assertSame($order->final_total_cents, $order->balance_cents);
        $this->assertSame('8.000', $product->inventory()->first()->quantity_on_hand);
        $this->get($response->json('print_url'))->assertOk()->assertSee('Order Confirmation')->assertDontSee('<strong>Payment Receipt</strong>', false);
        $this->sale($quote, ['payment_status' => 'unpaid'])->assertOk()->assertJsonPath('number', $order->number);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->patch(route('admin.orders.update', $order), ['revision' => $order->revision, 'status' => 'warehouse_pending', 'payment_status' => 'paid', 'delivery' => '0', 'tax' => number_format($order->tax_cents / 100, 2, '.', '')])->assertSessionHasNoErrors();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(0, $order->fresh()->balance_cents);
        $this->assertSame('8.000', $product->inventory()->first()->quantity_on_hand);
    }

    public function test_unpaid_cash_delivery_keeps_the_balance_due_and_requires_the_address(): void
    {
        $this->staff();
        $product = $this->product('UNPAID-DELIVERY');
        $quote = $this->quote($product, 'delivery');
        $this->sale($quote, ['payment_status' => 'unpaid'])->assertUnprocessable();
        $this->sale($quote, ['payment_status' => 'unpaid', 'phone' => '555123', 'address' => '10 Test Street', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M1M1M1', 'country' => 'Canada'])->assertOk();
        $order = Order::firstOrFail();
        $this->assertSame('warehouse_pending', $order->status);
        $this->assertNull($order->paid_at);
        $this->assertSame($order->final_total_cents, $order->balance_cents);
    }

    public function test_pos_search_and_review_include_source_name_and_subtitle(): void
    {
        $this->staff();
        $product = $this->product('BOTH-NAMES');
        $product->update(['italian_subtitle' => 'Torta al limone', 'original_description' => 'Supplier lemon dessert carton']);
        foreach (['Torta al limone', 'Supplier lemon dessert'] as $term) {
            $this->getJson(route('admin.pos.products', ['customer_id' => $this->customerId, 'q' => $term]))
                ->assertOk()->assertJsonCount(1, 'products')->assertJsonPath('products.0.name', $product->premium_marketing_name)
                ->assertJsonPath('products.0.subtitle', 'Torta al limone')->assertJsonPath('products.0.source_name', 'Supplier lemon dessert carton');
        }
        $this->getJson(route('admin.pos.products', ['customer_id' => $this->customerId, 'q' => 'Torta al limone', 'scan' => 1]))->assertOk()->assertJsonCount(0, 'products');
        $quote = $this->quote($product);
        $this->assertSame('Torta al limone', $quote['items'][0]['subtitle']);
        $this->assertSame('Supplier lemon dessert carton', $quote['items'][0]['source_name']);
    }

    public function test_warehouse_staff_can_record_unpaid_cash_without_inventing_a_collection(): void
    {
        $this->staff();
        $product = $this->product('WAREHOUSE-UNPAID');
        $warehouseRole = Role::where('name', Role::WAREHOUSE_USER)->firstOrFail();
        $warehouseRole->update(['permissions' => ['pos.manage', 'warehouse.pack']]);
        $this->actingAs(User::factory()->create(['role_id' => $warehouseRole->id, 'is_active' => true]));
        $quote = $this->quote($product);
        $this->sale($quote, ['payment_status' => 'unpaid'])->assertOk()->assertJsonPath('payment_status', 'unpaid');
        $order = Order::firstOrFail();
        $this->assertSame('ready_to_dispatch', $order->status);
        $this->assertTrue((bool) $order->items()->first()->packed);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertNull($order->paid_at);
        $this->assertSame(0, $order->net_received_cents);
        $this->assertSame($order->final_total_cents, $order->balance_cents);
        $this->assertSame('8.000', $product->inventory()->first()->quantity_on_hand);
    }
}
