<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\OnlinePayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class OnlinePaymentsTest extends TestCase
{
    use RefreshDatabase;

    private string $stripeState = 'open';

    private int $refundAmount = 0;

    private bool $paypalCaptured = false;

    private bool $paypalApproved = false;

    private bool $paypalSignature = true;

    private bool $mismatch = false;

    private bool $outage = false;

    private string $captureStatus = 'COMPLETED';

    protected function setUp(): void
    {
        parent::setUp();
$this->actingAs(User::factory()->create(['email'=>'jane@example.test','account_type'=>'individual','phone'=>'555123','role_id'=>Role::where('name','Customer')->value('id'),'is_active'=>true]));
        config(['payments.mode' => 'sandbox', 'payments.stripe.enabled' => true, 'payments.stripe.secret' => 'sk_test_fake',
            'payments.stripe.webhook_secret' => 'whsec_test', 'payments.paypal.enabled' => true, 'payments.paypal.client_id' => 'fake-id',
            'payments.paypal.secret' => 'fake-secret', 'payments.paypal.webhook_id' => 'fake-webhook']);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if ($this->outage) {
                return Http::response([], 503);
            } $url = $request->url();
            if (str_contains($url, 'api.stripe.com')) {
                $payment = Payment::where('provider', 'stripe')->first();
                if (str_ends_with($url, '/checkout/sessions') && $request->method() === 'POST') {
                    return Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']);
                }
                if (str_ends_with($url, '/expire')) {
                    $this->stripeState = 'expired';

                    return Http::response(['status' => 'expired']);
                }
                if (str_ends_with($url, '/refunds')) {
                    $this->refundAmount = $payment->amount_cents;

                    return Http::response(['id' => 're_1', 'status' => 'succeeded', 'amount' => $payment->amount_cents]);
                }

                return Http::response(['id' => 'cs_test_1', 'client_reference_id' => $payment->reference, 'amount_total' => $this->mismatch ? 1 : $payment->amount_cents,
                    'currency' => 'cad', 'livemode' => false, 'status' => $this->stripeState,
                    'payment_status' => $this->stripeState === 'complete' ? 'paid' : 'unpaid',
                    'payment_intent' => ['id' => 'pi_1', 'latest_charge' => ['id' => 'ch_1', 'amount_refunded' => $this->refundAmount]]]);
            }
            if (str_ends_with($url, '/v1/oauth2/token')) {
                return Http::response(['access_token' => 'fake-token']);
            }
            if (str_ends_with($url, '/verify-webhook-signature')) {
                return Http::response(['verification_status' => $this->paypalSignature ? 'SUCCESS' : 'FAILURE']);
            }
            $payment = Payment::where('provider', 'paypal')->first();
            if (str_ends_with($url, '/v2/checkout/orders') && $request->method() === 'POST') {
                return Http::response(['id' => 'PPORDER1', 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PPORDER1']]]);
            }
            if (str_ends_with($url, '/capture')) {
                $this->paypalCaptured = true;
            }
            if (str_ends_with($url, '/refund')) {
                $this->refundAmount = $payment->amount_cents;

                return Http::response(['id' => 'PPREFUND1', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'CAD', 'value' => number_format($payment->amount_cents / 100, 2, '.', '')]]);
            }
            if (str_contains($url, '/v2/payments/captures/')) {
                return Http::response(['id' => 'PPCAPTURE1', 'status' => $this->refundAmount === $payment->amount_cents ? 'REFUNDED' : $this->captureStatus,
                    'amount' => ['currency_code' => 'CAD', 'value' => number_format($payment->amount_cents / 100, 2, '.', '')]]);
            }

            return Http::response(['id' => 'PPORDER1', 'status' => $this->paypalCaptured ? 'COMPLETED' : ($this->paypalApproved ? 'APPROVED' : 'CREATED'),
                'purchase_units' => [['custom_id' => $payment->reference, 'amount' => ['currency_code' => 'CAD', 'value' => number_format($payment->amount_cents / 100, 2, '.', '')],
                    'payments' => ['captures' => $this->paypalCaptured ? [['id' => 'PPCAPTURE1']] : []]]]]);
        });
    }

    private function checkout(string $method = 'card', bool $legacy = true): array
    {
        $product = Product::create(['category_id' => 1, 'slug' => 'payment-cake', 'premium_marketing_name' => 'Payment Cake', 'qr_code' => 'PAY', 'is_active' => true, 'total_selling_price_cad' => 10]);
        $product->inventory()->create(['quantity_on_hand' => 10]);
        $this->postJson(route('cart.add', $product), ['quantity' => 2])->assertOk();
        $this->get('/checkout')->assertOk()->assertSee('Pay by card')->assertSee('Pay with PayPal');
        $data = ['checkout_token' => session('checkout_token'), 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.test',
            'phone' => '555123', 'address' => '10 Test Street', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M1M1M1', 'country' => 'Canada',
            'payment_method' => $method, 'subtotal_cents' => 1, 'payment_status' => 'paid'];
        // New checkout disables gateways. Exercise historical gateway orders independently.
        if ($legacy) {
            $this->post('/checkout', array_replace($data, ['payment_method'=>'cash']))->assertRedirect('/order-success');
            $order = Order::firstOrFail();
            $order->update(['payment_method'=>$method, 'payment_status'=>'pending', 'status'=>'awaiting_payment', 'warehouse_round'=>0, 'warehouse_sent_at'=>null]);
            $payment = app(OnlinePayments::class)->initialize($order);
            $response = $this->post(route('payment.start', $order->fresh()->payment->reference));
        } else {
            $response = $this->post('/checkout', $data);
        }

        return [$product, $data, $response];
    }

    private function admin(array $permissions = []): User
    {
        $role = $permissions ? Role::create(['name' => 'Limited payment staff', 'permissions' => $permissions]) : Role::where('is_super', true)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($user);

        return $user;
    }

    private function stripeEvent(string $id = 'evt_1', bool $valid = true)
    {
        $payment = Payment::firstOrFail();
        $body = json_encode(['id' => $id, 'type' => 'checkout.session.completed', 'livemode' => false,
            'data' => ['object' => ['object' => 'checkout.session', 'id' => 'cs_test_1', 'client_reference_id' => $payment->reference]]]);
        $timestamp = time();
        $signature = $valid ? hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test') : 'incorrect';

        return $this->call('POST', '/payments/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature], $body);
    }

    public function test_legacy_hosted_card_checkout_is_pending_without_card_storage(): void
    {
        [$product,$data,$response] = $this->checkout();
        $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');
        $order = Order::firstOrFail();
        $payment = $order->payment;
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame(2000, $payment->amount_cents);
        $this->assertSame('8.000', $product->inventory()->first()->quantity_on_hand);
        $this->get(route('payment.show', $payment->reference))->assertOk();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->get('/order-success')->assertRedirect(route('payment.show', $payment->reference));
        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/checkout/sessions') && $request['mode'] === 'payment'
                && $request['payment_method_types'] === ['card'] && $request['customer_creation'] === 'if_required'
                && ! isset($request['payment_intent_data']['setup_future_usage'])
                && $request->hasHeader('Idempotency-Key');
        });
    }

    public function test_verified_webhook_marks_paid_once_and_rejects_forgery(): void
    {
        [$product] = $this->checkout();
        $this->stripeState = 'complete';
        $this->stripeEvent('evt_bad', false)->assertStatus(400);
        $this->assertSame('pending', Order::firstOrFail()->payment_status);
        $this->stripeEvent()->assertOk();
        $this->stripeEvent()->assertOk();
        $order = Order::firstOrFail();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('warehouse_pending', $order->status);
        $this->assertSame('pi_1', $order->payment->transaction_id);
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame('8.000', $product->inventory()->first()->quantity_on_hand);
        $this->get('/order-success')->assertOk()->assertSee('Payment Cake');
    }

    public function test_amount_mismatch_is_not_acknowledged_or_marked_paid(): void
    {
        $this->checkout();
        $this->stripeState = 'complete';
        $this->mismatch = true;
        $this->stripeEvent()->assertStatus(503);
        $this->assertSame('pending', Order::firstOrFail()->payment_status);
        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_return_signature_and_provider_verification_are_required(): void
    {
        $this->checkout();
        $payment = Payment::firstOrFail();
        $this->get(route('payment.return', $payment->reference))->assertForbidden();
        $url = URL::temporarySignedRoute('payment.return', now()->addHour(), ['payment' => $payment->reference]);
        $this->get($url)->assertRedirect(route('payment.show', $payment->reference));
        $this->assertSame('pending', $payment->order->fresh()->payment_status);
        session()->forget('last_order_id');
        $this->get(route('payment.show', $payment->reference))->assertNotFound();
    }

    public function test_cancel_releases_stock_once_only_after_gateway_confirmation(): void
    {
        [$product] = $this->checkout();
        $payment = Payment::firstOrFail();
        $this->post(route('payment.cancel', $payment->reference))->assertRedirect();
        $this->assertSame('cancelled', $payment->order->fresh()->status);
        $this->assertSame('10.000', $product->inventory()->first()->quantity_on_hand);
        app(OnlinePayments::class)->sync($payment, false, true);
        $this->assertSame('10.000', $product->inventory()->first()->quantity_on_hand);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_gateway_outage_does_not_release_potentially_paid_stock(): void
    {
        [$product] = $this->checkout();
        $payment = Payment::firstOrFail();
        $this->outage = true;
        $this->post(route('payment.cancel', $payment->reference))->assertSessionHasErrors('payment');
        $this->assertSame('8.000', $product->inventory()->first()->quantity_on_hand);
        $this->assertNull($payment->fresh()->released_at);
    }

    public function test_late_payment_requires_review_and_does_not_double_deduct(): void
    {
        [$product] = $this->checkout();
        $payment = Payment::firstOrFail();
        app(OnlinePayments::class)->sync($payment, false, true);
        $this->stripeState = 'complete';
        $this->stripeEvent()->assertOk();
        $this->assertSame('payment_review', $payment->order->fresh()->status);
        $this->assertSame('paid', $payment->order->fresh()->payment_status);
        $this->assertSame('10.000', $product->inventory()->first()->quantity_on_hand);
        $this->get('/order-success')->assertRedirect(route('payment.show', $payment->reference));
    }

    public function test_paypal_redirect_capture_and_cancel(): void
    {
        [$product,,$response] = $this->checkout('paypal');
        $response->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=PPORDER1');
        $payment = Payment::firstOrFail();
        $this->paypalApproved = true;
        $url = URL::temporarySignedRoute('payment.return', now()->addHour(), ['payment' => $payment->reference]);
        $this->get($url.'&token=PPORDER1&PayerID=BUYER')->assertRedirect();
        $this->assertSame('paid', $payment->order->fresh()->payment_status);
        $this->assertSame('PPCAPTURE1', $payment->fresh()->transaction_id);
        app(OnlinePayments::class)->sync($payment, true);
        $this->assertSame('8.000', $product->inventory()->first()->quantity_on_hand);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/capture') && $request->hasHeader('PayPal-Request-Id'));
    }

    public function test_expired_paypal_approval_cannot_capture_and_releases_stock(): void
    {
        [$product] = $this->checkout('paypal');
        $payment = Payment::firstOrFail();
        $this->paypalApproved = true;
        $this->travel(61)->minutes();
        app(OnlinePayments::class)->sync($payment, true);
        $this->assertSame('10.000', $product->inventory()->first()->quantity_on_hand);
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/capture'));
        $this->travelBack();
    }

    public function test_full_refund_restores_unfulfilled_stock_once_and_reports_exclude_sandbox(): void
    {
        [$product] = $this->checkout();
        $payment = Payment::firstOrFail();
        $this->stripeState = 'complete';
        $this->stripeEvent()->assertOk();
        $this->admin();
        $this->get('/admin/reports/payments')->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 0);
        $this->get('/admin/reports/payments?sandbox=1')->assertOk()->assertViewHas('summary', fn ($s) => $s['card']['gross'] === 2000);
        $this->post(route('admin.payments.refund', $payment), ['confirm' => 'REFUND', 'expected_cents' => 2000])->assertSessionHasNoErrors();
        $this->assertSame('refunded', $payment->order->fresh()->payment_status);
        $this->assertSame('10.000', $product->inventory()->first()->quantity_on_hand);
        $this->post(route('admin.payments.refund', $payment), ['confirm' => 'REFUND', 'expected_cents' => 2000])->assertSessionHasNoErrors();
        $this->assertSame('10.000', $product->inventory()->first()->quantity_on_hand);
        $this->get('/admin/reports/payments?sandbox=1')->assertViewHas('summary', fn ($s) => $s['card']['refunded'] === 2000);
        $this->get('/admin/reports/payments/export?sandbox=1')->assertOk()->assertDownload();
    }

    public function test_manual_payment_and_unauthorized_refund_are_blocked(): void
    {
        $this->checkout();
        $order = Order::firstOrFail();
        $this->admin(['orders.view', 'orders.manage']);
        $this->patch(route('admin.orders.update', $order), ['revision' => 0, 'status' => 'placed', 'payment_status' => 'paid', 'delivery' => '0', 'tax' => '0'])->assertSessionHasErrors('order');
        $this->post(route('admin.payments.refund', $order->payment), ['confirm' => 'REFUND', 'expected_cents' => 2000])->assertForbidden();
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Online payment');
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_disabled_gateway_is_rejected_without_order_or_stock_change(): void
    {
        config(['payments.stripe.enabled' => false]);
        [$product,,$response] = $this->checkout('card', false);
        $response->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame('10.000', $product->inventory()->first()->quantity_on_hand);
    }

    public function test_paypal_webhook_verification_capture_and_duplicate_refund(): void
    {
        [$product] = $this->checkout('paypal');
        $payment = Payment::firstOrFail();
        $this->paypalApproved = true;
        $event = ['id' => 'WH-APPROVED', 'event_type' => 'CHECKOUT.ORDER.APPROVED', 'resource' => ['id' => 'PPORDER1']];
        $this->paypalSignature = false;
        $this->postJson('/payments/webhooks/paypal', $event)->assertStatus(400);
        $this->assertSame('pending', $payment->fresh()->status);
        $this->paypalSignature = true;
        $this->postJson('/payments/webhooks/paypal', $event)->assertOk();
        $this->assertSame('paid', $payment->fresh()->status);
        $refund = ['id' => 'WH-REFUND', 'event_type' => 'PAYMENT.CAPTURE.REFUNDED', 'resource' => [
            'id' => 'PPREFUND1', 'status' => 'COMPLETED', 'amount' => ['value' => '5.00', 'currency_code' => 'CAD'],
            'supplementary_data' => ['related_ids' => ['capture_id' => 'PPCAPTURE1']]]];
        $this->postJson('/payments/webhooks/paypal', $refund)->assertOk();
        $this->postJson('/payments/webhooks/paypal', $refund)->assertOk();
        $this->assertSame(500, $payment->fresh()->refunded_cents);
        $this->assertSame('partially_refunded', $payment->order->fresh()->payment_status);
        $this->assertSame('8.000', $product->inventory()->first()->quantity_on_hand);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/capture') && strlen($r->header('PayPal-Request-Id')[0]) <= 38);
    }

    public function test_scheduler_expires_abandoned_checkout_and_preserves_failed_reconciliation(): void
    {
        [$product] = $this->checkout();
        $payment = Payment::firstOrFail();
        $this->travel(61)->minutes();
        $this->outage = true;
        $this->artisan('payments:reconcile')->assertExitCode(1);
        $this->assertNull($payment->fresh()->released_at);
        $this->outage = false;
        $this->artisan('payments:reconcile')->assertExitCode(0);
        $this->assertSame('expired', $payment->fresh()->status);
        $this->assertSame('10.000', $product->inventory()->first()->quantity_on_hand);
        $this->travelBack();
    }

    public function test_refunded_delivered_order_does_not_restock_or_show_amount_due(): void
    {
        [$product] = $this->checkout();
        $payment = Payment::firstOrFail();
        $this->stripeState = 'complete';
        app(OnlinePayments::class)->sync($payment);
        $payment->order->update(['status' => 'delivered']);
        $this->refundAmount = $payment->amount_cents;
        app(OnlinePayments::class)->sync($payment);
        $this->assertSame('delivered', $payment->order->fresh()->status);
        $this->assertSame('8.000', $product->inventory()->first()->quantity_on_hand);
        $this->admin();
        $this->get(route('admin.orders.print', $payment->order))->assertOk()->assertSee('Amount due: $0.00')->assertDontSee('external terminal')->assertSee('pi_1');
    }

    public function test_pending_paypal_capture_holds_stock_even_after_expiry_until_settled(): void
    {
        [$product] = $this->checkout('paypal');
        $payment = Payment::firstOrFail();
        $this->paypalApproved = true;
        $this->captureStatus = 'PENDING';
        app(OnlinePayments::class)->sync($payment, true);
        $this->travel(61)->minutes();
        app(OnlinePayments::class)->sync($payment, false, true);
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertNull($payment->fresh()->released_at);
        $this->assertSame('8.000', $product->inventory()->first()->quantity_on_hand);
        $this->captureStatus = 'COMPLETED';
        app(OnlinePayments::class)->sync($payment);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('warehouse_pending', $payment->order->fresh()->status);
        $this->travelBack();
    }

    public function test_declined_paypal_capture_releases_stock_once(): void
    {
        [$product] = $this->checkout('paypal');
        $payment = Payment::firstOrFail();
        $this->paypalApproved = true;
        $this->captureStatus = 'DECLINED';
        app(OnlinePayments::class)->sync($payment, true);
        app(OnlinePayments::class)->sync($payment, true);
        $this->assertSame('unpaid', $payment->order->fresh()->payment_status);
        $this->assertSame('cancelled', $payment->order->fresh()->status);
        $this->assertSame('10.000', $product->inventory()->first()->quantity_on_hand);
    }

    public function test_creation_outage_can_retry_same_order_without_duplicate_stock_deduction(): void
    {
        $this->outage = true;
        [$product,,$response] = $this->checkout();
        $payment = Payment::firstOrFail();
        $response->assertSessionHasErrors('payment');
        $this->assertSame('creating', $payment->status);
        $this->outage = false;
        $this->post(route('payment.start', $payment->reference))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('8.000', $product->inventory()->first()->quantity_on_hand);
        $keys = Http::recorded(fn ($request) => str_ends_with($request->url(), '/checkout/sessions'))->map(fn ($pair) => $pair[0]->header('Idempotency-Key')[0])->unique();
        $this->assertCount(1,$keys);
    }
}
