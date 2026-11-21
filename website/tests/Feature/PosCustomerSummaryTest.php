<?php

namespace Tests\Feature;

use App\Models\{Customer, Order, Role, User};
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosCustomerSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_uses_current_balances_and_the_previous_toronto_month(): void
    {
        $this->travelTo(BusinessTime::start('2026-11-15'));
        $admin = User::factory()->create(['role_id' => Role::where('is_super', true)->value('id'), 'is_active' => true]);
        $buyer = User::factory()->create(['account_type' => 'individual', 'is_active' => true])->customerRecord();
        $this->actingAs($admin);
        $this->assertSame('standard', $buyer->fresh()->tier);
        $this->putJson(route('admin.customers.update', $buyer), ['name' => $buyer->name, 'tier' => 'invalid'])->assertJsonValidationErrors('tier');
        $this->put(route('admin.customers.update', $buyer), ['name' => $buyer->name, 'tier' => 'gold'])->assertSessionHasNoErrors();
        $buyer->update(['credit_limit_cents' => 50000]);

        $partial = $this->order($buyer, 'October partial', '2026-10-31', ['payment_method' => 'credit']);
        $partial->settlements()->create(['user_id' => $admin->id, 'method' => 'cash', 'amount_cents' => 2000, 'note' => 'Partial payment']);
        $this->order($buyer, 'This month', '2026-11-01');
        $this->order($buyer, 'Too early', '2026-09-30');
        $this->order($buyer, 'Paid in full', '2026-10-15', ['payment_status' => 'paid']);
        $this->order($buyer, 'Cancelled', '2026-10-15', ['status' => 'cancelled']);

        $url = route('admin.pos.customer-summary', ['customer_id' => $buyer->id]);
        $this->getJson($url)->assertOk()->assertJsonPath('tier_label', 'Gold')
            ->assertJsonPath('profile_url', route('admin.customers.show', $buyer))
            ->assertJsonPath('available_credit_cents', 42000)
            ->assertJsonPath('last_order.number', 'This month')->assertJsonMissingPath('recent_orders')
            ->assertJsonPath('last_month.label', 'October 2026')->assertJsonPath('last_month.amount_cents', 8000)
            ->assertJsonPath('last_month.orders.0.number', 'October partial')->assertJsonCount(1, 'last_month.orders');

        $role = Role::create(['name' => 'Sales counter', 'permissions' => ['pos.manage']]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'is_active' => true]));
        $this->getJson($url)->assertOk()->assertJsonPath('profile_url', null)->assertJsonPath('last_order', null)
            ->assertJsonPath('last_month.amount_cents', 8000)->assertJsonPath('last_month.orders', []);
    }

    private function order(Customer $buyer, string $number, string $date, array $extra = []): Order
    {
        return Order::create($extra + [
            'customer_id' => $buyer->id, 'checkout_token' => (string) Str::uuid(), 'number' => $number,
            'first_name' => 'Buyer', 'last_name' => 'Test', 'email' => $buyer->email, 'phone' => '',
            'address' => '', 'city' => '', 'province' => '', 'postal_code' => '', 'country' => 'Canada',
            'status' => 'delivered', 'subtotal_cents' => 10000, 'delivery_cents' => 0, 'tax_cents' => 0,
            'ordered_at' => BusinessTime::start($date), 'payment_status' => 'unpaid',
        ]);
    }
}
