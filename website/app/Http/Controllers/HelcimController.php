<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\HelcimGateway;
use App\Services\OnlinePayments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HelcimController extends Controller
{
    public function confirm(Request $request, Payment $payment, HelcimGateway $gateway, OnlinePayments $payments)
    {
        abort_unless($payment->provider === 'helcim' && ((int) session('last_order_id') === $payment->order_id ||
            (auth()->check() && (int) $payment->order->created_by === auth()->id())), 404);
        $data = $request->validate(['transaction_id' => ['required', 'string', 'regex:/^[0-9]{1,20}$/D']]);
        try {
            // No client-supplied status, price, card data or hash is trusted. Read the authenticated API.
            $transaction = $gateway->transaction($payment->mode, $data['transaction_id']);
            if (($transaction['invoiceNumber'] ?? '') !== $gateway->invoiceNumber($payment)) {
                return response()->json(['message' => 'Payment does not belong to this order.'], 422);
            }
            $payment = $payments->sync($payment);
            return response()->json(['redirect' => route('payment.show', $payment->reference), 'paid' => (bool) $payment->paid_at]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Confirmation is pending. Do not pay again; use Check payment status.'], 503);
        }
    }

    public function webhook(Request $request, HelcimGateway $gateway, OnlinePayments $payments)
    {
        $mode = $request->query('mode');
        abort_unless(in_array($mode, ['sandbox', 'live'], true), 400);
        if (strlen($request->getContent()) > 16384) return response('Payload too large', 413);
        try {
            $event = $gateway->verifyWebhook($mode, $request->getContent(), $request->header('webhook-id', ''),
                $request->header('webhook-timestamp', ''), $request->header('webhook-signature', ''));
        } catch (\Throwable $e) {
            return response('Invalid signature', 400);
        }
        if (($event['type'] ?? '') !== 'cardTransaction') return response('Ignored', 200);
        $key = ['provider' => 'helcim', 'mode' => $mode, 'event_id' => $request->header('webhook-id')];
        if (DB::table('payment_events')->where($key)->exists()) return response('Already processed', 200);
        try {
            $row = $gateway->transaction($mode, (string) ($event['id'] ?? ''));
            $invoice = $row['invoiceNumber'] ?? '';
            $payment = Payment::where('provider', 'helcim')->where('mode', $mode)->where('provider_order_id', $invoice)->first();
            if ($payment) {
                $payments->sync($payment);
            } elseif (str_starts_with($invoice, 'MMC')) {
                // Allow a concurrent checkout transaction to commit before acknowledging delivery.
                return response('Order reconciliation pending', 503);
            }
            DB::table('payment_events')->insertOrIgnore($key + ['created_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            return response('Reconciliation temporarily unavailable', 503);
        }
        return response('OK', 200);
    }
}
