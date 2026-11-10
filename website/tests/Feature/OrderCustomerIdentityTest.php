<?php

namespace Tests\Feature;

use App\Models\{Customer, Order, Role, User};
use App\Services\StaffPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class OrderCustomerIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('is_super', true)->value('id'), 'is_active' => true]);
    }

    private function customer(string $type = 'business', ?string $businessName = 'Sweet Boutique'): Customer
    {
        $customer = User::factory()->create(['role_id' => null, 'account_type' => $type, 'name' => 'Current profile contact'])->customerRecord();
        $customer->update(['business_name' => $businessName]);
        return $customer;
    }

    private function order(?Customer $customer): Order
    {
        $order = Order::create(['customer_id' => $customer?->id, 'checkout_token' => Str::uuid(), 'number' => 'MMC-IDENTITY-'.Str::random(6),
            'first_name' => 'Anthony', 'last_name' => 'Macri', 'email' => 'anthony@example.test', 'phone' => '4168443337',
            'address' => 'Saved street', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M5V', 'country' => 'Canada',
            'subtotal_cents' => 1000, 'delivery_cents' => 0, 'tax_cents' => 0, 'currency' => 'CAD', 'source' => 'website',
            'fulfillment' => 'pickup', 'payment_method' => 'cash', 'payment_status' => 'unpaid', 'status' => 'warehouse_pending']);
        $order->items()->create(['name' => 'Order cake', 'product_code' => 'CONTACT-1', 'quantity' => 1, 'unit_cents' => 1000, 'line_cents' => 1000]);
        return $order->fresh();
    }

    public function test_business_identity_is_shown_first_with_labeled_saved_contacts_and_profile_shortcut(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer();
        $order = $this->order($customer);
        $before = $order->getRawOriginal();
        $url = route('admin.customers.show', $customer);
        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSeeInOrder(['Business customer', 'Sweet Boutique', 'Customer:', 'Anthony Macri', 'Email:', 'anthony@example.test', 'Phone:', '4168443337'])
            ->assertSee('href="'.$url.'"', false)->assertSee('aria-label="Open customer profile for Sweet Boutique"', false);
        $this->assertSame($before, $order->fresh()->getRawOriginal());
        $this->get($url)->assertOk()->assertSee('Sweet Boutique');
    }

    public function test_individual_customer_uses_the_order_contact_name_and_ignores_business_fields(): void
    {
        $this->actingAs($this->admin());
        $order = $this->order($this->customer('individual', 'Old business name'));
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Individual customer')->assertSee('Anthony Macri')
            ->assertSee('Phone:')->assertSee('4168443337')->assertDontSee('Old business name')->assertDontSee('Business customer');
        $this->get(route('admin.orders.index'))->assertOk()->assertSee('Individual customer')->assertSee('Anthony Macri')
            ->assertDontSee('Old business name')->assertDontSee('Business customer');
    }

    public function test_guest_and_missing_business_name_have_clear_safe_fallbacks(): void
    {
        $this->actingAs($this->admin());
        $guest = $this->order(null);
        $guest->update(['phone' => '', 'email' => '']);
        $this->get(route('admin.orders.show', $guest))->assertOk()->assertSee('Guest customer')->assertSee('Anthony Macri')->assertSee('Not provided')
            ->assertDontSee('class="btn btn-outline-primary wh-order-customer-profile"', false);
        $business = $this->order($this->customer('business', null));
        $this->get(route('admin.orders.show', $business))->assertOk()->assertSee('Business customer')->assertSee('Anthony Macri')
            ->assertSee('aria-label="Open customer profile for Anthony Macri"', false);
        $this->get(route('admin.orders.index'))->assertOk()->assertSee('Guest customer')->assertSee('Email not provided')
            ->assertSee('Business customer')->assertSee('Anthony Macri');
    }

    public function test_order_view_permission_does_not_grant_a_customer_profile_shortcut(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);
        $role = Role::create(['name' => 'Order-only identity viewer', 'permissions' => StaffPermissions::normalize(['access.admin.orders.index'])]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'is_active' => true]));
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Business customer')->assertSee('Sweet Boutique')
            ->assertDontSee('class="btn btn-outline-primary wh-order-customer-profile"', false);
        $this->get(route('admin.customers.show', $customer))->assertForbidden();
    }

    public function test_business_identity_and_business_search_work_in_each_order_list_without_mixing_deleted_records(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer();
        $pending = $this->order($customer);
        $completed = $this->order($customer);
        $completed->updateQuietly(['status' => 'completed']);
        $deleted = $this->order($customer);
        $deleted->delete();
        $before = $deleted->getRawOriginal();

        foreach (['admin.orders.index' => $pending, 'admin.orders.completed' => $completed, 'admin.orders.deleted.index' => $deleted] as $route => $order) {
            $response = $this->get(route($route, ['q' => 'Sweet Boutique']))->assertOk()
                ->assertSee($order->number)->assertSeeInOrder(['Business customer', 'Sweet Boutique', 'Customer: Anthony Macri', 'anthony@example.test'])
                ->assertViewHas('orders', fn ($orders) => $orders->total() === 1 && $orders->first()->relationLoaded('customer'));
            foreach ([$pending, $completed, $deleted] as $other) {
                if ($other->id !== $order->id) $response->assertDontSee($other->number);
            }
            $this->get(route($route, ['q' => 'Unknown business']))->assertOk()->assertViewHas('orders', fn ($orders) => $orders->isEmpty());
        }

        $this->get(route('admin.orders.deleted.show', $deleted->id))->assertOk()->assertSee('Deleted · View only')
            ->assertSeeInOrder(['Business customer', 'Sweet Boutique', 'Customer: Anthony Macri', 'anthony@example.test', 'Phone: 4168443337']);
        $this->assertSame($before, Order::onlyTrashed()->findOrFail($deleted->id)->getRawOriginal());
    }

    public function test_warehouse_cards_show_business_identity_and_business_search_preserves_order_scope(): void
    {
        $customer = $this->customer();
        $visible = $this->order($customer);
        $private = $this->order($customer);
        $private->updateQuietly(['status' => 'parked_admin']);
        $worker = User::factory()->create(['role_id' => Role::where('name', 'Warehouse User')->value('id'), 'is_active' => true]);
        $this->actingAs($worker)->get(route('admin.orders.index', ['q' => 'Sweet Boutique']))->assertOk()
            ->assertSee('warehouse-card-customer')->assertSee('Business customer')->assertSee('Sweet Boutique')->assertSee('Customer: Anthony Macri')
            ->assertSee($visible->number)->assertDontSee($private->number)
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 1 && $orders->first()->relationLoaded('customer'));
        $this->get(route('admin.orders.deleted.index'))->assertForbidden();
    }

    public function test_order_exports_include_customer_type_and_business_name_for_both_lists(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer();
        $pending = $this->order($customer);
        $completed = $this->order($customer);
        $completed->updateQuietly(['status' => 'completed']);
        foreach (['pending' => $pending, 'completed' => $completed] as $view => $order) {
            foreach (['print', 'pdf'] as $format) {
                $this->get(route('admin.orders.export', ['format' => $format, 'view' => $view, 'q' => 'Sweet Boutique']))->assertOk()
                    ->assertSee('Customer type')->assertSee('Business name')->assertSee('Business customer')->assertSee('Sweet Boutique')->assertSee($order->number);
            }
            $response = $this->get(route('admin.orders.export', ['format' => 'xlsx', 'view' => $view, 'q' => 'Sweet Boutique']))->assertOk();
            $path = tempnam(sys_get_temp_dir(), 'order-identity-');
            $reader = new Reader;
            try {
                file_put_contents($path, $response->streamedContent());
                $reader->open($path);
                $rows = [];
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) $rows[] = $row->toArray();
                }
                $this->assertCount(2, $rows);
                $this->assertSame(['Customer type', 'Business name'], array_slice($rows[0], -2));
                $this->assertSame(['Business customer', 'Sweet Boutique'], array_slice($rows[1], -2));
                $this->assertSame($order->number, $rows[1][0]);
            } finally {
                $reader->close();
                unlink($path);
            }
        }
    }
}
