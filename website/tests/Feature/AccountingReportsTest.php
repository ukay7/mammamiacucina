<?php

namespace Tests\Feature;

use App\Models\{Order, Role, Supplier, User};
use App\Services\{AccountingLedger, AccountingReports, CustomerInvoicing, PaymentReconciliation};
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_role_can_read_and_export_all_reports_without_mutating_business_records(): void
    {
        $accountant = $this->accountant();
        $order = $this->order();
        $order->items()->create(['name' => 'Test item', 'quantity' => 1, 'unit_cents' => 10000, 'line_cents' => 10000]);
        $invoice = app(CustomerInvoicing::class)->issue($order);
        $this->actingAs($accountant)->get(route('admin.accounting.index'))->assertOk()->assertSee('Profit and Loss')->assertDontSee('Post balanced journals');
        foreach (array_keys(AccountingReports::REPORTS) as $kind) {
            $this->get(route('admin.accounting.report', $kind))->assertOk();
            $this->get(route('admin.accounting.report', [$kind, 'format' => 'csv']))->assertDownload($kind.'.csv');
        }
        $this->get(route('admin.accounting.report', 'documents'))->assertSee($invoice->label)->assertSee('Download PDF');
        $this->get(route('admin.accounting.document', [$order->customer_id, 'invoice', $invoice->id, 'download' => 1]))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('admin.accounting.document', [$order->customer_id, 'account-proof', $invoice->id]))->assertNotFound();
        $this->get(route('admin.accounting.report', ['profit-loss', 'format' => 'print']))->assertOk()->assertSee('Print / Save PDF');
        $this->get(route('admin.orders.show', $order))->assertForbidden();
        $this->patch(route('admin.orders.update', $order))->assertForbidden();
        $this->put(route('admin.customers.update', $order->customer_id))->assertForbidden();
        $this->get(route('admin.accounting.template'))->assertForbidden();
        $this->post(route('admin.accounting.import'))->assertForbidden();
    }

    public function test_balanced_ledger_import_populates_statements_and_ages_partial_supplier_payments(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'role_id' => Role::where('is_super', true)->value('id')]);
        $supplier = Supplier::create(['name' => 'Test Supplier', 'name_key' => hash('sha256', 'test supplier')]);
        $data = [
            ['open', '2026-01-01', 'cash', 'Bank', '1000.00', 'opening'],
            ['open', '2026-01-01', 'equity', 'Capital', '-1000.00'],
            ['sale', '2026-01-05', 'cash', 'Bank', '275', 'operating'],
            ['sale', '2026-01-05', 'revenue', 'Sales', '-250'],
            ['sale', '2026-01-05', 'gst_collected', 'GST/HST', '-25'],
            ['bill', '2026-01-06', 'expense', 'Rent', '100', '', $supplier->id, 'BILL-17'],
            ['bill', '2026-01-06', 'gst_itc', 'Recoverable tax', '10', '', $supplier->id, 'BILL-17'],
            ['bill', '2026-01-06', 'payable', 'Vendor control', '-110', '', $supplier->id, 'BILL-17', '2026-01-15'],
            ['pay', '2026-01-19', 'payable', 'Vendor control', '40', '', $supplier->id, 'BILL-17'],
            ['pay', '2026-01-19', 'cash', 'Bank', '-40', 'operating'],
            ['cost', '2026-01-22', 'cogs', 'Inventory used', '30'],
            ['cost', '2026-01-22', 'asset', 'Inventory', '-30'],
        ];
        $this->actingAs($admin)->post(route('admin.accounting.import'), ['ledger' => $this->csv($data)])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('accounting_journals', 5);
        $this->post(route('admin.accounting.import'), ['ledger' => $this->csv($data)])->assertSessionHasErrors('ledger');
        $this->post(route('admin.accounting.import'), ['ledger' => $this->csv([['bad', '2026-01-06', 'expense', 'Rent', '25']])])
            ->assertSessionHasErrors('ledger');
        $this->assertDatabaseCount('accounting_journals', 5);
        $this->actingAs($this->accountant());
        $dates = ['from' => '2026-01-01', 'to' => '2026-03-01'];
        $this->get(route('admin.accounting.report', ['profit-loss', ...$dates]))->assertOk()->assertSee('120.00');
        $this->get(route('admin.accounting.report', ['balance-sheet', ...$dates]))->assertOk()->assertSee('1215.00');
        $this->get(route('admin.accounting.report', ['cash-flow', ...$dates]))->assertOk()->assertSee('1235.00');
        $payables = $this->get(route('admin.accounting.report', ['payables', ...$dates, 'supplier' => $supplier->id]));
        $payables->assertOk()->assertSee('BILL-17')->assertSee('31–60 Days');
        $this->assertSame('70.00', $payables->viewData('paginator')->items()[0][5]);
        $this->get(route('admin.accounting.report', ['tax', ...$dates]))->assertOk()->assertSee('Approved input tax credits')->assertSee('25.00');
        $this->get(route('admin.accounting.report', ['expenses', ...$dates]))->assertOk()->assertSee('Rent')->assertSee('100.00');
    }

    public function test_weekly_review_groups_split_receipt_uses_local_dates_and_keeps_original_payments(): void
    {
        $week = BusinessTime::today()->subWeek()->startOfWeek()->toDateString();
        $order = $this->order();
        $other = $this->order($order->customer_id);
        $receipt = DB::table('customer_receipts')->insertGetId([
            'number' => 'RCPT-SPLIT', 'customer_id' => $order->customer_id, 'customer_snapshot' => '{}', 'total_cents' => 13000,
            'request_key' => (string) Str::uuid(), 'request_hash' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $line = DB::table('customer_receipt_lines')->insertGetId([
            'customer_receipt_id' => $receipt, 'customer_id' => $order->customer_id, 'client_key' => (string) Str::uuid(), 'method' => 'etransfer',
            'amount_cents' => 13000, 'payment_date' => $week, 'reference' => 'BANK-SPLIT', 'status' => 'confirmed', 'planned_allocations' => '[]',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[$order, 6000], [$other, 7000]] as [$allocated, $amount]) {
            $allocated->settlements()->create(['customer_receipt_line_id' => $line, 'received_on' => $week, 'amount_cents' => $amount,
                'entry_kind' => 'payment', 'method' => 'etransfer', 'reference' => 'BANK-SPLIT', 'note' => 'Test split receipt']);
        }
        $this->actingAs($this->accountant())->get(route('admin.payment-reconciliation.index', ['week' => $week]))
            ->assertOk()->assertSee('BANK-SPLIT')->assertSee('130.00')->assertSee('1 awaiting review');
        $url = route('admin.payment-reconciliation.record');
        $key = 'receipt-line:'.$line;
        $fingerprint = app(PaymentReconciliation::class)->source($week)->get($key)['fingerprint'];
        $data = ['week' => $week, 'movement' => $key, 'fingerprint' => $fingerprint, 'bank_reference' => 'STATEMENT-ABC', 'status' => 'matched'];
        $this->post(route('admin.payment-reconciliation.complete'), ['week' => $week])->assertSessionHasErrors('review');
        $this->post($url, $data + ['matched_cad' => '130.01'])->assertSessionHasErrors('review');
        $this->post($url, array_replace($data, ['status' => 'partial', 'matched_cad' => '120.00', 'notes' => 'Verify the remaining amount.']))->assertSessionHasNoErrors();
        $this->post($url, $data + ['matched_cad' => '130.00'])->assertSessionHasNoErrors();
        $this->post(route('admin.payment-reconciliation.complete'), ['week' => $week, 'notes' => 'Statement checked.'])->assertSessionHasNoErrors();
        $this->get(route('admin.payment-reconciliation.index', ['week' => $week]))->assertOk()->assertSee('Review completed')
            ->assertSee('Earlier reviews')->assertSee('Statement checked.');
        $this->post($url, $data + ['matched_cad' => '130.00'])->assertSessionHasErrors('review');
        $this->assertDatabaseCount('payment_reconciliation_reviews', 2);
        $this->assertDatabaseHas('customer_receipt_lines', ['id' => $line, 'amount_cents' => 13000]);
        $this->assertDatabaseCount('order_settlements', 2);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    private function accountant(): User
    {
        return User::factory()->create(['is_active' => true, 'role_id' => Role::where('name', 'Accountant / Bookkeeper')->value('id')]);
    }

    private function order(?int $customer = null): Order
    {
        $customer ??= User::factory()->create(['account_type' => 'individual'])->customerRecord()->id;
        return Order::create(['customer_id' => $customer, 'number' => 'TEST-'.Str::random(6), 'checkout_token' => Str::uuid(),
            'first_name' => 'Test', 'last_name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '123',
            'address' => '', 'city' => '', 'province' => '', 'postal_code' => '', 'country' => 'Canada', 'fulfillment' => 'pickup',
            'source' => 'website', 'status' => 'delivered', 'payment_method' => 'credit', 'payment_status' => 'unpaid',
            'subtotal_cents' => 10000, 'delivery_cents' => 0, 'tax_cents' => 1000, 'currency' => 'CAD']);
    }

    private function csv(array $rows): UploadedFile
    {
        $rows = array_map(fn ($row) => implode(',', array_pad($row, count(AccountingLedger::HEADERS), '')), $rows);
        return UploadedFile::fake()->createWithContent('journals.csv', implode(',', AccountingLedger::HEADERS)."\n".implode("\n", $rows));
    }
}
