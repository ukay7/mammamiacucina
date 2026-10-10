<?php

namespace Tests\Feature;

use App\Models\{GatewaySetting, GeneralSetting, Order, Payment, Product, Role, User};
use App\Services\{HelcimGateway, OnlinePayments};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Tests\TestCase;

class HelcimPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private array $transactions = [];
    private bool $outage = false;
    private bool $refundTimeout = false;

    protected function setUp(): void
    {
        parent::setUp();
        GatewaySetting::forceCreate(['id' => 1, 'mode' => 'sandbox', 'helcim_enabled' => true,
            'credentials' => ['sandbox' => ['helcim' => ['api_token' => 'test-secret-token', 'webhook_secret' => base64_encode('test-verifier')]]]]);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if ($this->outage) return Http::response([], 503);
            if (str_ends_with($request->url(), '/connection-test')) return Http::response(['message' => 'Connected Successfully']);
            if (str_ends_with($request->url(), '/helcim-pay/initialize')) {
                return Http::response(['checkoutToken' => 'checkout-test-token-12345', 'secretToken' => 'never-store-this-secret']);
            }
            if (str_ends_with($request->url(), '/payment/refund')) {
                $row = $this->row('refund', (string) $request['amount'], (string) (200 + count($this->transactions)));
                $this->transactions[] = $row;
                return Http::response($row, $this->refundTimeout ? 503 : 200);
            }
            if (preg_match('~/card-transactions/([0-9]+)$~', $request->url(), $match)) {
                return Http::response(collect($this->transactions)->firstWhere('transactionId', $match[1]) ?? [], 200);
            }
            if (str_contains($request->url(), '/card-transactions?')) return Http::response($this->transactions);
            throw new \RuntimeException('Unexpected test request: '.$request->url());
        });
    }

    private function checkout(string $fulfillment = 'pickup', string $type = 'individual'): array
    {
        $user = User::factory()->create(['account_type' => $type, 'business_approved_at' => $type === 'business' ? now() : null, 'is_active' => true, 'role_id' => Role::where('name', 'Customer')->value('id')]);
        $this->actingAs($user);
        GeneralSetting::findOrFail(1)->update(['pickup_address' => '123 Test Street', 'delivery_cents' => 500, 'tax_basis_points' => 500]);
        $product = Product::create(['category_id' => 1, 'slug' => 'helcim-cake', 'premium_marketing_name' => 'Helcim Cake', 'qr_code' => 'HEL-CARD', 'is_active' => true, 'total_selling_price_cad' => 10, 'business_selling_price_cad' => 6]);
        $product->inventory()->create(['quantity_on_hand' => 10]);
        $this->postJson(route('cart.add', $product), ['quantity' => 2])->assertOk();
        $this->get('/checkout')->assertOk()->assertSee('Pay by card');
        $data = ['checkout_token' => session('checkout_token'), 'fulfillment' => $fulfillment, 'payment_method' => 'card',
            'first_name' => 'Test', 'last_name' => 'Customer', 'email' => $user->email, 'phone' => '123',
            'address' => '45 Test Street', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M1P 1A1', 'country' => 'Canada'];
        $response = $this->post('/checkout', $data);
        return [$product, $data, $response];
    }

    private function row(string $type = 'purchase', string $amount = '21.00', string $id = '101'): array
    {
        return ['transactionId' => $id, 'invoiceNumber' => app(HelcimGateway::class)->invoiceNumber(Payment::firstOrFail()),
            'status' => 'APPROVED', 'type' => $type, 'currency' => 'CAD', 'amount' => $amount];
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true, 'role_id' => Role::where('is_super', true)->value('id')]));
    }

    private function webhook(string $id = 'message_1', bool $valid = true, int $age = 0)
    {
        $body = json_encode(['id' => '101', 'type' => 'cardTransaction']);
        $timestamp = (string) (time() - $age);
        $signature = base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$body, $valid ? 'test-verifier' : 'wrong', true));
        return $this->call('POST', '/payments/card-events?mode=sandbox', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_WEBHOOK_ID' => $id, 'HTTP_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_WEBHOOK_SIGNATURE' => 'v1,'.$signature,
        ], $body);
    }

    public function test_checkout_retries_reuse_one_order_and_modal_without_exposing_secrets(): void
    {
        [$product, $data, $response] = $this->checkout();
        $payment = Payment::firstOrFail();
        $response->assertRedirect(route('payment.show', $payment->reference));
        $this->assertSame('helcim', $payment->provider);
        $this->assertSame('awaiting_payment', $payment->order->status);
        $this->assertSame('pending', $payment->order->payment_status);
        $this->get(route('payment.show', $payment->reference))->assertOk()->assertSee('helcim-pay-button')->assertDontSee('test-secret-token')->assertDontSee('never-store-this-secret');
        $this->post('/checkout', $data)->assertRedirect(route('payment.show', $payment->reference));
        $this->post(route('payment.start', $payment->reference))->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame('8.000', $product->inventory->fresh()->quantity_on_hand);
        $this->assertStringNotContainsString('checkout-test-token', DB::table('payments')->value('checkout_token'));
        $this->assertArrayNotHasKey('checkout_token', $payment->toArray());
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['amount'] === 21 && $request['paymentMethod'] === 'cc' && $request['allowPartial'] === 0 && $request['invoiceRequest']['tax']['amount'] === 1);
    }

    public function test_verified_payment_moves_once_to_warehouse_and_appears_in_invoice_and_ledger(): void
    {
        [$product] = $this->checkout();
        $payment = Payment::firstOrFail();
        $this->transactions = [$this->row()];
        $this->postJson(route('payment.card-confirm', $payment->reference), ['transaction_id' => '101'])->assertOk()->assertJson(['paid' => true]);
        $this->webhook()->assertOk();
        $this->webhook()->assertOk();
        $order = $payment->order->fresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('warehouse_pending', $order->status);
        $this->assertSame(2100, $order->collected_cents);
        $this->assertSame(0, $order->balance_cents);
        $this->assertSame(1, $order->events()->count());
        $this->assertDatabaseCount('payment_events', 1);
        $this->get(route('payment.show', $payment->reference))->assertRedirect('/order-success');
        $this->get('/order-print')->assertOk()->assertSee('Helcim invoice:')->assertSee('101');
        $this->admin();
        $this->get(route('admin.orders.payments', $order))->assertOk()->assertSee('+$21.00')->assertSee('Invoice / receipt');
        $this->get('/admin/reports/payments?sandbox=1')->assertOk()->assertViewHas('summary', fn ($summary) => $summary['card']['gross'] === 2100);
    }

    public function test_bad_signatures_wrong_invoice_amount_and_foreign_owner_cannot_confirm(): void
    {
        $this->checkout();
        $payment = Payment::firstOrFail();
        $this->transactions = [$this->row()];
        $this->webhook('bad', false)->assertStatus(400);
        $this->webhook('old', true, 400)->assertStatus(400);
        $this->transactions[0]['invoiceNumber'] = 'OTHER';
        $this->postJson(route('payment.card-confirm', $payment->reference), ['transaction_id' => '101'])->assertStatus(422);
        $this->transactions = [$this->row('purchase', '0.01')];
        $this->postJson(route('payment.card-confirm', $payment->reference), ['transaction_id' => '101'])->assertStatus(503);
        $this->assertNull($payment->fresh()->paid_at);
        session()->forget('last_order_id');
        $this->actingAs(User::factory()->create());
        $this->postJson(route('payment.card-confirm', $payment->reference), ['transaction_id' => '101'])->assertNotFound();
        $this->get(route('payment.show', $payment->reference))->assertNotFound();
    }

    public function test_cancellation_waits_for_token_expiry_and_outage_never_releases_stock(): void
    {
        [$product] = $this->checkout();
        $payment = Payment::firstOrFail();
        $this->post(route('payment.cancel', $payment->reference))->assertRedirect();
        $this->assertNotNull($payment->fresh()->cancel_requested_at);
        $this->assertNull($payment->fresh()->released_at);
        $this->travel(71)->minutes();
        $this->outage = true;
        $this->artisan('payments:reconcile')->assertExitCode(1);
        $this->assertNull($payment->fresh()->released_at);
        $this->outage = false;
        $this->artisan('payments:reconcile')->assertExitCode(0);
        app(OnlinePayments::class)->sync($payment);
        $this->assertSame('10.000', $product->inventory->fresh()->quantity_on_hand);
        $this->assertSame('cancelled', $payment->order->fresh()->status);
        $this->transactions = [$this->row()];
        app(OnlinePayments::class)->sync($payment);
        $this->assertSame('payment_review', $payment->order->fresh()->status);
        $this->assertSame('10.000', $product->inventory->fresh()->quantity_on_hand);
    }

    public function test_partial_full_refunds_and_timeout_reconciliation_do_not_duplicate_money(): void
    {
        $this->checkout();
        $payment = Payment::firstOrFail();
        $this->transactions = [$this->row()];
        app(OnlinePayments::class)->sync($payment);
        $this->admin();
        $this->post(route('admin.payments.refund', $payment), ['confirm' => 'REFUND', 'expected_cents' => 2100, 'amount' => '5.00'])->assertSessionHasNoErrors();
        $this->assertSame(500, $payment->fresh()->refunded_cents);
        $this->assertSame('partially_refunded', $payment->order->fresh()->payment_status);
        $this->assertDatabaseCount('payment_refunds', 1);
        $this->refundTimeout = true;
        $this->post(route('admin.payments.refund', $payment), ['confirm' => 'REFUND', 'expected_cents' => 1600, 'amount' => '16.00'])->assertSessionHasErrors('payment');
        $this->refundTimeout = false;
        app(OnlinePayments::class)->sync($payment);
        $this->assertSame(2100, $payment->fresh()->refunded_cents);
        $this->assertSame('refunded', $payment->order->fresh()->payment_status);
        $this->post(route('admin.payments.refund', $payment), ['confirm' => 'REFUND', 'expected_cents' => 1600, 'amount' => '16.00'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payment_refunds', 2);
        $refunds = Http::recorded(fn ($request) => str_ends_with($request->url(), '/payment/refund'))->values();
        $this->assertCount(2, $refunds);
        $this->assertNotSame($refunds[0][0]->header('idempotency-key'), $refunds[1][0]->header('idempotency-key'));
    }

    public function test_disabled_gateway_rejects_card_before_deducting_stock(): void
    {
        GatewaySetting::find(1)->update(['helcim_enabled' => false]);
        [$product, , $response] = $this->checkout();
        $response->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame('10.000', $product->inventory->fresh()->quantity_on_hand);
    }

    public function test_decline_stays_unpaid_and_closed_browser_is_reconciled_by_scheduler(): void
    {
        $this->checkout('delivery');
        $payment = Payment::firstOrFail();
        $this->transactions = [array_merge($this->row('purchase', '26.00'), ['status' => 'DECLINED'])];
        app(OnlinePayments::class)->sync($payment);
        $this->assertNull($payment->fresh()->paid_at);
        $this->transactions = [$this->row('purchase', '26.00')];
        $this->artisan('payments:reconcile')->assertExitCode(0);
        $this->assertSame('paid', $payment->order->fresh()->payment_status);
        $this->assertSame(2600, $payment->order->fresh()->collected_cents);
    }

    public function test_settings_mask_helcim_secrets_and_test_without_charging(): void
    {
        $this->admin();
        $this->get(route('admin.gateways.edit'))->assertOk()->assertSee('Helcim')->assertSee('/payments/card-events')->assertDontSee('test-secret-token');
        $this->put(route('admin.gateways.update'), ['revision' => 0, 'mode' => 'sandbox', 'stripe_enabled' => 0, 'paypal_enabled' => 0, 'helcim_enabled' => 1,
            'credentials' => ['sandbox' => ['helcim' => ['api_token' => '', 'webhook_secret' => '']]]])->assertSessionHasNoErrors();
        $this->assertSame('test-secret-token', GatewaySetting::find(1)->credentials['sandbox']['helcim']['api_token']);
        $this->post(route('admin.gateways.test', ['helcim', 'sandbox']))->assertSessionHasNoErrors();
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_business_prices_are_used_and_creation_failure_retries_the_same_order(): void
    {
        $this->outage = true;
        [$product, , $response] = $this->checkout('delivery', 'business');
        $response->assertSessionHasErrors('payment');
        $payment = Payment::firstOrFail();
        $this->assertSame(1760, $payment->amount_cents);
        $this->assertSame('creating', $payment->status);
        $this->outage = false;
        $this->post(route('payment.start', $payment->reference))->assertRedirect();
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame('8.000', $product->inventory->fresh()->quantity_on_hand);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/helcim-pay/initialize') && $request['amount'] === 17.6);
    }

    public function test_duplicate_purchase_requires_review_and_customer_cannot_refund(): void
    {
        $this->checkout();
        $payment = Payment::firstOrFail();
        $this->transactions = [$this->row(), $this->row('purchase', '21.00', '102')];
        app(OnlinePayments::class)->sync($payment);
        $this->assertSame('payment_review', $payment->order->fresh()->status);
        $this->assertNull($payment->fresh()->paid_at);
        $this->post(route('admin.payments.refund', $payment), ['confirm' => 'REFUND', 'expected_cents' => 2100])->assertRedirect(route('customer.orders'));
        $role = Role::create(['name' => 'Refund restricted tester', 'permissions' => ['orders.view', 'orders.manage']]);
        $this->actingAs(User::factory()->create(['is_active' => true, 'role_id' => $role->id]));
        $this->post(route('admin.payments.refund', $payment), ['confirm' => 'REFUND', 'expected_cents' => 2100])->assertForbidden();
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/payment/refund'));
    }

    public function test_oversized_stale_refund_and_wrong_currency_are_rejected(): void
    {
        $this->checkout();
        $payment = Payment::firstOrFail();
        $this->transactions = [array_merge($this->row(), ['currency' => 'USD'])];
        $this->postJson(route('payment.card-confirm', $payment->reference), ['transaction_id' => '101'])->assertStatus(503);
        $this->assertNull($payment->fresh()->paid_at);
        $this->transactions = [$this->row()];
        app(OnlinePayments::class)->sync($payment);
        $this->admin();
        foreach ([['expected_cents' => 2100, 'amount' => '22.00'], ['expected_cents' => 2200, 'amount' => '5.00']] as $data) {
            $this->post(route('admin.payments.refund', $payment), $data + ['confirm' => 'REFUND'])->assertSessionHasErrors('payment');
        }
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/payment/refund'));
        $this->assertSame(0, $payment->fresh()->refunded_cents);
    }

    public function test_reduced_order_shows_refund_due_until_actual_gateway_refund_and_keeps_each_negative_entry(): void
    {
        $this->checkout();
        $payment = Payment::firstOrFail();
        $this->transactions = [$this->row()];
        app(OnlinePayments::class)->sync($payment);
        // Simulate the totals produced by the separately tested order amendment service.
        $payment->order->update(['subtotal_cents' => 1000, 'tax_cents' => 50]);
        $this->admin();
        $this->get(route('admin.orders.print', $payment->order))->assertOk()->assertSee('Refund due: $10.50')->assertSee('Net received: $21.00');
        $this->assertDatabaseCount('payment_refunds', 0);
        $this->post(route('admin.payments.refund', $payment), ['confirm' => 'REFUND', 'expected_cents' => 2100, 'amount' => '5.00'])->assertSessionHasNoErrors();
        $this->post(route('admin.payments.refund', $payment), ['confirm' => 'REFUND', 'expected_cents' => 1600, 'amount' => '5.50'])->assertSessionHasNoErrors();
        $this->assertSame(0, $payment->order->fresh()->balance_cents);
        $this->get(route('admin.orders.payments', $payment->order))->assertOk()->assertSee('−$5.00')->assertSee('−$5.50')->assertSee('+$21.00');
        $this->get(route('admin.orders.print', $payment->order))->assertOk()->assertSee('Amount due: $0.00')->assertSee('Net received: $10.50');
    }
}
