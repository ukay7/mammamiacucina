<?php

namespace Tests\Feature;

use App\Models\{Order, Product, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderAmendmentPricingTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $type, ?string $businessPrice = '12.00'): array
    {
        $user = User::factory()->create(['account_type' => $type, 'is_active' => true, 'business_approved_at' => $type === 'business' ? now() : null]);
        $customer = $user->customerRecord();
        $product = Product::create(['category_id' => 1, 'slug' => Str::uuid(), 'premium_marketing_name' => 'Added cake',
            'qr_code' => Str::uuid(), 'is_active' => true, 'total_selling_price_cad' => '20.00', 'business_selling_price_cad' => $businessPrice]);
        $order = Order::create(['customer_id' => $customer->id, 'checkout_token' => Str::uuid(), 'number' => 'TEST-'.Str::uuid(),
            'first_name' => 'Buyer', 'last_name' => 'Test', 'email' => $user->email, 'phone' => '123', 'address' => '', 'city' => '',
            'province' => '', 'postal_code' => '', 'country' => '', 'subtotal_cents' => 700, 'delivery_cents' => 0, 'tax_cents' => 0,
            'payment_method' => 'cash', 'payment_status' => 'unpaid', 'status' => 'parked_admin', 'source' => 'website', 'fulfillment' => 'pickup']);
        $order->items()->create(['name' => 'Historical item', 'quantity' => 1, 'unit_cents' => 700, 'line_cents' => 700]);
        $this->actingAs(User::factory()->create(['is_active' => true, 'role_id' => Role::where('is_super', true)->value('id')]));

        return [$order->fresh(), $product, $customer];
    }

    private function add(Order $order, Product $product, ?string $price = null)
    {
        return $this->post(route('admin.orders.amend', $order), ['revision' => $order->revision,
            'items' => [$order->items()->first()->id => ['quantity' => 1, 'unit_price' => '7.00']],
            'add_items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => $price]],
            'fulfillment' => 'pickup', 'delivery' => '0', 'tax' => '0', 'tax_mode' => 'manual', 'reason' => 'Add requested product', 'first_name' => 'Buyer']);
    }

    public function test_picker_and_server_default_follow_the_order_customer_not_the_staff_account(): void
    {
        foreach (['business' => 1200, 'individual' => 2000] as $type => $expected) {
            [$order, $product] = $this->fixture($type);
            $this->get(route('admin.orders.show', $order))->assertOk()
                ->assertSee('value="'.$product->id.'" data-price="'.number_format($expected / 100, 2, '.', '').'"', false);
            $this->add($order, $product)->assertSessionHasNoErrors();
            $line = $order->items()->where('product_id', $product->id)->sole();
            $this->assertSame($expected, $line->unit_cents);
            $this->assertSame($expected, $line->original_unit_cents);
            $this->assertSame(0, $line->discount_bps);
            $this->assertSame(700, $order->items()->whereNull('product_id')->sole()->unit_cents);
            $this->assertSame(700 + $expected * 2, $order->fresh()->subtotal_cents);
        }
    }

    public function test_customer_product_discount_is_applied_once_and_saved_with_its_base(): void
    {
        [$order, $product, $customer] = $this->fixture('business');
        DB::table('customer_product_discounts')->insert(['customer_id' => $customer->id, 'product_id' => $product->id, 'discount_bps' => 1000, 'enabled' => true]);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('value="'.$product->id.'" data-price="12.00"', false)
            ->assertSee('data-discount="10"', false);
        $this->add($order, $product, '10.80')->assertSessionHasNoErrors();
        $line = $order->items()->where('product_id', $product->id)->sole();
        $this->assertSame(1080, $line->unit_cents);
        $this->assertSame(1200, $line->original_unit_cents);
        $this->assertSame(1000, $line->discount_bps);
        $this->assertSame(2160, $line->line_cents);
    }

    public function test_manual_override_remains_available_without_inventing_a_discount(): void
    {
        [$order, $product, $customer] = $this->fixture('business');
        DB::table('customer_product_discounts')->insert(['customer_id' => $customer->id, 'product_id' => $product->id, 'discount_bps' => 1000, 'enabled' => true]);
        $this->add($order, $product, '9.50')->assertSessionHasNoErrors();
        $line = $order->items()->where('product_id', $product->id)->sole();
        $this->assertSame(950, $line->unit_cents);
        $this->assertSame(950, $line->original_unit_cents);
        $this->assertSame(0, $line->discount_bps);
    }

    public function test_missing_business_price_falls_back_and_explicit_zero_remains_zero(): void
    {
        foreach ([null => 2000, '0.00' => 0] as $price => $expected) {
            [$order, $product] = $this->fixture('business', $price === '' ? null : $price);
            $this->get(route('admin.orders.show', $order))->assertOk()
                ->assertSee('value="'.$product->id.'" data-price="'.number_format($expected / 100, 2, '.', '').'"', false);
            $this->add($order, $product)->assertSessionHasNoErrors();
            $this->assertSame($expected, $order->items()->where('product_id', $product->id)->sole()->unit_cents);
        }
    }

    public function test_editor_shows_existing_photo_and_saved_discount_and_saves_line_discounts(): void
    {
        [$order, $added] = $this->fixture('business');
        $order->update(['source' => 'pos', 'order_discount_bps' => 500]);
        $product = $added->replicate();
        $product->fill(['slug' => Str::uuid(), 'qr_code' => Str::uuid(), 'premium_marketing_name' => 'Existing cake'])->save();
        $product->media()->create(['kind' => 'image', 'path' => 'test/photo.png', 'original_name' => 'photo.png', 'mime_type' => 'image/png', 'sort_order' => 0]);
        $line = $order->items()->sole();
        $line->update(['product_id' => $product->id, 'name' => 'Existing cake', 'quantity' => 2,
            'original_unit_cents' => 2000, 'discount_bps' => 1500, 'unit_cents' => 1700, 'line_cents' => 3400]);

        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee(route('admin.orders.item-image', [$order, $line]))
            ->assertSee('name="items['.$line->id.'][discount_percent]" value="15.00"', false)
            ->assertSee('Order discount (5%)');

        $data = ['revision' => $order->revision, 'items' => [$line->id => ['quantity' => 2, 'unit_price' => '20.00', 'discount_percent' => '12.50']],
            'add_items' => [['product_id' => $added->id, 'quantity' => 2, 'unit_price' => '12.00', 'discount_percent' => '25']],
            'fulfillment' => 'pickup', 'delivery' => '0', 'tax' => '0', 'tax_mode' => 'manual', 'reason' => 'Adjust discounts', 'first_name' => 'Buyer'];
        $invalid = $data;
        $invalid['items'][$line->id]['discount_percent'] = '100.01';
        $this->post(route('admin.orders.amend', $order), $invalid)->assertSessionHasErrors('items.'.$line->id.'.discount_percent');
        $this->assertSame(1500, $line->fresh()->discount_bps);

        $this->post(route('admin.orders.amend', $order), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order_items', ['id' => $line->id, 'original_unit_cents' => 2000, 'discount_bps' => 1250, 'unit_cents' => 1750, 'line_cents' => 3500]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $added->id,
            'original_unit_cents' => 1200, 'discount_bps' => 2500, 'unit_cents' => 900, 'line_cents' => 1800]);
        $this->assertSame(5035, $order->fresh()->subtotal_cents);
    }
}
