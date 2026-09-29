<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class PaymentGateway
{
    public function __construct(private GatewayConfiguration $configuration) {}

    public function ready(string $provider): bool
    {
        return $this->configuration->enabled($provider) && $this->configuration->configured($provider, $this->configuration->mode());
    }

    private function assertMode(Payment $payment): void
    {
        if (! $this->configuration->configured($payment->provider, $payment->mode)) {
            throw new RuntimeException('Credentials for this payment environment are unavailable.');
        }
    }

    private function stripe(string $mode): PendingRequest
    {
        $credentials = $this->configuration->credentials('stripe', $mode);

        return Http::baseUrl('https://api.stripe.com/v1')->withToken($credentials['secret'])
            ->withHeaders(['Stripe-Version' => '2024-06-20'])->asForm()->acceptJson()->connectTimeout(8)->timeout(25);
    }

    private function paypal(string $mode): PendingRequest
    {
        $credentials = $this->configuration->credentials('paypal', $mode);
        $base = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
        $response = Http::withBasicAuth($credentials['client_id'], $credentials['secret'])->asForm()->acceptJson()->connectTimeout(8)->timeout(25)
            ->post($base.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('PayPal authentication unavailable.');
        }

        return Http::baseUrl($base)->withToken($response->json('access_token'))->acceptJson()->connectTimeout(8)->timeout(25);
    }

    public function testConnection(string $provider, string $mode): void
    {
        if (! $this->configuration->configured($provider, $mode)) {
            throw new RuntimeException('Complete saved credentials first.');
        }
        if ($provider === 'stripe') {
            $this->json($this->stripe($mode)->get('/balance'));
        } else {
            $this->paypal($mode);
        }
    }

    private function json($response): array
    {
        if (! $response->successful() || ! is_array($response->json())) {
            // Never include provider payloads, credentials or customer details in exception messages.
            throw new RuntimeException('The payment provider could not confirm this request.');
        }

        return $response->json();
    }

    public function create(Payment $payment): array
    {
        $this->assertMode($payment);
        $return = URL::temporarySignedRoute('payment.return', $payment->expires_at->copy()->addDay(), ['payment' => $payment->reference]);
        $cancel = URL::temporarySignedRoute('payment.cancel-return', $payment->expires_at->copy()->addDay(), ['payment' => $payment->reference]);
        if ($payment->provider === 'stripe') {
            $data = $this->json($this->stripe($payment->mode)->withHeaders(['Idempotency-Key' => 'checkout-'.$payment->reference])->post('/checkout/sessions', [
                'mode' => 'payment', 'payment_method_types' => ['card'], 'customer_creation' => 'if_required',
                'client_reference_id' => $payment->reference, 'metadata' => ['payment_reference' => $payment->reference],
                'payment_intent_data' => ['metadata' => ['payment_reference' => $payment->reference]],
                'success_url' => $return, 'cancel_url' => $cancel, 'expires_at' => $payment->expires_at->timestamp,
                'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'cad', 'unit_amount' => $payment->amount_cents,
                    'product_data' => ['name' => 'Mamma Mia Cucina order '.$payment->order->number.' (includes delivery and tax)']]]],
            ]));

            return ['id' => $data['id'], 'url' => $data['url']];
        }
        $data = $this->json($this->paypal($payment->mode)->withHeaders(['PayPal-Request-Id' => 'c-'.$payment->reference, 'Prefer' => 'return=representation'])
            ->post('/v2/checkout/orders', [
                'intent' => 'CAPTURE', 'purchase_units' => [['reference_id' => $payment->reference, 'custom_id' => $payment->reference,
                    'invoice_id' => $payment->order->number.'-'.$payment->reference, 'amount' => ['currency_code' => 'CAD', 'value' => number_format($payment->amount_cents / 100, 2, '.', '')]]],
                'payment_source' => ['paypal' => ['experience_context' => ['brand_name' => 'Mamma Mia Cucina', 'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW', 'return_url' => $return, 'cancel_url' => $cancel]]],
            ]));
        $url = collect($data['links'] ?? [])->first(fn ($link) => in_array($link['rel'], ['payer-action', 'approve']));
        if (! $url) {
            throw new RuntimeException('PayPal checkout link unavailable.');
        }

        return ['id' => $data['id'], 'url' => $url['href']];
    }

    public function retrieve(Payment $payment): array
    {
        $this->assertMode($payment);
        $id = rawurlencode($payment->provider_order_id);

        return $payment->provider === 'stripe'
            ? $this->json($this->stripe($payment->mode)->get('/checkout/sessions/'.$id, ['expand' => ['payment_intent.latest_charge']]))
            : $this->json($this->paypal($payment->mode)->get('/v2/checkout/orders/'.$id));
    }

    public function capture(Payment $payment): array
    {
        $this->assertMode($payment);

        return $this->json($this->paypal($payment->mode)->withHeaders(['PayPal-Request-Id' => 'p-'.$payment->reference, 'Prefer' => 'return=representation'])
            ->withBody('{}', 'application/json')->post('/v2/checkout/orders/'.rawurlencode($payment->provider_order_id).'/capture'));
    }

    public function expire(Payment $payment): void
    {
        $this->assertMode($payment);
        if ($payment->provider === 'stripe') {
            $this->json($this->stripe($payment->mode)->withHeaders(['Idempotency-Key' => 'expire-'.$payment->reference])
                ->post('/checkout/sessions/'.rawurlencode($payment->provider_order_id).'/expire'));
        }
        // PayPal Orders have no cancel endpoint. Capture is performed only by our locked server flow,
        // which refuses capture after this local payment has been released.
    }

    public function captureDetails(Payment $payment, string $id): array
    {
        $this->assertMode($payment);

        return $this->json($this->paypal($payment->mode)->get('/v2/payments/captures/'.rawurlencode($id)));
    }

    public function refund(Payment $payment): array
    {
        $this->assertMode($payment);
        if ($payment->provider === 'stripe') {
            $data = $this->json($this->stripe($payment->mode)->withHeaders(['Idempotency-Key' => 'refund-'.$payment->reference])
                ->post('/refunds', ['payment_intent' => $payment->transaction_id, 'amount' => $payment->amount_cents - $payment->refunded_cents]));

            return ['id' => $data['id'], 'status' => $data['status'], 'amount' => (int) $data['amount']];
        }
        $data = $this->json($this->paypal($payment->mode)->withHeaders(['PayPal-Request-Id' => 'r-'.$payment->reference])
            ->post('/v2/payments/captures/'.rawurlencode($payment->transaction_id).'/refund', [
                'amount' => ['currency_code' => 'CAD', 'value' => number_format(($payment->amount_cents - $payment->refunded_cents) / 100, 2, '.', '')],
            ]));

        return ['id' => $data['id'], 'status' => strtolower($data['status']), 'amount' => $this->cents($data['amount']['value'])];
    }

    public function verifyStripe(string $body, string $signature, ?string $mode = null): array
    {
        $mode ??= $this->configuration->mode();
        if (! $this->configuration->configured('stripe', $mode)) {
            throw new RuntimeException('Stripe webhook is not configured.');
        }
        $parts = [];
        foreach (explode(',', $signature) as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) === 2) {
                $parts[$pair[0]][] = $pair[1];
            }
        }
        $timestamp = $parts['t'][0] ?? '';
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            throw new RuntimeException('Invalid webhook timestamp.');
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $this->configuration->credentials('stripe', $mode)['webhook_secret']);
        if (! collect($parts['v1'] ?? [])->contains(fn ($value) => hash_equals($expected, $value))) {
            throw new RuntimeException('Invalid webhook signature.');
        }

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    public function verifyPaypal(array $event, array $headers, ?string $mode = null): bool
    {
        $mode ??= $this->configuration->mode();
        if (! $this->configuration->configured('paypal', $mode)) {
            return false;
        }
        $data = $this->json($this->paypal($mode)->post('/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $headers['paypal-auth-algo'] ?? '', 'cert_url' => $headers['paypal-cert-url'] ?? '',
            'transmission_id' => $headers['paypal-transmission-id'] ?? '', 'transmission_sig' => $headers['paypal-transmission-sig'] ?? '',
            'transmission_time' => $headers['paypal-transmission-time'] ?? '',
            'webhook_id' => $this->configuration->credentials('paypal', $mode)['webhook_id'], 'webhook_event' => $event,
        ]));

        return ($data['verification_status'] ?? '') === 'SUCCESS';
    }

    public function cents(string $amount): int
    {
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $amount)) {
            throw new RuntimeException('Invalid payment amount.');
        }
        $parts = explode('.', $amount);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }
}
