<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function prepareOrder(): array
    {
        $p = Product::create(['category_id' => 1, 'slug' => 'order-cake', 'premium_marketing_name' => 'Order Cake', 'qr_code' => 'ORDER', 'is_active' => true, 'total_selling_price_cad' => '10.695']);
        $p->inventory()->create(['quantity_on_hand' => 10]);
        $this->postJson(route('cart.add', $p), ['quantity' => 2])->assertOk();
        $this->get('/checkout')->assertOk()->assertSee('Cash on delivery');

        return [$p, ['checkout_token' => session('checkout_token'), 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.test', 'phone' => '555-1234', 'address' => '123 Main Street', 'city' => 'Toronto', 'province' => 'Ontario', 'postal_code' => 'M1M 1M1', 'country' => 'Canada', 'notes' => 'Ring the bell']];
    }

    public function test_order_saved_once_with_snapshots_cash_and_stock_history(): void
    {
        [$p,$d] = $this->prepareOrder();
        $d['subtotal_cents'] = 1;
        $d['payment_method'] = 'card';
        $this->post('/checkout', $d)->assertRedirect('/order-success')->assertSessionMissing('storefront_cart');
        $order = Order::firstOrFail();
        $this->assertEquals(2140, $order->subtotal_cents);
        $this->assertSame('cash', $order->payment_method);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertNull($order->tax_cents);
        $this->assertNull($order->delivery_cents);
        $this->assertSame('8.000', $p->inventory()->first()->quantity_on_hand);
        $this->assertDatabaseHas('inventory_movements', ['product_id' => $p->id, 'quantity_change' => -2]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'name' => 'Order Cake', 'unit_cents' => 1070, 'quantity' => 2]);
        $this->post('/checkout', $d)->assertRedirect('/order-success');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame('8.000', $p->inventory()->first()->quantity_on_hand);
        $p->update(['premium_marketing_name' => 'Changed Cake', 'total_selling_price_cad' => 999]);
        $this->get('/order-success')->assertOk()->assertSee('Order Cake')->assertSee('$21.40')->assertDontSee('Changed Cake');
        session()->forget(['last_order_id', 'last_order_token']);
        $this->get('/order-success')->assertRedirect('/cart');
    }

    public function test_empty_invalid_details_and_stale_price_are_rejected(): void
    {
        $this->get('/checkout')->assertRedirect('/cart');
        [$p,$d] = $this->prepareOrder();
        $this->post('/checkout', array_merge($d, ['email' => 'invalid']))->assertSessionHasErrors('email');
        $p->update(['total_selling_price_cad' => 12]);
        $this->post('/checkout', $d)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame('10.000', $p->inventory()->first()->quantity_on_hand);
    }

    public function test_stock_unavailable_and_wrong_token_do_not_place_orders(): void
    {
        [$p,$d] = $this->prepareOrder();
        $this->post('/checkout', array_merge($d, ['checkout_token' => (string) Str::uuid()]))->assertSessionHasErrors('cart');
        $p->inventory()->update(['quantity_on_hand' => 1]);
        $this->post('/checkout', $d)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $p->update(['is_active' => false]);
        $this->post('/checkout', $d)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_admin_orders_require_permission(): void
    {
        [$p,$d] = $this->prepareOrder();
        $this->post('/checkout', $d)->assertRedirect('/order-success');
        $order = Order::firstOrFail();
        $this->get('/admin/orders')->assertRedirect('/admin/login');
        $role = Role::create(['name' => 'No Orders', 'permissions' => [], 'is_super' => false]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($user)->get('/admin/orders')->assertForbidden();
        $this->get('/admin/orders/'.$order->id)->assertForbidden();
        $role->update(['permissions' => ['orders.view']]);
        $user->unsetRelation('role');
        $this->actingAs($user)->get('/admin/orders')->assertOk()->assertSee($order->number);
        $this->get('/admin/orders/'.$order->id)->assertOk()->assertSee('123 Main Street')->assertSee('Order Cake');
    }
}
