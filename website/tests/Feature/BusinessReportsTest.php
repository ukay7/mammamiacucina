<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderSettlement;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\BusinessReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessReportsTest extends TestCase
{
    use RefreshDatabase;

    private function login(): User
    {
        $user = User::factory()->create(['role_id' => Role::where('is_super', true)->value('id'), 'is_active' => true]);
        $this->actingAs($user);

        return $user;
    }

    private function order(array $extra = []): Order
    {
        return Order::create(array_merge(['checkout_token' => (string) Str::uuid(), 'number' => 'R-'.Str::random(8), 'first_name' => 'Report', 'last_name' => 'Customer', 'email' => 'report@example.test', 'phone' => '123', 'address' => 'Street', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M4S', 'country' => 'Canada', 'subtotal_cents' => 10000, 'delivery_cents' => 0, 'tax_cents' => 0, 'currency' => 'CAD', 'payment_method' => 'cash', 'payment_status' => 'unpaid', 'status' => 'warehouse_pending'], $extra));
    }

    private function gateway(Order $order, array $extra = []): Payment
    {
        return Payment::create(array_merge(['order_id' => $order->id, 'reference' => (string) Str::uuid(), 'provider' => 'helcim', 'mode' => 'live', 'status' => 'paid', 'amount_cents' => 10000, 'refunded_cents' => 0, 'currency' => 'CAD', 'expires_at' => now()->addHour(), 'paid_at' => now()], $extra));
    }

    public function test_collections_use_transaction_dates_actual_methods_and_exclude_test_payments(): void
    {
        $this->login();
        $this->travelTo(now()->setDate(2026, 10, 11)->startOfDay()->addHours(12));
        $old = $this->order(['created_at' => now()->subDays(10), 'payment_method' => 'etransfer', 'manual_collected_cents' => 0]);
        OrderSettlement::create(['order_id' => $old->id, 'amount_cents' => 4000, 'method' => 'cash', 'note' => 'Verified payment']);
        OrderSettlement::create(['order_id' => $old->id, 'amount_cents' => -1000, 'method' => 'etransfer', 'note' => 'Actual refund']);
        $online = $this->order(['payment_method' => 'card']);
        $payment = $this->gateway($online, ['refunded_cents' => 2000]);
        DB::table('payment_refunds')->insert(['payment_id' => $payment->id, 'provider_refund_id' => 'refund-1', 'amount_cents' => 2000, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        $this->gateway($this->order(['number' => 'SANDBOX-HIDDEN']), ['mode' => 'sandbox']);
        $this->order(['number' => 'UNDATED', 'payment_status' => 'paid', 'manual_collected_cents' => 3000, 'paid_at' => null]);
        $this->order(['number' => 'UPLOAD-ONLY', 'payment_method' => 'etransfer', 'status' => 'transfer_pending', 'transfer_receipt_path' => 'receipt.png']);
        $service = app(BusinessReports::class);
        $f = ['from' => today()->toDateString(), 'to' => today()->toDateString()];
        $r = $service->report('payments', $f);
        $this->assertEquals(14000, $r['summary']['received']);
        $this->assertEquals(3000, $r['summary']['refunded']);
        $this->assertEquals(11000, $r['summary']['net']);
        $this->assertEquals(1, $r['undated']);
        $this->assertEquals(4000, $service->report('payments', $f + ['method' => 'cash'])['summary']['received']);
        $this->assertEquals(0, $service->report('sales', $f)['rows']->where('id', $old->id)->count());
        $this->get('/admin/reports/view/payments?from=2026-10-11&to=2026-10-11')->assertOk()->assertSee($old->number)->assertDontSee('SANDBOX-HIDDEN')->assertDontSee('UPLOAD-ONLY')->assertSee('historic payment/refund');
        foreach (array_keys(BusinessReports::TYPES) as $kind) {
            $this->get('/admin/reports/view/'.$kind)->assertOk();
            $this->assertNotEmpty($this->get('/admin/reports/export/'.$kind)->assertOk()->streamedContent());
        }
        $this->get('/admin/reports')->assertOk()->assertSee('Received today');
        $this->get('/admin/reports/order/'.$old->id)->assertOk()->assertSee('$70.00')->assertSee('Payments & receipts', false);
    }

    public function test_balances_handle_cancellation_refund_due_and_unconfirmed_totals(): void
    {
        $this->login();
        $cancelled = $this->order(['status' => 'cancelled', 'manual_collected_cents' => 5000, 'manual_refunded_cents' => 1000]);
        $reduced = $this->order(['subtotal_cents' => 5000, 'manual_collected_cents' => 10000]);
        $unknown = $this->order(['delivery_cents' => null, 'tax_cents' => null]);
        $this->order(['currency' => 'USD', 'number' => 'USD-HIDDEN']);
        $f = ['from' => today()->toDateString(), 'to' => today()->toDateString()];
        $r = app(BusinessReports::class)->report('balances', $f + ['balance' => 'refund']);
        $this->assertEquals(9000, $r['summary']['refund_due']);
        $this->assertEquals(2, $r['summary']['orders']);
        $this->get('/admin/reports/view/sales')->assertOk()->assertSee('unconfirmed totals')->assertDontSee('USD-HIDDEN');
        $this->get('/admin/reports/view/balances?balance=refund')->assertOk()->assertSee($cancelled->number)->assertSee($reduced->number)->assertDontSee($unknown->number);
    }

    public function test_planner_requires_order_permission_and_revision_and_does_not_change_payment(): void
    {
        $this->login();
        $order = $this->order();
        $this->get('/admin/reports/view/fulfilment?schedule=unscheduled')->assertOk()->assertSee($order->number);
        $this->post('/admin/reports/order/'.$order->id.'/schedule', ['revision' => 0, 'fulfillment_date' => today()->addDay()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame('warehouse_pending', $order->fresh()->status);
        $this->assertEquals(1, $order->fresh()->revision);
        $this->assertEquals(1, $order->events()->count());
        $this->post('/admin/reports/order/'.$order->id.'/schedule', ['revision' => 0, 'fulfillment_date' => today()->toDateString()])->assertSessionHasErrors('fulfillment_date');
        $this->get('/admin/reports/view/fulfilment?from='.today()->addDay()->toDateString().'&to='.today()->addDay()->toDateString())->assertOk()->assertSee($order->number);
        $viewer = User::factory()->create(['is_active' => true, 'role_id' => Role::create(['name' => 'Report reader', 'permissions' => ['orders.view']])->id]);
        $this->actingAs($viewer)->get('/admin/reports/order/'.$order->id)->assertOk()->assertDontSee('Save planned date');
        $this->post('/admin/reports/order/'.$order->id.'/schedule', ['revision' => 1])->assertForbidden();
        $warehouse = User::factory()->create(['is_active' => true, 'role_id' => Role::create(['name' => 'Restricted warehouse reports', 'permissions' => \App\Services\StaffPermissions::normalize(['access.admin.orders.index', 'warehouse.pack'])])->id]);
        $this->actingAs($warehouse)->get('/admin/reports')->assertForbidden();
        $this->get('/admin/reports/order/'.$order->id)->assertForbidden();
        $this->get('/admin/reports/export/sales')->assertForbidden();
    }

    public function test_customer_grouping_pagination_and_safe_exports(): void
    {
        $this->login();
        $a = User::factory()->create(['account_type' => 'individual', 'role_id' => null])->customerRecord();
        $b = User::factory()->create(['account_type' => 'individual', 'role_id' => null])->customerRecord();
        $this->order(['customer_id' => $a->id, 'email' => 'same@example.test', 'first_name' => '=FORMULA']);
        $this->order(['customer_id' => $b->id, 'email' => 'same@example.test']);
        $this->order(['email' => 'guest@example.test']);
        $this->order(['email' => 'GUEST@example.test']);
        $response = $this->get('/admin/reports/view/customers')->assertOk();
        $this->assertEquals(3, $response->viewData('rows')->total());
        $this->assertEquals(1, $this->get('/admin/reports/view/sales?customer=id:'.$a->id)->assertOk()->viewData('rows')->total());
        $this->assertEquals(2, $this->get('/admin/reports/view/sales?customer=email:guest@example.test')->assertOk()->viewData('rows')->total());
        $csv = $this->get('/admin/reports/export/sales')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=FORMULA", $csv);
        $this->getJson('/admin/reports/view/sales?from=2026-10-20&to=2026-10-01')->assertUnprocessable();
        $this->getJson('/admin/reports/view/sales?from=2020-01-01&to=2026-10-01')->assertUnprocessable();
        $this->get('/admin/reports/view/unknown')->assertNotFound();
    }
}
