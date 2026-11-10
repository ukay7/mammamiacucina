<?php

namespace Tests\Feature;

use App\Models\{InventoryMovement, Order, OrderSettlement, Product, Role, StockCountTask, User, Warehouse};
use App\Services\{AdminDashboard, BusinessReports, CustomerInvoicing, CustomerReceipts, DocumentTheme};
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TorontoTimeTest extends TestCase
{
    use RefreshDatabase;

    private function login(): User
    {
        $actor = User::factory()->create(['role_id' => Role::where('is_super', true)->value('id'), 'is_active' => true]);
        $this->actingAs($actor);
        return $actor;
    }

    private function order(string $when, array $attributes = []): Order
    {
        return Order::create(array_merge(['checkout_token' => Str::uuid(), 'number' => 'TZ-'.Str::random(8),
            'first_name' => 'Toronto', 'last_name' => 'Customer', 'email' => 'timezone@example.test', 'phone' => '123',
            'address' => 'Street', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M5V', 'country' => 'Canada',
            'subtotal_cents' => 10000, 'delivery_cents' => 0, 'tax_cents' => 0, 'currency' => 'CAD',
            'payment_method' => 'cash', 'payment_status' => 'unpaid', 'status' => 'warehouse_pending',
            'ordered_at' => $when, 'created_at' => $when], $attributes))->fresh();
    }

    public function test_historical_instants_use_their_own_dst_offset_without_mutating_the_source(): void
    {
        $instant = Carbon::parse('2026-11-02 18:12:00', 'UTC');
        $this->assertSame('02 Nov 2026 13:12 EST', BusinessTime::format($instant));
        $this->assertSame('02 Jul 2026 14:12 EDT', BusinessTime::format('2026-07-02 18:12:00'));
        $this->assertSame('01 Nov 2026 01:30 EDT', BusinessTime::format('2026-11-01 05:30:00'));
        $this->assertSame('01 Nov 2026 01:30 EST', BusinessTime::format('2026-11-01 06:30:00'));
        $this->assertSame('2026-11-02 18:12:00 UTC', $instant->format('Y-m-d H:i:s T'));
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', date_default_timezone_get());
        $this->assertNull(BusinessTime::format(null));
        $this->assertSame('Monday, 02 Nov 2026 13:12 EST', app(DocumentTheme::class)->dateLabel('Monday, 02 Nov 2026 18:12 UTC'));
    }

    public function test_calendar_windows_cover_23_and_25_hour_days(): void
    {
        $this->assertSame('2026-03-08 05:00:00', BusinessTime::start('2026-03-08')->toDateTimeString());
        $this->assertSame('2026-03-09 04:00:00', BusinessTime::end('2026-03-08')->toDateTimeString());
        $this->assertSame('2026-11-01 04:00:00', BusinessTime::start('2026-11-01')->toDateTimeString());
        $this->assertSame('2026-11-02 05:00:00', BusinessTime::end('2026-11-01')->toDateTimeString());
        $this->travelTo(Carbon::parse('2026-11-03 02:00:00', 'UTC'));
        $this->assertSame('2026-11-02', BusinessTime::today()->toDateString());
        $this->assertSame('2026-11-03 02:00:00', now()->toDateTimeString());
    }

    public function test_datetime_inputs_convert_to_utc_and_reject_nonexistent_or_ambiguous_clock_times(): void
    {
        $this->assertSame('2026-07-02 18:12:00', BusinessTime::input('2026-07-02T14:12', 'ordered_at')->toDateTimeString());
        $this->assertSame('2026-11-02 18:12:00', BusinessTime::input('2026-11-02T13:12', 'ordered_at')->toDateTimeString());
        $this->assertNull(BusinessTime::input(null, 'starts_at'));
        foreach (['2026-03-08T02:30', '2026-11-01T01:30'] as $value) {
            try {
                BusinessTime::input($value, 'ordered_at');
                $this->fail('Unsafe local clock time was accepted: '.$value);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('ordered_at', $e->errors());
            }
        }
    }

    public function test_sales_collections_exports_and_dashboard_use_toronto_midnight(): void
    {
        $actor = $this->login();
        $this->travelTo(Carbon::parse('2026-11-03 03:00:00', 'UTC'));
        $outside = $this->order('2026-11-02 04:59:59');
        $inside = $this->order('2026-11-03 04:59:59');
        $tomorrow = $this->order('2026-11-03 05:00:00');
        foreach ([$outside, $inside, $tomorrow] as $order) {
            OrderSettlement::create(['order_id' => $order->id, 'kind' => 'payment', 'method' => 'cash', 'amount_cents' => 1000, 'note' => 'Timezone fixture', 'created_at' => $order->ordered_at]);
        }
        $filters = ['from' => '2026-11-02', 'to' => '2026-11-02'];
        foreach (['sales', 'payments'] as $kind) {
            $result = app(BusinessReports::class)->report($kind, $filters);
            $this->assertEquals(1, $result['rows']->count());
            $this->assertSame('2026-11-02', $result['trend']->first()->day);
        }
        $csv = $this->get('/admin/reports/export/sales?from=2026-11-02&to=2026-11-02')->assertOk()->streamedContent();
        $this->assertStringContainsString($inside->number, $csv);
        $this->assertStringContainsString('02 Nov 2026 23:59 EST', $csv);
        $this->assertStringNotContainsString($outside->number, $csv);
        $this->assertStringNotContainsString($tomorrow->number, $csv);
        $this->get('/admin/reports/payments?from=2026-11-02&to=2026-11-02')->assertOk()->assertSee($inside->number)->assertDontSee($outside->number);
        // A genuine already-placed order, unlike the future boundary fixture, appears in today's dashboard.
        $inside->update(['ordered_at' => '2026-11-03 02:59:59']);
        $data = app(AdminDashboard::class)->build($actor, 1);
        $this->assertSame(1, $data['sales']['orders']);
        $this->assertSame(1, $data['sales']['trend']->first()['orders']);
        $this->assertSame('2026-11-02', $data['start']->toDateString());
    }

    public function test_sql_buckets_follow_both_dst_transitions_without_mysql_timezone_tables(): void
    {
        foreach ([['2026-03-08', ['2026-03-08 04:59:59', '2026-03-08 05:00:00', '2026-03-09 03:59:59', '2026-03-09 04:00:00']],
            ['2026-11-01', ['2026-11-01 03:59:59', '2026-11-01 04:00:00', '2026-11-02 04:59:59', '2026-11-02 05:00:00']]] as [$date, $instants]) {
            foreach ($instants as $when) $this->order($when);
            $result = app(BusinessReports::class)->report('sales', ['from' => $date, 'to' => $date]);
            $this->assertEquals(2, $result['summary']['orders']);
            $this->assertEquals(20000, $result['trend']->first()->amount);
        }
    }

    public function test_editing_order_date_preserves_audit_timestamp_and_date_only_cheque_terms(): void
    {
        $this->login();
        $this->travelTo(Carbon::parse('2026-11-05 12:00:00', 'UTC'));
        $order = $this->order('2026-11-02 18:12:00', ['payment_method' => 'cheque', 'cheque_days' => 2]);
        $created = $order->getRawOriginal('created_at');
        $this->patch(route('admin.orders.order-date', $order), ['revision' => 0, 'ordered_at' => '2026-11-02T22:30'])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('2026-11-03 03:30:00', $order->getRawOriginal('ordered_at'));
        $this->assertSame($created, $order->getRawOriginal('created_at'));
        $this->assertSame('2026-11-04', $order->cheque_due_date);
        $this->assertStringContainsString('22:30 EST', $order->events()->latest('id')->value('description'));
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('2026-11-02T22:30', false);
        $this->patchJson(route('admin.orders.order-date', $order), ['revision' => 1, 'ordered_at' => '2026-11-05T08:00'])->assertUnprocessable();
    }

    public function test_reprinting_historical_documents_preserves_snapshots_and_stored_instants(): void
    {
        $order = $this->order('2026-11-02 18:12:00', ['status' => 'completed']);
        $order->items()->create(['name' => 'Saved historical cake', 'product_code' => 'TZ-CAKE', 'qr_code' => 'TZ-100', 'quantity' => 1, 'unit_cents' => 10000, 'line_cents' => 10000]);
        $invoice = app(CustomerInvoicing::class)->issue($order);
        $invoice->update(['issued_at' => '2026-07-02 18:12:00']);
        $entry = OrderSettlement::create(['order_id' => $order->id, 'kind' => 'payment', 'method' => 'cash', 'amount_cents' => 1000, 'note' => 'Historical receipt', 'created_at' => '2026-11-02 18:12:00']);
        $entry->refresh();
        $before = [$order->getRawOriginal(), $invoice->fresh()->getRawOriginal(), $entry->getRawOriginal()];
        $theme = app(DocumentTheme::class);
        $this->assertSame('02 Nov 2026 13:12 EST', $theme->order($order)['date']);
        $this->assertSame('02 Jul 2026 14:12 EDT', $theme->order($order, $invoice)['date']);
        $this->assertSame('02 Nov 2026 13:12 EST', $theme->order($order, receipt: $entry)['date']);
        $this->assertSame($before, [$order->fresh()->getRawOriginal(), $invoice->fresh()->getRawOriginal(), $entry->fresh()->getRawOriginal()]);
    }

    public function test_inventory_day_totals_and_movement_csv_share_the_same_toronto_window(): void
    {
        $this->login();
        $this->travelTo(Carbon::parse('2026-11-03 02:00:00', 'UTC'));
        $product = Product::create(['category_id' => 1, 'slug' => 'timezone-cake', 'premium_marketing_name' => 'Timezone cake', 'product_code' => 'TZ-1']);
        foreach (['2026-11-02 04:59:59', '2026-11-02 05:00:00', '2026-11-03 04:59:59', '2026-11-03 05:00:00'] as $i => $when) {
            InventoryMovement::create(['product_id' => $product->id, 'quantity_before' => $i, 'quantity_change' => 1,
                'quantity_after' => $i + 1, 'reason' => 'Timezone movement '.$i, 'created_at' => $when]);
        }
        $page = $this->get(route('admin.reports.inventory', ['from' => '2026-11-02', 'to' => '2026-11-02']))->assertOk();
        $this->assertSame(2, $page->viewData('metrics')['movements_period']);
        $this->assertEquals(2, $page->viewData('trend')->first()->added);
        $csv = $this->get(route('admin.reports.inventory.export', ['report' => 'movements', 'from' => '2026-11-02', 'to' => '2026-11-02']))->assertOk()->streamedContent();
        $this->assertStringContainsString('2026-11-02 23:59:59 EST', $csv);
        $this->assertStringNotContainsString('Timezone movement 0', $csv);
        $this->assertStringNotContainsString('Timezone movement 3', $csv);
    }

    public function test_stock_count_schedule_is_entered_in_toronto_and_stored_in_utc(): void
    {
        $this->login();
        $worker = User::factory()->create(['role_id' => Role::where('name', Role::WAREHOUSE_USER)->value('id'), 'is_active' => true]);
        $product = Product::create(['category_id' => 1, 'slug' => 'timezone-count', 'premium_marketing_name' => 'Count cake', 'is_active' => true]);
        $this->post(route('admin.stock-counts.store'), ['scope' => 'specific', 'warehouse_id' => Warehouse::where('is_default', true)->value('id'),
            'assigned_to' => $worker->id, 'product_ids' => [$product->id], 'starts_at' => '2026-11-02T13:12', 'due_at' => '2026-11-03T13:12'])->assertSessionHasNoErrors();
        $task = StockCountTask::latest('id')->firstOrFail();
        $this->assertSame('2026-11-02 18:12:00', $task->getRawOriginal('starts_at'));
        $this->assertSame('2026-11-03 18:12:00', $task->getRawOriginal('due_at'));
        $this->get(route('admin.stock-counts.show', $task))->assertOk()->assertSee('02 Nov 2026 13:12 EST');
    }

    public function test_payment_date_validation_uses_today_in_toronto_and_keeps_calendar_dates_unshifted(): void
    {
        $actor = $this->login();
        $this->travelTo(Carbon::parse('2026-11-03 02:00:00', 'UTC'));
        $customer = User::factory()->create(['role_id' => null, 'account_type' => 'individual'])->customerRecord();
        $this->order('2026-11-01 12:00:00', ['customer_id' => $customer->id]);
        $line = ['key' => (string) Str::uuid(), 'method' => 'cash', 'amount' => '10.00', 'payment_date' => '2026-11-03', 'verified' => true];
        $service = app(CustomerReceipts::class);
        try {
            $service->preview($customer, ['lines' => [$line]], $actor);
            $this->fail('Tomorrow in Toronto must not be accepted as a receipt date.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines.0.payment_date', $e->errors());
        }
        $line['payment_date'] = '2026-11-02';
        $plan = $service->preview($customer, ['lines' => [$line]], $actor);
        $this->assertNotEmpty($plan['preview_hash']);
        $this->assertSame('2026-11-02', $line['payment_date']);
    }
}
