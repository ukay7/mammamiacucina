<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GatewaySetting;
use App\Models\GeneralSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_requires_an_eligible_customer_account(): void
    {
        $this->get(route('customer.catalogue'))->assertRedirect(route('customer.login'));
        $staff = User::factory()->create(['role_id' => Role::where('is_super', true)->value('id'), 'is_active' => true]);
        $this->actingAs($staff)->get(route('customer.catalogue'))->assertForbidden();
        $this->post(route('customer.catalogue.cart'))->assertForbidden();
        $buyer = $this->buyer(['business_approved_at' => null]);
        $this->actingAs($buyer)->get(route('customer.catalogue'))->assertRedirect(route('customer.business.pending'));
        $this->postJson(route('customer.catalogue.cart'))->assertForbidden();
    }

    public function test_selected_products_use_website_checkout_and_the_customers_discounts(): void
    {
        $buyer = $this->buyer();
        $customer = $buyer->customerRecord();
        $customer->update(['order_discount_bps' => 1000]);
        $first = $this->product('Visible Cake');
        $second = $this->product('Visible Tart', ['total_selling_price_cad' => null, 'business_selling_price_cad' => 30]);
        $this->product('Hidden Cake', ['category_id' => $this->category('Hidden bakery', false)->id]);
        $this->product('Inactive Cake', ['is_active' => false]);
        $this->product('Stockless Cake')->inventory()->create(['quantity_on_hand' => 0]);
        DB::table('customer_product_discounts')->insert(['customer_id' => $customer->id, 'product_id' => $first->id, 'discount_bps' => 1500, 'enabled' => true]);
        GeneralSetting::findOrFail(1)->update(['pickup_address' => 'Test pickup address', 'tax_basis_points' => 0]);

        $page = $this->actingAs($buyer)->get(route('customer.catalogue'))->assertOk()
            ->assertSee('Visible Cake')->assertSee('Visible Tart')->assertSee('$17.00')->assertSee('15% off')
            ->assertSee('10% account discount')->assertSee('Out of stock')->assertDontSee('Hidden Cake')->assertDontSee('Inactive Cake');

        $review = route('customer.catalogue', ['review' => 1]);
        $this->post(route('customer.catalogue.cart'), ['catalogue_version' => $page->viewData('cartVersion'), 'selected' => [$first->id, $second->id], 'quantity' => [$first->id => 2, $second->id => 1]])
            ->assertRedirect($review)->assertSessionHas('storefront_cart', [$first->id => 2, $second->id => 1]);
        $this->get($review)->assertOk()->assertSee('Review & place order')->assertSee('Test pickup address')
            ->assertSee($buyer->email)->assertSee('Additional order discount')->assertSee('$57.60');

        $input = $this->checkoutInput($buyer);
        $this->from($review)->post(route('checkout.store'), array_replace($input, ['phone' => '']))
            ->assertRedirect($review)->assertSessionHasErrors('phone');
        $this->get($review)->assertOk()->assertSee('The phone field is required.');
        $input['checkout_token'] = session('checkout_token');
        $this->post(route('checkout.store'), $input)->assertRedirect(route('theme.order-success'));
        $order = Order::firstOrFail();
        $this->assertEquals(5760, $order->final_total_cents);
        $this->assertSame('pickup', $order->fulfillment);
        $this->assertEquals($customer->id, $order->customer_id);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $first->id, 'quantity' => 2, 'unit_cents' => 1700]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $second->id, 'quantity' => 1, 'unit_cents' => 3000]);
        $this->get(route('theme.order-success'))->assertOk()->assertSee(route('customer.order', $order));
        $this->get(route('customer.orders'))->assertOk()->assertSee($order->number)->assertSee($order->status_label);
    }

    public function test_product_details_use_the_signed_in_customers_cart_pricing(): void
    {
        $product = $this->product('Visible Cake');
        $url = route('catalogue.product', $product->slug);
        $this->get($url)->assertOk()->assertSee('$25.00')->assertDontSee('account discount');

        $individual = $this->buyer(['account_type' => 'individual']);
        $individual->customerRecord()->update(['order_discount_bps' => 500]);
        DB::table('customer_product_discounts')->insert([
            'customer_id' => $individual->customerRecord()->id, 'product_id' => $product->id,
            'discount_bps' => 1000, 'enabled' => true,
        ]);
        $this->actingAs($individual)->get($url)->assertOk()->assertSee('$22.50')->assertSee('10% off')
            ->assertSee('5% account discount')->assertHeader('Cache-Control', 'no-store, private');

        $this->actingAs($this->buyer())->get($url)->assertOk()->assertSee('$20.00')->assertDontSee('10% off')
            ->assertDontSee('account discount')->assertDontSee('$22.50');
    }

    public function test_invalid_bulk_selection_leaves_the_existing_cart_unchanged(): void
    {
        $first = $this->product('Visible Cake');
        $other = $this->product('Visible Tart');
        $other->inventory()->create(['quantity_on_hand' => 1]);
        $hidden = $this->product('Hidden Cake', ['category_id' => $this->category('Hidden bakery', false)->id]);
        $this->actingAs($this->buyer())->withSession(['storefront_cart' => [$first->id => 1]]);
        $version = ['catalogue_version' => $this->get(route('customer.catalogue'))->viewData('cartVersion')];

        $this->postJson(route('customer.catalogue.cart'), $version + ['selected' => [$first->id, $other->id], 'quantity' => [$first->id => 1, $other->id => 2]])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity')->assertSessionHas('storefront_cart', [$first->id => 1]);
        $this->postJson(route('customer.catalogue.cart'), $version + ['selected' => [$hidden->id], 'quantity' => [$hidden->id => 1]])
            ->assertUnprocessable()->assertJsonValidationErrors('selected')->assertSessionHas('storefront_cart', [$first->id => 1]);
        $this->postJson(route('customer.catalogue.cart'), $version + ['selected' => [$first->id], 'quantity' => [$first->id => '1.5']])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity.'.$first->id);
    }

    public function test_catalogue_updates_saved_quantities_and_unchecked_lines_without_adding_duplicates(): void
    {
        $first = $this->product('Visible Cake');
        $second = $this->product('Visible Tart');
        $this->actingAs($this->buyer())->withSession(['storefront_cart' => [$first->id => 5, $second->id => 2]]);
        $page = $this->get(route('customer.catalogue'))->assertOk();
        $this->assertSame([$first->id => 5, $second->id => 2], $page->viewData('basket'));

        $version = $page->viewData('cartVersion');
        $this->post(route('customer.catalogue.cart'), ['catalogue_version' => $version, 'selected' => [$first->id], 'quantity' => [$first->id => 3]])
            ->assertRedirect(route('customer.catalogue', ['review' => 1]))->assertSessionHas('storefront_cart', [$first->id => 3]);
        $this->postJson(route('customer.catalogue.cart'), ['catalogue_version' => $version])
            ->assertUnprocessable()->assertJsonValidationErrors('cart')->assertSessionHas('storefront_cart', [$first->id => 3]);

        $current = $this->get(route('customer.catalogue'))->viewData('cartVersion');
        $this->post(route('customer.catalogue.cart'), ['catalogue_version' => $current])
            ->assertRedirect(route('customer.catalogue'))->assertSessionHas('storefront_cart', []);
    }

    public function test_customer_can_remove_a_line_during_review_and_keeps_the_checkout_details(): void
    {
        $first = $this->product('Visible Cake');
        $second = $this->product('Visible Tart');
        $buyer = $this->buyer();
        $buyer->customerRecord()->update(['order_discount_bps' => 1000]);
        GeneralSetting::findOrFail(1)->update(['pickup_address' => 'Test pickup address', 'tax_basis_points' => 500]);
        $this->actingAs($buyer)->withSession(['storefront_cart' => [$first->id => 3, $second->id => 2]]);

        $review = route('customer.catalogue', ['review' => 1]);
        $page = $this->get($review)->assertOk()->assertSee('Remove Visible Tart from cart');
        $this->post(route('customer.catalogue.cart.remove', $second), [
            'cart_version' => $page->viewData('cartVersion'), 'fulfillment' => 'pickup', 'notes' => 'Collect at noon',
        ])->assertRedirect($review)->assertSessionHas('storefront_cart', [$first->id => 3])->assertSessionMissing('checkout_token');

        $page = $this->get($review)->assertOk()->assertSee('Collect at noon')->assertSee('$56.70')->assertDontSee('Remove Visible Tart from cart');
        $this->post(route('customer.catalogue.cart.remove', $first), ['cart_version' => $page->viewData('cartVersion')])
            ->assertRedirect(route('customer.catalogue'))->assertSessionHas('storefront_cart', []);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_catalogue_item_hidden_after_review_cannot_be_ordered_from_that_review(): void
    {
        $category = $this->category('Public bakery', true);
        $product = $this->product('Visible Cake', ['category_id' => $category->id]);
        $buyer = $this->buyer();
        GeneralSetting::findOrFail(1)->update(['pickup_address' => 'Test pickup address']);
        $this->actingAs($buyer)->withSession(['storefront_cart' => [$product->id => 1]])
            ->get(route('customer.catalogue', ['review' => 1]))->assertOk();
        $category->update(['show_to_customer' => false]);

        $this->post(route('checkout.store'), $this->checkoutInput($buyer))->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_gateway_credentials_encrypted_with_another_key_do_not_block_checkout(): void
    {
        GatewaySetting::forceCreate(['id' => 1, 'mode' => 'sandbox', 'helcim_enabled' => true]);
        $otherKey = new Encrypter(random_bytes(32), 'AES-256-CBC');
        DB::table('gateway_settings')->where('id', 1)->update(['credentials' => $otherKey->encryptString('{}')]);
        GeneralSetting::findOrFail(1)->update(['pickup_address' => 'Test pickup address']);
        $product = $this->product('Visible Cake');
        $buyer = $this->buyer();
        $this->actingAs($buyer)->withSession(['storefront_cart' => [$product->id => 1]]);

        $this->get(route('theme.checkout'))->assertOk()->assertSee('Pay by card (unavailable)');
        $this->get(route('customer.catalogue', ['review' => 1]))->assertOk()->assertSee('Pay by card (unavailable)')
            ->assertDontSee('Credit card minimum');
        $input = $this->checkoutInput($buyer);
        $this->post(route('checkout.store'), array_replace($input, ['payment_method' => 'card']))->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
        $this->post(route('checkout.store'), $input)->assertRedirect(route('theme.order-success'));
        $this->assertDatabaseHas('orders', ['customer_id' => $buyer->customerRecord()->id, 'payment_method' => 'cash']);
    }

    private function buyer(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'name' => 'Jane Doe', 'phone' => '4165550100', 'is_active' => true,
            'role_id' => Role::where('name', 'Customer')->value('id'), 'account_type' => 'business', 'business_approved_at' => now(),
        ]);
    }

    private function product(string $name, array $attributes = []): Product
    {
        return Product::create($attributes + [
            'category_id' => 1, 'slug' => str($name)->slug()->toString(), 'premium_marketing_name' => $name,
            'qr_code' => substr(sha1($name), 0, 8), 'is_active' => true, 'total_selling_price_cad' => 25, 'business_selling_price_cad' => 20,
        ]);
    }

    private function category(string $name, bool $visible): Category
    {
        return Category::create(['name' => $name, 'slug' => str($name)->slug()->toString(), 'is_active' => true, 'show_to_customer' => $visible]);
    }

    private function checkoutInput(User $buyer): array
    {
        return [
            'checkout_token' => session('checkout_token'), 'fulfillment' => 'pickup', 'payment_method' => 'cash',
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => $buyer->email, 'phone' => $buyer->phone,
        ];
    }
}
