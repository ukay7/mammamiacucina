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
        $this->assertSame('mmc-'.$order->id, $order->number);
        $this->assertEquals(2140, $order->subtotal_cents);
        $this->assertSame('cash', $order->payment_method);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertEquals(0,$order->tax_cents);
        $this->assertEquals(0,$order->delivery_cents);
        $this->assertSame('8.000', $p->inventory()->first()->quantity_on_hand);
        $this->assertDatabaseHas('inventory_movements', ['product_id' => $p->id, 'quantity_change' => -2]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'name' => 'Order Cake', 'unit_cents' => 1070, 'quantity' => 2]);
        $this->post('/checkout', $d)->assertRedirect('/order-success');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame('8.000', $p->inventory()->first()->quantity_on_hand);
        $p->update(['premium_marketing_name' => 'Changed Cake', 'total_selling_price_cad' => 999]);
        $this->get('/order-success')->assertOk()->assertSee('Order Cake')->assertSee('$21.40')->assertDontSee('Changed Cake');
        $this->get('/order-print')->assertOk()->assertSee('Order Confirmation')->assertSee('Order Cake')->assertSee('logo.png');
        session()->forget(['last_order_id', 'last_order_token']);
        $this->get('/order-print')->assertNotFound();
        $this->get('/order-success')->assertRedirect('/cart');
    }

    public function test_next_order_uses_next_database_id_and_legacy_numbers_are_migrated(): void
    {
        [$p,$d] = $this->prepareOrder();
        $this->post('/checkout', $d)->assertRedirect('/order-success');
        $first = Order::firstOrFail();
        $this->postJson(route('cart.add',$p), ['quantity'=>1])->assertOk();
        $this->get('/checkout')->assertOk();
        $d['checkout_token']=session('checkout_token');
        $this->post('/checkout',$d)->assertRedirect('/order-success');
        $second=Order::latest('id')->first();
        $this->assertSame('mmc-'.($first->id+1),$second->number);
        $first->update(['number'=>'MMC-LEGACY-REFERENCE']);
        \Illuminate\Support\Facades\DB::table('inventory_movements')->where('reason','Order mmc-'.$first->id)->update(['reason'=>'Order MMC-LEGACY-REFERENCE']);
        $migration=require database_path('migrations/2026_09_16_010000_shorten_order_numbers.php');
        $migration->up();
        $this->assertSame('mmc-'.$first->id,$first->fresh()->number);
        $this->assertDatabaseHas('inventory_movements',['reason'=>'Order mmc-'.$first->id]);
    }

    public function test_default_charges_are_displayed_saved_and_do_not_change_old_orders():void
    {
        \App\Models\GeneralSetting::findOrFail(1)->update(['delivery_cents'=>550,'tax_basis_points'=>1000]);
        [$p,$d]=$this->prepareOrder();
        $this->get('/checkout')->assertOk()->assertSee('$5.50')->assertSee('10%')->assertSee('$2.14')->assertSee('$29.04');
        $d['checkout_token']=session('checkout_token');$d['delivery_cents']=0;$d['tax_cents']=0;
        $this->post('/checkout',$d)->assertRedirect('/order-success');
        $order=Order::firstOrFail();$this->assertEquals(2904,$order->final_total_cents);
        \App\Models\GeneralSetting::findOrFail(1)->update(['delivery_cents'=>900,'tax_basis_points'=>400]);
        $this->assertEquals(550,$order->fresh()->delivery_cents);$this->assertEquals(214,$order->fresh()->tax_cents);
        $this->postJson(route('cart.add',$p),['quantity'=>1])->assertOk();$this->get('/checkout')->assertOk();$d['checkout_token']=session('checkout_token');
        $this->post('/checkout',$d)->assertRedirect('/order-success');$next=Order::latest('id')->first();$this->assertEquals(900,$next->delivery_cents);$this->assertEquals(43,$next->tax_cents);
    }
    public function test_changed_defaults_require_customer_to_review_before_ordering():void
    {
        [$p,$d]=$this->prepareOrder();\App\Models\GeneralSetting::findOrFail(1)->update(['delivery_cents'=>100]);
        $this->post('/checkout',$d)->assertSessionHasErrors('cart');$this->assertDatabaseCount('orders',0);
    }
    public function test_settings_permissions_validation_and_stale_update():void
    {
        $this->get('/admin/settings/general')->assertRedirect('/admin/login');
        $role=Role::create(['name'=>'Settings Tester','permissions'=>[]]);$u=User::factory()->create(['role_id'=>$role->id,'is_active'=>true]);
        $this->actingAs($u)->get('/admin/settings/general')->assertForbidden();$this->put('/admin/settings/general',['delivery'=>'1','tax'=>'2','revision'=>0])->assertForbidden();
        $role->update(['permissions'=>['settings.manage']]);$u->unsetRelation('role');$this->actingAs($u)->get('/admin/settings/general')->assertOk()->assertSee('Default Order Charges');
        $this->put('/admin/settings/general',['delivery'=>'-1','tax'=>'1.234','revision'=>0])->assertSessionHasErrors(['delivery','tax']);
        $this->put('/admin/settings/general',['delivery'=>'5.50','tax'=>'2.25','revision'=>0])->assertRedirect('/admin/settings/general');
        $this->assertDatabaseHas('general_settings',['id'=>1,'delivery_cents'=>550,'tax_basis_points'=>225,'revision'=>1]);
        $this->put('/admin/settings/general',['delivery'=>'1','tax'=>'2','revision'=>0])->assertSessionHasErrors('settings');
    }

    public function test_percentage_tax_rounding_and_limits():void
    {
        $settings=\App\Models\GeneralSetting::findOrFail(1);
        $settings->update(['tax_basis_points'=>1300]);
        $this->assertSame(139,$settings->taxFor(1069));
        $settings->tax_basis_points=500;$this->assertSame(1,$settings->taxFor(10));
        $settings->tax_basis_points=0;$this->assertSame(0,$settings->taxFor(10000));
        $user=User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);
        $this->actingAs($user)->put('/admin/settings/general',['revision'=>0,'delivery'=>'0','tax'=>'100.01'])->assertSessionHasErrors('tax');
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
        $this->assertSame('mmc-'.$order->id, $order->number);
        $this->get('/admin/orders')->assertRedirect('/admin/login');
        $this->get('/admin/orders/'.$order->id.'/print')->assertRedirect('/admin/login');
        $role = Role::create(['name' => 'No Orders', 'permissions' => [], 'is_super' => false]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($user)->get('/admin/orders')->assertForbidden();
        $this->get('/admin/orders/'.$order->id)->assertForbidden();
        $this->get('/admin/orders/'.$order->id.'/print')->assertForbidden();
        $role->update(['permissions' => ['orders.view']]);
        $user->unsetRelation('role');
        $this->actingAs($user)->get('/admin/orders')->assertOk()->assertSee($order->number);
        $this->get('/admin/orders/'.$order->id)->assertOk()->assertSee('123 Main Street')->assertSee('Order Cake');
        $this->get('/admin/orders/'.$order->id.'/print')->assertOk()->assertSee('Order Confirmation')->assertSee('Order Cake');
    }
}
