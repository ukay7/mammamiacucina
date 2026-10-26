<?php

namespace Tests\Feature;

use App\Models\EmailHistory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminDashboard;
use App\Services\BusinessReports;
use App\Services\CustomerInvoicing;
use App\Services\CustomerTerms;
use App\Services\DocumentTheme;
use App\Services\ManualPaymentControls;
use App\Services\OnlinePayments;
use App\Services\StaffPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ManualPaymentControlsTest extends TestCase
{
    use RefreshDatabase;

    private function super(): User
    {
        return User::factory()->create(['role_id' => Role::where('is_super', true)->value('id'), 'is_active' => true]);
    }

    private function order(array $extra = []): Order
    {
        return Order::create($extra + ['checkout_token' => (string) Str::uuid(), 'number' => 'PAYCTRL-'.Str::random(8),
            'source' => 'website', 'first_name' => 'Payment', 'last_name' => 'Customer', 'email' => 'payment@example.test', 'phone' => '123',
            'address' => 'Street', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M5V', 'country' => 'Canada',
            'subtotal_cents' => 10000, 'delivery_cents' => 0, 'tax_cents' => 0, 'currency' => 'CAD',
            'payment_method' => 'etransfer', 'payment_status' => 'unpaid', 'status' => 'warehouse_pending', 'revision' => 0]);
    }

    private function data(Order $order, array $extra = []): array
    {
        return $extra + ['revision' => $order->revision, 'reason' => 'Verified with bank and customer', 'confirmed' => 1];
    }

    private function adjustment(Order $order, array $extra = []): array
    {
        return $this->data($order, $extra + ['action' => 'reverse', 'source' => 'original', 'amount' => '100.00']);
    }

    private function gateway(Order $order, array $extra = []): Payment
    {
        return Payment::create($extra + ['order_id' => $order->id, 'reference' => (string) Str::uuid(), 'provider' => 'stripe',
            'mode' => 'live', 'status' => 'pending', 'amount_cents' => 10000, 'currency' => 'CAD', 'expires_at' => now()->addHour()]);
    }

    public function test_super_admin_can_change_unpaid_method_without_collecting_or_restocking(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('transfer-receipts/existing.pdf', 'existing proof');
        $order = $this->order(['transfer_receipt_path' => 'transfer-receipts/existing.pdf']);
        $actor = $this->super();
        $this->actingAs($actor)->patch(route('admin.orders.payment-method', $order), $this->data($order, ['method' => 'cash']))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('cash', $order->payment_method);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('warehouse_pending', $order->status);
        $this->assertSame(0, $order->net_received_cents);
        $this->assertSame(10000, $order->balance_cents);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('order_settlements', 0);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'user_id' => $actor->id]);
        $this->get(route('orders.transfer-receipt', $order))->assertOk();
        Storage::disk('local')->assertExists('transfer-receipts/existing.pdf');
        $this->get(route('admin.orders.payments', $order))->assertOk()->assertSee('Change unpaid payment method')->assertSee('Verified with bank and customer');
    }

    public function test_non_super_cannot_use_controls_even_with_forged_permissions(): void
    {
        $order = $this->order(['payment_status' => 'paid', 'manual_collected_cents' => 10000]);
        $role = Role::create(['name' => 'Payment manager', 'permissions' => [...StaffPermissions::normalize(['orders.view', 'orders.manage']), 'access.admin.orders.payment-method', 'access.admin.orders.payment-adjustment']]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($user)->get(route('admin.orders.payments', $order))->assertOk()->assertDontSee('Super Admin payment controls');
        $this->patchJson(route('admin.orders.payment-method', $order), $this->data($order, ['method' => 'cash']))->assertForbidden();
        $this->postJson(route('admin.orders.payment-adjustment', $order), $this->adjustment($order))->assertForbidden();
        try {
            app(ManualPaymentControls::class)->adjust($order, $this->adjustment($order), $user);
            $this->fail('Service accepted a non-Super Admin.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_unpaid_method_can_change_before_delivery_and_tax_are_finalized(): void
    {
        $this->actingAs($this->super());
        $order = $this->order(['delivery_cents' => null, 'tax_cents' => null, 'status' => 'parked_admin']);
        $this->get(route('admin.orders.payments', $order))->assertOk()->assertSee('Change unpaid payment method');
        $this->patch(route('admin.orders.payment-method', $order), $this->data($order, ['method' => 'cash']))->assertSessionHasNoErrors();
        $this->assertSame('cash', $order->fresh()->payment_method);
        $this->assertNull($order->fresh()->final_total_cents);
        $this->assertSame('parked_admin', $order->fresh()->status);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_changes_require_reason_confirmation_and_fresh_revision(): void
    {
        $this->actingAs($this->super());
        $order = $this->order();
        $url = route('admin.orders.payment-method', $order);
        foreach (['reason', 'confirmed', 'revision'] as $field) {
            $data = $this->data($order, ['method' => 'cash']);
            unset($data[$field]);
            $this->patchJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->patchJson($url, $this->data($order, ['method' => 'cash', 'reason' => '  ']))->assertUnprocessable();
        $this->patchJson($url, $this->data($order, ['method' => 'cash', 'revision' => 999]))->assertUnprocessable();
        $this->assertSame('etransfer', $order->fresh()->payment_method);
    }

    public function test_partial_payment_retains_method_then_cash_settles_remaining_balance(): void
    {
        $this->actingAs($this->super());
        $order = $this->order(['manual_collected_cents' => 0]);
        $entry = $order->settlements()->create(['amount_cents' => 3000, 'method' => 'etransfer', 'reference' => 'BANK-30', 'note' => 'Verified deposit']);
        $original = $entry->fresh()->getAttributes();
        $this->patch(route('admin.orders.payment-method', $order), $this->data($order, ['method' => 'cash']))->assertSessionHasNoErrors();
        $order->refresh();
        $this->post(route('admin.orders.settlement', $order), ['revision' => $order->revision, 'direction' => 'collect', 'method' => 'cash', 'amount' => '70.00', 'note' => 'Remaining cash received', 'received' => 1])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(10000, $order->net_received_cents);
        $this->assertSame(0, $order->balance_cents);
        $this->assertSame($original, $entry->fresh()->getAttributes());
        $this->assertDatabaseHas('order_settlements', ['order_id' => $order->id, 'method' => 'cash', 'amount_cents' => 7000]);
    }

    public function test_confirmed_gateway_and_pending_gateway_cannot_be_changed_or_reversed(): void
    {
        $this->actingAs($this->super());
        Http::preventStrayRequests();
        foreach (['pending', 'paid', 'partially_refunded', 'refunded'] as $state) {
            $order = $this->order(['payment_method' => 'card', 'payment_status' => $state === 'pending' ? 'pending' : 'paid']);
            $payment = $this->gateway($order, ['status' => $state, 'paid_at' => $state === 'pending' ? null : now()]);
            $snapshot = $payment->fresh()->getAttributes();
            $this->patchJson(route('admin.orders.payment-method', $order), $this->data($order, ['method' => 'cash']))->assertUnprocessable();
            $this->postJson(route('admin.orders.payment-adjustment', $order), $this->adjustment($order))->assertUnprocessable();
            $this->assertSame($snapshot, $payment->fresh()->getAttributes());
            $this->assertSame('card', $order->fresh()->payment_method);
        }
    }

    public function test_bounced_cheque_reopens_completed_order_balance_without_refund_or_stock_change(): void
    {
        $actor = $this->super();
        $this->actingAs($actor);
        $customer = User::factory()->create(['account_type' => 'individual', 'email' => 'cheque@example.test'])->customerRecord();
        $order = $this->order(['customer_id' => $customer->id, 'payment_method' => 'cheque', 'payment_status' => 'paid', 'manual_collected_cents' => 10000, 'paid_at' => now(), 'status' => 'completed']);
        $order->items()->create(['name' => 'Original invoice item', 'quantity' => 1, 'unit_cents' => 10000, 'line_cents' => 10000]);
        $invoice = app(CustomerInvoicing::class)->issue($order);
        $snapshot = $invoice->fresh()->getAttributes();
        $emails = EmailHistory::count();
        $this->post(route('admin.orders.payment-adjustment', $order), $this->adjustment($order, ['action' => 'bounced_cheque']))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame(0, $order->net_received_cents);
        $this->assertSame(10000, $order->balance_cents);
        $this->assertSame(0, $order->refunded_cents);
        $this->assertSame(10000, $order->reversed_cents);
        $this->assertSame(10000, app(CustomerInvoicing::class)->balances($customer)['pending']);
        $this->assertSame(10000, (int) app(AdminDashboard::class)->balances()->where('o.id', $order->id)->first()->balance_cents);
        $this->assertSame($snapshot, $invoice->fresh()->getAttributes());
        $this->assertSame($emails, EmailHistory::count());
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->postJson(route('admin.orders.payment-adjustment', $order), $this->adjustment($order))->assertUnprocessable();
        $this->assertDatabaseCount('order_settlements', 1);
        $this->patch(route('admin.orders.payment-method', $order), $this->data($order, ['method' => 'cash']))->assertSessionHasNoErrors();
        $order->refresh();
        $this->post(route('admin.orders.settlement', $order), ['revision' => $order->revision, 'direction' => 'collect', 'method' => 'cash', 'amount' => '100.00', 'note' => 'Replacement for bounced cheque', 'received' => 1])->assertSessionHasNoErrors();
        $this->assertSame(0, $order->fresh()->balance_cents);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_method_correction_reassigns_amount_without_new_collection_or_refund(): void
    {
        $this->actingAs($this->super());
        $order = $this->order(['payment_method' => 'cash', 'payment_status' => 'paid', 'manual_collected_cents' => 10000, 'paid_at' => now()]);
        $this->post(route('admin.orders.payment-adjustment', $order), $this->adjustment($order, ['action' => 'correct_method', 'method' => 'etransfer', 'reference' => 'BANK-100']))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(10000, $order->manual_collected_cents);
        $this->assertSame('cash', $order->original_payment_method);
        $this->assertSame('etransfer', $order->payment_method);
        $this->assertSame(10000, $order->net_received_cents);
        $this->assertSame(0, $order->refunded_cents);
        $this->assertSame(0, $order->reversed_cents);
        $this->assertDatabaseCount('order_settlements', 2);
        $source = collect(app(ManualPaymentControls::class)->sources($order))->firstWhere('method', 'etransfer');
        $this->assertNotNull($source);
        $this->post(route('admin.orders.payment-adjustment', $order), $this->adjustment($order, ['source' => $source['id']]))->assertSessionHasNoErrors();
        $this->assertSame(10000, $order->fresh()->balance_cents);
        $this->assertSame(0, $order->fresh()->refunded_cents);
    }

    public function test_foreign_source_invalid_bounce_over_reversal_and_paid_method_change_are_rejected(): void
    {
        $this->actingAs($this->super());
        $order = $this->order(['payment_method' => 'cash', 'payment_status' => 'paid', 'manual_collected_cents' => 10000]);
        $other = $this->order();
        $entry = $other->settlements()->create(['amount_cents' => 10000, 'method' => 'cash', 'note' => 'Other order']);
        foreach ([['source' => (string) $entry->id], ['action' => 'bounced_cheque'], ['amount' => '101.00'], ['action' => 'correct_method', 'method' => 'cash']] as $extra) {
            $this->postJson(route('admin.orders.payment-adjustment', $order), $this->adjustment($order, $extra))->assertUnprocessable();
        }
        $this->patchJson(route('admin.orders.payment-method', $order), $this->data($order, ['method' => 'etransfer']))->assertUnprocessable();
        $this->assertSame(10000, $order->fresh()->net_received_cents);
    }

    public function test_reversal_and_corrections_are_separate_from_refunds_in_reports_and_exports(): void
    {
        $this->actingAs($this->super());
        $order = $this->order(['payment_method' => 'cheque', 'payment_status' => 'paid', 'manual_collected_cents' => 10000, 'paid_at' => now()]);
        $this->post(route('admin.orders.payment-adjustment', $order), $this->adjustment($order, ['action' => 'bounced_cheque']))->assertSessionHasNoErrors();
        $f = ['from' => today()->toDateString(), 'to' => today()->toDateString()];
        $reports = app(BusinessReports::class);
        foreach (['payments', 'methods'] as $kind) {
            $summary = $reports->report($kind, $f)['summary'];
            $this->assertSame(10000, (int) $summary['received']);
            $this->assertSame(0, (int) $summary['refunded']);
            $this->assertSame(10000, (int) $summary['reversed']);
            $this->assertSame(0, (int) $summary['net']);
            $this->get(route('admin.reports.business', ['kind' => $kind] + $f))->assertOk()->assertSee('Reversed');
            $this->get(route('admin.reports.business.export', ['kind' => $kind] + $f))->assertOk();
        }
        $this->get(route('admin.reports.payments'))->assertOk()->assertSee('Reversed');
        $export = $this->get(route('admin.reports.payments.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('"Reversed CAD"', $export);
        $rows = array_map(fn ($row) => str_getcsv($row, ',', '"', ''), explode("\n", trim($export)));
        $this->assertSame('0.00', $rows[1][8]);
        $this->assertSame('0.00', $rows[1][9]);
        $this->assertSame('100.00', trim($rows[1][11]));
    }

    public function test_correction_and_reversal_documents_keep_shared_theme_and_do_not_claim_refund(): void
    {
        $this->actingAs($this->super());
        $order = $this->order(['payment_method' => 'cheque', 'payment_status' => 'paid', 'manual_collected_cents' => 10000, 'paid_at' => now()]);
        $this->post(route('admin.orders.payment-adjustment', $order), $this->adjustment($order, ['action' => 'bounced_cheque']))->assertSessionHasNoErrors();
        $entry = $order->settlements()->firstOrFail();
        $doc = app(DocumentTheme::class)->order($order->fresh(), null, $entry);
        $this->assertSame('Payment Reversal', $doc['title']);
        $this->assertStringContainsString('No customer refund', $doc['notice']);
        $this->get(route('admin.orders.payment-document', [$order, $entry]))->assertOk()->assertSee('Payment Reversal')->assertSee('Verified with bank and customer')->assertDontSee('Refund Receipt');
        $this->get(route('admin.orders.initial-payment-document', $order))->assertOk()->assertSee('Original recorded collection')->assertSee('Cheque');
    }

    public function test_deleted_orders_and_stale_duplicate_adjustments_cannot_be_modified(): void
    {
        $this->actingAs($this->super());
        $order = $this->order(['payment_status' => 'paid', 'manual_collected_cents' => 10000]);
        $data = $this->adjustment($order, ['amount' => '25.00']);
        $this->post(route('admin.orders.payment-adjustment', $order), $data)->assertSessionHasNoErrors();
        $this->postJson(route('admin.orders.payment-adjustment', $order), $data)->assertUnprocessable();
        $this->assertSame(7500, $order->fresh()->net_received_cents);
        $order->delete();
        $this->postJson(route('admin.orders.payment-adjustment', $order), $data)->assertNotFound();
        $this->patchJson(route('admin.orders.payment-method', $order), $this->data($order, ['method' => 'cash']))->assertNotFound();
    }

    public function test_closed_unpaid_checkout_is_retained_and_late_gateway_success_requires_review(): void
    {
        $this->actingAs($this->super());
        $order = $this->order(['payment_method' => 'card']);
        $payment = $this->gateway($order, ['status' => 'failed', 'provider_order_id' => 'cs_closed']);
        $this->patch(route('admin.orders.payment-method', $order), $this->data($order, ['method' => 'cash']))->assertSessionHasNoErrors();
        $this->assertNotNull($payment->fresh()->superseded_at);
        $this->assertNull($order->fresh()->payment);
        $this->assertSame(10000, (int) app(AdminDashboard::class)->balances()->where('o.id', $order->id)->first()->balance_cents);
        $order->refresh();
        $this->patchJson(route('admin.orders.update', $order), ['revision' => $order->revision, 'status' => $order->status,
            'payment_status' => 'paid', 'delivery' => '0.00', 'tax' => '0.00'])->assertUnprocessable();
        $this->post(route('admin.orders.settlement', $order), ['revision' => $order->revision, 'direction' => 'collect',
            'method' => 'cash', 'amount' => '100.00', 'note' => 'Cash replacement', 'received' => 1])->assertSessionHasNoErrors();
        $p = $payment->fresh();
        config(['payments.mode' => 'live', 'payments.stripe.enabled' => true, 'payments.stripe.secret' => 'sk_live_fake', 'payments.stripe.webhook_secret' => 'whsec_test']);
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_closed', 'client_reference_id' => $p->reference,
            'amount_total' => 10000, 'currency' => 'cad', 'livemode' => true, 'payment_status' => 'paid',
            'status' => 'complete', 'payment_intent' => 'pi_late'])]);
        app(OnlinePayments::class)->sync($p);
        $this->assertNull($payment->fresh()->superseded_at);
        $this->assertNotNull($payment->fresh()->attention);
        $this->assertSame('payment_review', $order->fresh()->status);
        $this->assertSame(20000, $order->fresh()->net_received_cents);
        $this->assertSame(-10000, $order->fresh()->balance_cents);
        $this->assertSame('card', $order->fresh()->original_payment_method);
    }

    public function test_credit_limits_and_cheque_due_dates_follow_unpaid_method_changes(): void
    {
        $this->actingAs($this->super());
        $customer = User::factory()->create(['account_type' => 'business'])->customerRecord();
        $customer->update(['credit_limit_cents' => 5000, 'credit_policy' => 'block', 'cheque_days' => 10]);
        $order = $this->order(['customer_id' => $customer->id]);
        $url = route('admin.orders.payment-method', $order);
        $this->patchJson($url, $this->data($order, ['method' => 'credit']))->assertUnprocessable();
        $this->assertSame('etransfer', $order->fresh()->payment_method);
        $customer->update(['credit_limit_cents' => 20000]);
        $this->patch($url, $this->data($order, ['method' => 'credit']))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(10000, app(CustomerInvoicing::class)->balances($customer)['used']);
        $this->patch($url, $this->data($order, ['method' => 'cheque']))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(today()->addDays(10)->toDateString(), $order->cheque_due_date);
        $this->assertSame(0, app(CustomerInvoicing::class)->balances($customer)['used']);
        $this->patch($url, $this->data($order, ['method' => 'cash']))->assertSessionHasNoErrors();
        $this->assertNull($order->fresh()->cheque_due_date);
    }

    public function test_correction_does_not_inflate_collections_and_cannot_reverse_refunded_money(): void
    {
        $this->actingAs($this->super());
        $order = $this->order(['payment_method' => 'cash', 'payment_status' => 'paid', 'manual_collected_cents' => 10000,
            'manual_refunded_cents' => 2000, 'paid_at' => now()]);
        $url = route('admin.orders.payment-adjustment', $order);
        $this->postJson($url, $this->adjustment($order))->assertUnprocessable();
        $this->postJson($url, $this->adjustment($order, ['action' => 'correct_method', 'amount' => '80.00', 'method' => 'etransfer']))->assertUnprocessable();
        $this->assertDatabaseCount('order_settlements', 0);
        $this->post($url, $this->adjustment($order, ['action' => 'correct_method', 'amount' => '80.00', 'method' => 'etransfer', 'reference' => 'BANK-80']))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(8000, $order->net_received_cents);
        $this->assertSame(2000, $order->refunded_cents);
        $report = app(BusinessReports::class)->report('methods', ['from' => today()->toDateString(), 'to' => today()->toDateString()]);
        $this->assertSame(10000, (int) $report['summary']['received']);
        $this->assertSame(0, (int) $report['summary']['reversed']);
        $this->assertSame(0, (int) $report['summary']['adjustments']);
        $cash = $report['rows']->get()->firstWhere('method', 'cash');
        $this->assertSame(-8000, (int) $cash->adjustments);
        $this->postJson($url, $this->adjustment($order, ['amount' => '0.01']))->assertUnprocessable();
        $source = collect(app(ManualPaymentControls::class)->sources($order))->firstWhere('method', 'etransfer');
        $this->post($url, $this->adjustment($order, ['source' => $source['id'], 'amount' => '80.00']))->assertSessionHasNoErrors();
        $this->assertSame(0, $order->fresh()->net_received_cents);
        $this->assertSame(2000, $order->fresh()->refunded_cents);
    }

    public function test_paid_cash_can_be_corrected_to_credit_without_recording_credit_as_received_money(): void
    {
        $actor = $this->super();
        $this->actingAs($actor);
        $customer = User::factory()->create(['account_type' => 'business'])->customerRecord();
        $customer->update(['credit_limit_cents' => 20000, 'credit_policy' => 'block']);
        $order = $this->order(['customer_id' => $customer->id, 'payment_method' => 'cash', 'payment_status' => 'paid',
            'manual_collected_cents' => null, 'paid_at' => now(), 'status' => 'completed']);
        $order->items()->create(['name' => 'Original invoice item', 'quantity' => 1, 'unit_cents' => 10000, 'line_cents' => 10000]);
        $invoice = app(CustomerInvoicing::class)->issue($order);
        $invoiceSnapshot = $invoice->fresh()->getAttributes();
        $emails = EmailHistory::count();
        $this->get(route('admin.orders.payments', $order))->assertOk()->assertSee('value="credit">Customer Credit', false);
        $this->post(route('admin.orders.payment-adjustment', $order), $this->adjustment($order, [
            'action' => 'correct_method', 'method' => 'credit', 'reason' => 'Cash was selected by mistake; customer has not paid',
        ]))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('credit', $order->payment_method);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('completed', $order->status);
        $this->assertSame('cash', $order->original_payment_method);
        $this->assertSame(10000, (int) $order->manual_collected_cents);
        $this->assertSame(0, $order->net_received_cents);
        $this->assertSame(10000, $order->balance_cents);
        $this->assertSame(0, $order->refunded_cents);
        $this->assertSame(0, $order->reversed_cents);
        $this->assertDatabaseCount('order_settlements', 1);
        $this->assertDatabaseHas('order_settlements', ['order_id' => $order->id, 'method' => 'cash', 'entry_kind' => 'correction_debit', 'amount_cents' => -10000]);
        $this->assertDatabaseMissing('order_settlements', ['order_id' => $order->id, 'method' => 'credit']);
        $this->assertSame([], app(ManualPaymentControls::class)->sources($order));
        $balances = app(CustomerInvoicing::class)->balances($customer);
        $this->assertSame(10000, $balances['pending']);
        $this->assertSame(10000, $balances['used']);
        $this->assertSame(10000, $balances['available']);
        $this->assertSame(10000, app(CustomerTerms::class)->used($customer));
        $this->assertSame(10000, (int) app(AdminDashboard::class)->balances()->where('o.id', $order->id)->first()->balance_cents);
        $this->assertSame($invoiceSnapshot, $invoice->fresh()->getAttributes());
        $this->assertSame($emails, EmailHistory::count());
        $this->assertDatabaseCount('inventory_movements', 0);
        $event = $order->events()->where('user_id', $actor->id)->latest('id')->firstOrFail();
        $this->assertStringContainsString('cash → credit', $event->description);
        $this->assertStringContainsString('paid → unpaid', $event->description);
        $this->assertStringContainsString('customer has not paid', $event->description);
        $this->get(route('admin.orders.payments', $order))->assertOk()->assertSee('Moved to Customer Credit; no funds received.');
        $summary = app(BusinessReports::class)->report('payments', ['from' => today()->toDateString(), 'to' => today()->toDateString()])['summary'];
        $this->assertSame(0, (int) $summary['net']);
        $this->assertSame(0, (int) $summary['refunded']);
        $this->assertSame(-10000, (int) $summary['adjustments']);
        $this->get(route('admin.reports.payments'))->assertOk()->assertViewHas('summary', fn ($s) => $s['credit']['gross'] === 0 && $s['credit']['pending'] === 10000);
        $this->post(route('admin.orders.settlement', $order), ['revision' => $order->revision, 'direction' => 'collect',
            'method' => 'cash', 'amount' => '100.00', 'note' => 'Customer paid later', 'received' => 1])->assertSessionHasNoErrors();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(0, $order->fresh()->balance_cents);
        $this->assertSame(0, app(CustomerInvoicing::class)->balances($customer)['used']);
    }

    public function test_credit_correction_checks_account_permissions_limit_and_order_state_atomically(): void
    {
        $this->actingAs($this->super());
        foreach (['no_account', 'zero_limit', 'over_limit', 'disabled', 'unpriced', 'cancelled', 'gateway'] as $scenario) {
            $customer = User::factory()->create(['account_type' => 'business'])->customerRecord();
            $customer->update(['credit_limit_cents' => $scenario === 'zero_limit' ? 0 : ($scenario === 'over_limit' ? 5000 : 20000),
                'credit_policy' => 'block', 'payment_permissions' => ['website' => ['credit' => $scenario !== 'disabled']]]);
            $order = $this->order(['customer_id' => $scenario === 'no_account' ? null : $customer->id,
                'payment_method' => 'cash', 'payment_status' => 'paid', 'manual_collected_cents' => 10000, 'paid_at' => now(),
                'status' => $scenario === 'cancelled' ? 'cancelled' : 'warehouse_pending', 'delivery_cents' => $scenario === 'unpriced' ? null : 0]);
            if ($scenario === 'gateway') {
                $this->gateway($order, ['status' => 'paid', 'paid_at' => now()]);
            }
            if ($scenario === 'no_account') {
                $this->get(route('admin.orders.payments', $order))->assertOk()->assertDontSee('value="credit">Customer Credit', false);
            }
            $before = $order->fresh()->getAttributes();
            $this->postJson(route('admin.orders.payment-adjustment', $order), $this->adjustment($order, [
                'action' => 'correct_method', 'method' => 'credit',
            ]))->assertUnprocessable();
            $this->assertSame($before, $order->fresh()->getAttributes(), $scenario);
            $this->assertDatabaseMissing('order_settlements', ['order_id' => $order->id]);
        }
    }

    public function test_credit_correction_increases_existing_credit_usage_only_by_the_reversed_collection(): void
    {
        $this->actingAs($this->super());
        $customer = User::factory()->create(['account_type' => 'business'])->customerRecord();
        $customer->update(['credit_limit_cents' => 20000, 'credit_policy' => 'block']);
        $this->order(['customer_id' => $customer->id, 'payment_method' => 'credit', 'subtotal_cents' => 9000, 'manual_collected_cents' => 0]);
        $order = $this->order(['customer_id' => $customer->id, 'payment_method' => 'credit', 'manual_collected_cents' => 0]);
        $source = $order->settlements()->create(['method' => 'cash', 'amount_cents' => 5000, 'note' => 'Earlier partial payment']);
        $this->assertSame(14000, app(CustomerInvoicing::class)->balances($customer)['used']);
        $this->post(route('admin.orders.payment-adjustment', $order), $this->adjustment($order, [
            'action' => 'correct_method', 'method' => 'credit', 'source' => (string) $source->id, 'amount' => '30.00',
        ]))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(2000, $order->net_received_cents);
        $this->assertSame(8000, $order->balance_cents);
        $this->assertSame(17000, app(CustomerInvoicing::class)->balances($customer)['used']);
        $this->postJson(route('admin.orders.payment-adjustment', $order), $this->adjustment($order, [
            'action' => 'correct_method', 'method' => 'credit', 'source' => (string) $source->id, 'amount' => '30.00',
        ]))->assertUnprocessable();
        $this->assertSame(8000, $order->fresh()->balance_cents);
    }

    public function test_credit_warning_policy_is_visible_after_correction_and_confirmation_is_required(): void
    {
        $this->actingAs($this->super());
        $customer = User::factory()->create(['account_type' => 'business'])->customerRecord();
        $customer->update(['credit_limit_cents' => 5000, 'credit_policy' => 'warn']);
        $order = $this->order(['customer_id' => $customer->id, 'payment_method' => 'cash', 'payment_status' => 'paid',
            'manual_collected_cents' => 10000, 'paid_at' => now()]);
        $data = $this->adjustment($order, ['action' => 'correct_method', 'method' => 'credit']);
        foreach (['reason', 'confirmed'] as $field) {
            $invalid = $data;
            unset($invalid[$field]);
            $this->postJson(route('admin.orders.payment-adjustment', $order), $invalid)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->post(route('admin.orders.payment-adjustment', $order), $data)->assertSessionHasNoErrors();
        $this->assertStringContainsString('Credit warning', $order->fresh()->credit_warning);
        $this->get(route('admin.orders.payments', $order))->assertOk()->assertSee('Credit warning');
    }
}
