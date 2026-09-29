<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\GatewayConfiguration;
use App\Services\OnlinePayments;
use App\Services\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    private function owned(Payment $payment): void
    {
        abort_unless((int) session('last_order_id') === $payment->order_id, 404);
    }

    public function show(Payment $payment)
    {
        $this->owned($payment);
        if ($payment->order->payment_status === 'paid' && $payment->order->status !== 'payment_review') {
            return redirect()->route('theme.order-success');
        }

        return response()->view('pages.payment', compact('payment'))->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function start(Payment $payment, OnlinePayments $payments)
    {
        $this->owned($payment);
        try {
            $payment = $payments->start($payment);
            if ($payment->checkout_url && ! $payment->paid_at && ! $payment->released_at && ! $payment->expires_at->isPast()) {
                return redirect()->away($payment->checkout_url);
            }
            $payments->sync($payment);
        } catch (\Throwable $e) {
            return redirect()->route('payment.show', $payment->reference)->withErrors(['payment' => 'Payment checkout is temporarily unavailable. Please retry this same order; do not place a replacement order.']);
        }

        return redirect()->route('payment.show', $payment->reference);
    }

    public function returned(Request $request, Payment $payment, OnlinePayments $payments)
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['token', 'PayerID']), 403);
        if ($payment->provider === 'paypal' && $request->filled('token')) {
            abort_unless(hash_equals((string) $payment->provider_order_id, (string) $request->query('token')), 400);
        }
        session(['last_order_id' => $payment->order_id, 'last_order_token' => $payment->order->checkout_token]);
        try {
            $payments->sync($payment, true);
        } catch (\Throwable $e) {
            return redirect()->route('payment.show', $payment->reference)->withErrors(['payment' => 'We are waiting for payment confirmation. Do not pay again. Use Check payment status below.']);
        }

        return redirect()->route('payment.show', $payment->reference);
    }

    public function cancelled(Request $request, Payment $payment)
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['token', 'PayerID']), 403);
        session(['last_order_id' => $payment->order_id, 'last_order_token' => $payment->order->checkout_token]);

        return redirect()->route('payment.show', $payment->reference)->with('status', 'You returned without completing checkout. Resume payment, or cancel this order below.');
    }

    public function check(Payment $payment, OnlinePayments $payments)
    {
        $this->owned($payment);
        try {
            $payments->sync($payment, true);
        } catch (\Throwable $e) {
            return back()->withErrors(['payment' => 'The gateway has not confirmed a final status yet. Please check again shortly.']);
        }

        return redirect()->route('payment.show', $payment->reference);
    }

    public function cancel(Payment $payment, OnlinePayments $payments)
    {
        $this->owned($payment);
        try {
            if (! $payment->provider_order_id && ! $payment->expires_at->isPast()) {
                $payment = $payments->start($payment);
            }
            $payments->sync($payment, false, true);
        } catch (\Throwable $e) {
            return back()->withErrors(['payment' => 'Cancellation could not be confirmed. Your stock remains reserved while we check with the gateway.']);
        }

        return redirect()->route('payment.show', $payment->reference);
    }

    public function webhook(Request $request, string $provider, PaymentGateway $gateway, OnlinePayments $payments)
    {
        abort_unless(in_array($provider, ['stripe', 'paypal'], true), 404);
        $mode = $request->query('mode', app(GatewayConfiguration::class)->mode());
        abort_unless(in_array($mode, ['sandbox', 'live'], true), 400);
        try {
            if ($provider === 'stripe') {
                $event = $gateway->verifyStripe($request->getContent(), $request->header('Stripe-Signature', ''), $mode);
                if (($event['livemode'] ?? null) !== ($mode === 'live')) {
                    return response('Mode mismatch', 400);
                }
            } else {
                $event = $request->json()->all();
                $headers = [];
                foreach (['paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time'] as $key) {
                    $headers[$key] = $request->header($key, '');
                }
                if (! $gateway->verifyPaypal($event, $headers, $mode)) {
                    return response('Invalid signature', 400);
                }
            }
        } catch (\Throwable $e) {
            return response('Webhook could not be verified', 400);
        }
        if (! is_string($event['id'] ?? null) || strlen($event['id']) > 255) {
            return response('Invalid event', 400);
        }
        $key = ['provider' => $provider, 'mode' => $mode, 'event_id' => $event['id']];
        if (DB::table('payment_events')->where($key)->exists()) {
            return response('Already processed', 200);
        }
        try {
            $resource = $provider === 'stripe' ? ($event['data']['object'] ?? []) : ($event['resource'] ?? []);
            $reference = $provider === 'stripe' ? ($resource['client_reference_id'] ?? $resource['metadata']['payment_reference'] ?? null) :
                ($resource['purchase_units'][0]['custom_id'] ?? $resource['custom_id'] ?? null);
            $providerId = null;
            if ($provider === 'stripe' && ($resource['object'] ?? '') === 'checkout.session') {
                $providerId = $resource['id'] ?? null;
            }
            if ($provider === 'paypal') {
                $providerId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
                if (str_starts_with($event['event_type'] ?? '', 'CHECKOUT.ORDER.')) {
                    $providerId = $resource['id'] ?? null;
                }
            }
            $transaction = $provider === 'stripe' ? ($resource['payment_intent'] ?? null) : ($resource['supplementary_data']['related_ids']['capture_id'] ?? null);
            if ($provider === 'paypal' && str_starts_with($event['event_type'] ?? '', 'PAYMENT.CAPTURE.') && ($event['event_type'] ?? '') !== 'PAYMENT.CAPTURE.REFUNDED') {
                $transaction = $resource['id'] ?? null;
            }
            if ($provider === 'paypal' && ! $transaction && ($event['event_type'] ?? '') === 'PAYMENT.CAPTURE.REFUNDED') {
                foreach ($resource['links'] ?? [] as $link) {
                    if (($link['rel'] ?? '') === 'up' && preg_match('~/v2/payments/captures/([A-Za-z0-9]+)$~', $link['href'] ?? '', $match)) {
                        $transaction = $match[1];
                    }
                }
            }
            $payment = null;
            $query = Payment::where('provider', $provider)->where('mode', $mode);
            if ($reference) {
                $payment = (clone $query)->where('reference', $reference)->first();
            }
            if (! $payment && $providerId) {
                $payment = (clone $query)->where('provider_order_id', $providerId)->first();
            }
            if (! $payment && is_string($transaction)) {
                $payment = (clone $query)->where('transaction_id', $transaction)->first();
            }
            if ($payment) {
                if (! $payment->provider_order_id && $providerId) {
                    Payment::whereKey($payment->id)->whereNull('provider_order_id')->update(['provider_order_id' => $providerId]);
                    $payment->refresh();
                }
                $payments->sync($payment, $provider === 'paypal' && ($event['event_type'] ?? '') === 'CHECKOUT.ORDER.APPROVED');
                if ($provider === 'paypal' && ($event['event_type'] ?? '') === 'PAYMENT.CAPTURE.REFUNDED') {
                    $payments->paypalRefund($payment, $resource);
                }
            }
            DB::table('payment_events')->insertOrIgnore($key + ['created_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            return response('Payment reconciliation temporarily unavailable', 503);
        }

        return response('OK', 200);
    }
}
