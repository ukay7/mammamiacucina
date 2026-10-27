<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptLine;
use App\Models\Order;
use App\Models\OrderSettlement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\BusinessReports;
use App\Services\CustomerInvoicing;
use App\Services\CustomerReceipts;
use App\Services\ManualPaymentControls;
use App\Services\OrderAmendment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function super(): User
    {
        return User::factory()->create(['role_id' => Role::where('is_super', true)->value('id'), 'is_active' => true]);
    }

    private function customer(): Customer
    {
        return User::factory()->create(['role_id' => Role::where('name', 'Customer')->value('id'), 'account_type' => 'individual', 'is_active' => true])->customerRecord();
    }

    private function order(Customer $customer, int $amount = 10000, array $extra = []): Order
    {
        return Order::create($extra + ['customer_id' => $customer->id, 'checkout_token' => Str::uuid(), 'number' => 'ACCOUNT-'.Str::random(7),
            'source' => 'website', 'first_name' => $customer->name, 'last_name' => 'Test', 'email' => $customer->email, 'phone' => '123',
            'address' => 'Street', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M5V', 'country' => 'Canada',
            'subtotal_cents' => $amount, 'delivery_cents' => 0, 'tax_cents' => 0, 'currency' => 'CAD', 'payment_method' => 'cash',
            'payment_status' => 'unpaid', 'manual_collected_cents' => 0, 'status' => 'delivered', 'revision' => 0, 'ordered_at' => now()->subDays(10)]);
    }

    private function line(string $method, string $amount, array $extra = []): array
    {
        return $extra + ['key' => (string) Str::uuid(), 'method' => $method, 'amount' => $amount, 'payment_date' => today()->toDateString(),
            'reference' => $method === 'cash' ? '' : 'BANK-123', 'cheque_date' => $method === 'cheque' ? today()->toDateString() : null,
            'bank' => '', 'note' => 'Verified customer visit', 'verified' => $method !== 'cheque'];
    }

    private function receive(Customer $customer, array $lines): CustomerReceipt
    {
        $service = app(CustomerReceipts::class);
        $actor = auth()->user();
        $plan = $service->preview($customer, ['lines' => $lines], $actor);

        return $service->create($customer, ['lines' => $lines, 'preview_hash' => $plan['preview_hash'], 'request_key' => (string) Str::uuid(), 'confirmed' => 1], $actor);
    }

    private function act(CustomerReceiptLine $line, string $action): CustomerReceipt
    {
        $service = app(CustomerReceipts::class);
        $actor = auth()->user();
        $input = ['action' => $action, 'revision' => $line->receipt->revision, 'reason' => 'Verified with the bank', 'reference' => 'CLEAR-123', 'payment_date' => today()->toDateString()];
        $plan = $service->previewAction($line, $input, $actor);

        return $service->act($line, $input + ['preview_hash' => $plan['preview_hash'], 'confirmed' => 1], $actor);
    }

    public function test_mixed_payments_split_across_orders_and_cheques_only_settle_after_clearance(): void
    {
        $this->actingAs($this->super());
        $customer = $this->customer();
        $orders = collect([20000, 20000, 20000, 40000])->map(fn ($amount) => $this->order($customer, $amount));
        $product = Product::create(['category_id' => 1, 'slug' => 'receipt-stock-check', 'qr_code' => 'RECEIPT-STOCK', 'premium_marketing_name' => 'Stock preservation check', 'is_active' => true]);
        $product->inventory()->create(['quantity_on_hand' => 42]);
        $orders[0]->items()->create(['product_id' => $product->id, 'name' => $product->premium_marketing_name, 'quantity' => 1, 'unit_cents' => 20000, 'line_cents' => 20000]);
        $invoice = app(CustomerInvoicing::class)->issue($orders[0]);
        $invoiceSnapshot = $invoice->fresh()->getAttributes();
        $beforeInventory = DB::table('inventory_movements')->count();
        $beforeInvoices = DB::table('customer_invoices')->count();
        $lines = [$this->line('cash', '450'), $this->line('cheque', '250')];
        $service = app(CustomerReceipts::class);
        $preview = $service->preview($customer, ['lines' => $lines], auth()->user());
        $this->assertSame(45000, $preview['confirmed_cents']);
        $this->assertSame(25000, $preview['pending_cents']);
        $third = collect($preview['orders'])->firstWhere('id', $orders[2]->id);
        $this->assertSame([5000, 15000], array_column($third['allocations'], 'amount_cents'));
        $receipt = $this->receive($customer, $lines);
        $cheque = $receipt->lines()->where('method', 'cheque')->firstOrFail();
        $this->assertSame('awaiting_clearance', $cheque->status);
        $this->assertSame(0, $cheque->allocations()->count());
        $this->assertSame('paid', $orders[0]->fresh()->payment_status);
        $this->assertSame(15000, $orders[2]->fresh()->balance_cents);
        $this->assertSame('Partially paid', $orders[2]->fresh()->payment_status_label);
        $this->assertSame(55000, app(CustomerInvoicing::class)->balances($customer)['pending']);
        $this->assertSame(30000, $service->account($customer)['maximum_cents']);
        $firstSnapshot = $receipt->events()->first()->snapshot;
        $this->act($cheque, 'clear');
        $cheque->refresh();
        $this->assertSame('cleared', $cheque->status);
        $this->assertSame(2, $orders[2]->fresh()->settlements()->count());
        $this->assertSame(0, $orders[2]->fresh()->balance_cents);
        $this->assertSame(30000, $orders[3]->fresh()->balance_cents);
        $this->assertSame(30000, app(CustomerInvoicing::class)->balances($customer)['pending']);
        $this->assertSame($firstSnapshot, $receipt->events()->first()->fresh()->snapshot);
        $this->assertSame($beforeInventory, DB::table('inventory_movements')->count());
        $this->assertSame($beforeInvoices, DB::table('customer_invoices')->count());
        $this->assertEquals(42, $product->inventory->fresh()->quantity_on_hand);
        $this->assertSame($invoiceSnapshot, $invoice->fresh()->getAttributes());
        $this->assertSame(['delivered'], $orders->map(fn ($o) => $o->fresh()->status)->unique()->values()->all());
        $this->assertSame(70000, (int) app(BusinessReports::class)->cash(['from' => today()->subDay()->toDateString(), 'to' => today()->toDateString()])->sum('m.amount_cents'));
    }

    public function test_total_cannot_exceed_outstanding_and_pending_cheques_reduce_the_available_maximum(): void
    {
        $this->actingAs($this->super());
        $customer = $this->customer();
        $this->order($customer);
        $url = route('admin.customers.receipts.preview', $customer);
        $this->postJson($url, ['lines' => [$this->line('cash', '100.01')]])->assertUnprocessable()->assertSee('maximum CAD 100.00');
        $this->postJson($url, ['lines' => [$this->line('cash', '60'), $this->line('cheque', '50')]])->assertUnprocessable();
        $receipt = $this->receive($customer, [$this->line('cheque', '70')]);
        $this->assertSame(3000, app(CustomerReceipts::class)->account($customer)['maximum_cents']);
        $this->postJson($url, ['lines' => [$this->line('cash', '30.01')]])->assertUnprocessable()->assertSee('maximum CAD 30.00');
        $this->receive($customer, [$this->line('cash', '30')]);
        $this->assertSame(7000, app(CustomerInvoicing::class)->balances($customer)['pending']);
        $this->act($receipt->lines()->first(), 'cancel');
        $this->assertSame(7000, app(CustomerReceipts::class)->account($customer)['maximum_cents']);
        $this->assertSame(3000, (int) OrderSettlement::sum('amount_cents'));
    }

    public function test_clearance_reallocates_to_current_balances_and_bounce_reverses_only_that_cheque(): void
    {
        $this->actingAs($this->super());
        $customer = $this->customer();
        $first = $this->order($customer, 20000);
        $second = $this->order($customer, 30000);
        $receipt = $this->receive($customer, [$this->line('cash', '50'), $this->line('cheque', '200')]);
        $cheque = $receipt->lines()->where('method', 'cheque')->first();
        $this->receive($customer, [$this->line('cash', '100')]);
        $this->act($cheque, 'clear');
        $this->assertSame([5000, 15000], $cheque->allocations()->orderBy('order_id')->pluck('amount_cents')->all());
        $this->assertSame(15000, app(CustomerInvoicing::class)->balances($customer)['pending']);
        $this->act($cheque->fresh(), 'bounce');
        $this->assertSame('bounced', $cheque->fresh()->status);
        $this->assertSame(35000, app(CustomerInvoicing::class)->balances($customer)['pending']);
        $this->assertSame(5000, $first->fresh()->balance_cents);
        $this->assertSame(30000, $second->fresh()->balance_cents);
        $this->assertSame(15000, (int) OrderSettlement::sum('amount_cents'));
        $this->get(route('admin.customers.receipts.show', [$customer, $receipt]))->assertOk()->assertSee('Bounced')->assertSee('Full audit history');
    }

    public function test_oldest_order_business_date_controls_allocation_and_ineligible_orders_are_excluded(): void
    {
        $this->actingAs($this->super());
        $customer = $this->customer();
        $other = $this->customer();
        $later = $this->order($customer, 10000, ['ordered_at' => now()->subDay()]);
        $oldest = $this->order($customer, 10000, ['ordered_at' => now()->subDays(30), 'payment_method' => 'cheque', 'cheque_due_date' => today()->subDay()->toDateString()]);
        $this->order($customer, 5000, ['status' => 'cancelled']);
        $deleted = $this->order($customer, 5000);
        $deleted->delete();
        $this->order($customer, 5000, ['payment_status' => 'paid', 'manual_collected_cents' => 5000]);
        $this->order($customer, 5000, ['tax_cents' => null]);
        $this->order($other, 99999);
        $gateway = $this->order($customer, 5000);
        Payment::create(['order_id' => $gateway->id, 'reference' => Str::uuid(), 'provider' => 'stripe', 'mode' => 'live', 'status' => 'pending', 'amount_cents' => 5000, 'currency' => 'CAD', 'expires_at' => now()->addHour()]);
        $account = app(CustomerReceipts::class)->account($customer);
        $this->assertSame([$oldest->id, $later->id], array_column($account['orders'], 'id'));
        $this->assertSame(20000, $account['maximum_cents']);
        $this->assertSame(1, $account['overdue_orders']);
        $this->receive($customer, [$this->line('cash', '50')]);
        $this->assertSame(5000, $oldest->fresh()->balance_cents);
        $this->assertSame(10000, $later->fresh()->balance_cents);
    }

    public function test_stale_preview_duplicate_submission_and_duplicate_clearance_cannot_collect_twice(): void
    {
        $actor = $this->super();
        $this->actingAs($actor);
        $customer = $this->customer();
        $order = $this->order($customer);
        $service = app(CustomerReceipts::class);
        $lines = [$this->line('cash', '40')];
        $plan = $service->preview($customer, ['lines' => $lines], $actor);
        $input = ['lines' => $lines, 'preview_hash' => $plan['preview_hash'], 'request_key' => (string) Str::uuid(), 'confirmed' => 1];
        $first = $service->create($customer, $input, $actor);
        $again = $service->create($customer, $input, $actor);
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, CustomerReceipt::count());
        $this->assertSame(4000, (int) OrderSettlement::sum('amount_cents'));
        $this->postJson(route('admin.customers.receipts.store', $customer), $input + [])->assertOk();
        $stale = $service->preview($customer, ['lines' => [$this->line('cash', '10')]], $actor);
        $order->increment('revision');
        $this->postJson(route('admin.customers.receipts.store', $customer), ['lines' => [$this->line('cash', '10')], 'preview_hash' => $stale['preview_hash'], 'request_key' => (string) Str::uuid(), 'confirmed' => 1])->assertUnprocessable();
        $chequeReceipt = $this->receive($customer, [$this->line('cheque', '20')]);
        $line = $chequeReceipt->lines()->first();
        $input = ['action' => 'clear', 'revision' => 0, 'reason' => 'Verified clearance', 'reference' => 'BANK-OK', 'payment_date' => today()->toDateString()];
        $plan = $service->previewAction($line, $input, $actor);
        $input += ['preview_hash' => $plan['preview_hash'], 'confirmed' => 1];
        $service->act($line, $input, $actor);
        $this->postJson(route('admin.customers.receipts.action', [$customer, $chequeReceipt, $line]), $input)->assertUnprocessable();
        $this->assertSame(6000, (int) OrderSettlement::sum('amount_cents'));
    }

    public function test_validation_requires_actual_verification_dates_references_and_receipt_proof(): void
    {
        $this->actingAs($this->super());
        $customer = $this->customer();
        $this->order($customer);
        $url = route('admin.customers.receipts.preview', $customer);
        foreach (['0', '-1', '1e2', '0.001', '10000000'] as $amount) {
            $this->postJson($url, ['lines' => [$this->line('cash', $amount)]])->assertUnprocessable();
        }
        $this->postJson($url, ['lines' => [$this->line('cash', '10', ['verified' => false])]])->assertUnprocessable();
        $this->postJson($url, ['lines' => [$this->line('cash', '10', ['payment_date' => today()->addDay()->toDateString()])]])->assertUnprocessable();
        $this->postJson($url, ['lines' => [$this->line('cheque', '10', ['reference' => ''])]])->assertUnprocessable();
        $this->postJson($url, ['lines' => [$this->line('etransfer', '10', ['proof_hash' => str_repeat('a', 64)])]])->assertUnprocessable();
        $this->postJson($url, ['lines' => ['invalid row']])->assertUnprocessable();
        $this->assertSame(0, CustomerReceipt::count());
        $this->assertSame(0, OrderSettlement::count());
    }

    public function test_evidence_is_private_and_preview_never_stores_files_and_retry_has_no_orphans(): void
    {
        Storage::fake('local');
        $this->actingAs($this->super());
        $customer = $this->customer();
        $this->order($customer);
        $line = $this->line('etransfer', '25');
        $proof = UploadedFile::fake()->create('bank.pdf', 5, 'application/pdf');
        $payload = ['lines' => [0 => $line + ['proof' => $proof]]];
        $preview = $this->post(route('admin.customers.receipts.preview', $customer), $payload, ['Accept' => 'application/json'])->assertOk()->json();
        $this->assertSame([], Storage::disk('local')->allFiles());
        $payload += ['preview_hash' => $preview['preview_hash'], 'request_key' => (string) Str::uuid(), 'confirmed' => 1];
        $this->post(route('admin.customers.receipts.store', $customer), $payload, ['Accept' => 'application/json'])->assertOk();
        $receipt = CustomerReceipt::first();
        $line = $receipt->lines()->first();
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->post(route('admin.customers.receipts.store', $customer), $payload, ['Accept' => 'application/json'])->assertOk();
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->get(route('admin.customers.receipts.proof', [$customer, $receipt, $line]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $other = $this->customer();
        $this->get(route('admin.customers.receipts.proof', [$other, $receipt, $line]))->assertNotFound();
        $this->get(route('admin.customers.receipts.document', [$customer, $receipt]))->assertOk()->assertSee('Customer Payment Receipt')->assertSee('Received from')->assertSee('Confirmed order allocations');
    }

    public function test_snapshot_versions_cannot_cross_receipts_and_documents_preserve_original_values(): void
    {
        $this->actingAs($this->super());
        $customer = $this->customer();
        $this->order($customer, 50000);
        $receipt = $this->receive($customer, [$this->line('cheque', '100')]);
        $event = $receipt->events()->first();
        $this->get(route('admin.customers.receipts.document', [$customer, $receipt]))->assertOk()->assertSee('Cheque Acknowledgement')->assertSee('Awaiting clearance');
        $this->act($receipt->lines()->first(), 'clear');
        $this->get(route('admin.customers.receipts.document', [$customer, $receipt]))->assertOk()->assertSee('Customer Payment Receipt')->assertSee('Cleared');
        $this->get(route('admin.customers.receipts.document', [$customer, $receipt, 'version' => $event->id]))->assertOk()->assertSee('Cheque Acknowledgement')->assertSee('Awaiting clearance');
        $other = $this->receive($customer, [$this->line('cash', '20')]);
        $this->get(route('admin.customers.receipts.document', [$customer, $other, 'version' => $event->id]))->assertNotFound();
        $this->assertSame(50000, $event->fresh()->snapshot['outstanding_cents']);
    }

    public function test_receipt_controls_are_super_admin_only_even_with_forged_permissions(): void
    {
        $customer = $this->customer();
        $this->order($customer);
        $this->actingAs($this->super());
        $receipt = $this->receive($customer, [$this->line('cash', '10')]);
        $line = $receipt->lines()->first();
        $staff = User::factory()->create(['is_active' => true, 'role_id' => Role::create(['name' => 'Receipt attacker', 'permissions' => ['orders.view', 'orders.manage', 'customers.manage', 'access.admin.customers.index', 'access.admin.customers.receipts.create']])->id]);
        $this->actingAs($staff)->get(route('admin.customers.show', $customer))->assertOk()->assertDontSee('Receive Payment');
        foreach (['create' => [$customer], 'show' => [$customer, $receipt], 'document' => [$customer, $receipt], 'proof' => [$customer, $receipt, $line]] as $action => $params) {
            $this->get(route('admin.customers.receipts.'.$action, $params))->assertForbidden();
        }
        $this->postJson(route('admin.customers.receipts.store', $customer), [])->assertForbidden();
        $this->postJson(route('admin.customers.receipts.action', [$customer, $receipt, $line]), [])->assertForbidden();
    }

    public function test_batch_allocations_cannot_be_silently_changed_in_order_controls_or_deleted(): void
    {
        $this->actingAs($this->super());
        $customer = $this->customer();
        $order = $this->order($customer);
        $receipt = $this->receive($customer, [$this->line('cash', '50')]);
        $order->refresh();
        $this->assertSame([], app(ManualPaymentControls::class)->sources($order));
        $this->assertStringContainsString('customer receipt', $order->deletionBlockReason());
        $this->act($receipt->lines()->first(), 'reverse');
        $order->refresh();
        $this->assertSame(10000, $order->balance_cents);
        $this->assertNull($order->deletionBlockReason());
        $this->assertSame(0, (int) OrderSettlement::sum('amount_cents'));
        $this->assertSame(2, $receipt->events()->count());
    }

    public function test_refunded_customer_receipt_does_not_block_deletion_after_money_is_resolved(): void
    {
        $actor = $this->super();
        $this->actingAs($actor);
        $customer = $this->customer();
        $order = $this->order($customer);
        $this->receive($customer, [$this->line('cash', '50')]);
        $order->refresh()->update(['status' => 'cancelled']);
        app(OrderAmendment::class)->settle($order->fresh(), ['revision' => $order->fresh()->revision, 'direction' => 'refund',
            'amount' => '50', 'method' => 'cash', 'note' => 'Cash refund returned to customer'], $actor->id);
        $order->refresh();
        $this->assertSame(0, $order->net_received_cents);
        $this->assertNull($order->deletionBlockReason());
    }

    public function test_postdated_cheque_cannot_be_cleared_early_and_a_stale_clearance_preview_is_rejected(): void
    {
        $actor = $this->super();
        $this->actingAs($actor);
        $customer = $this->customer();
        $order = $this->order($customer);
        $receipt = $this->receive($customer, [$this->line('cheque', '50', ['cheque_date' => today()->addDay()->toDateString()])]);
        $line = $receipt->lines()->first();
        $input = ['action' => 'clear', 'revision' => 0, 'reason' => 'Verified bank record', 'reference' => 'BANK-CLEAR', 'payment_date' => today()->toDateString()];
        $this->postJson(route('admin.customers.receipts.action-preview', [$customer, $receipt, $line]), $input)->assertUnprocessable();
        $this->travel(1)->days();
        $input['payment_date'] = today()->toDateString();
        $plan = app(CustomerReceipts::class)->previewAction($line, $input, $actor);
        $order->increment('revision');
        $this->postJson(route('admin.customers.receipts.action', [$customer, $receipt, $line]), $input + ['preview_hash' => $plan['preview_hash'], 'confirmed' => 1])->assertUnprocessable();
        $this->assertSame(0, OrderSettlement::count());
        $this->assertSame('awaiting_clearance', $line->fresh()->status);
        $this->travelBack();
    }

    public function test_duplicate_instrument_references_and_actions_before_receipt_are_rejected(): void
    {
        $actor = $this->super();
        $this->actingAs($actor);
        $customer = $this->customer();
        $this->order($customer, 50000);
        $url = route('admin.customers.receipts.preview', $customer);
        $this->postJson($url, ['lines' => [$this->line('cheque', '50'), $this->line('cheque', '20', ['reference' => ' bank-123 '])]])
            ->assertUnprocessable()->assertSee('already recorded');
        $receipt = $this->receive($customer, [$this->line('cheque', '50')]);
        $this->postJson($url, ['lines' => [$this->line('cheque', '50')]])->assertUnprocessable();
        $line = $receipt->lines()->first();
        $input = ['action' => 'bounce', 'revision' => 0, 'reason' => 'Bank rejection', 'payment_date' => today()->subDay()->toDateString()];
        $this->postJson(route('admin.customers.receipts.action-preview', [$customer, $receipt, $line]), $input)
            ->assertUnprocessable()->assertSee('cannot be before');
        $this->act($line, 'clear');
        $this->postJson($url, ['lines' => [$this->line('cheque', '50')]])->assertUnprocessable();
        $this->act($line->fresh(), 'bounce');
        $this->postJson($url, ['lines' => [$this->line('cheque', '50')]])->assertOk();
        $this->assertSame(0, (int) OrderSettlement::sum('amount_cents'));
    }
}
