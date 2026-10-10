<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OnlinePayments;
use App\Services\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PaymentReportController extends Controller
{
    private function query(Request $request): array
    {
        $data = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d',
            'method' => 'nullable|in:cash,card,paypal,etransfer', 'source' => 'nullable|in:website,pos',
            'payment_status' => 'nullable|in:unpaid,pending,paid,partially_refunded,refunded', 'sandbox' => 'nullable|boolean']);
        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->endOfMonth()->toDateString();
        if ($to < $from) {
            throw ValidationException::withMessages(['to' => 'End date must follow start date.']);
        }
        $query = Order::with(['payment', 'settlements'])->where('created_at', '>=', $from.' 00:00:00')->where('created_at', '<', Carbon::parse($to)->addDay()->startOfDay());
        foreach (['method' => 'payment_method', 'source' => 'source', 'payment_status' => 'payment_status'] as $input => $column) {
            if (! empty($data[$input])) {
                $query->where($column, $data[$input]);
            }
        }
        if (! $request->boolean('sandbox')) {
            $query->whereDoesntHave('payment', fn ($q) => $q->where('mode', 'sandbox'));
        }

        return [$query, $from, $to];
    }

    public function index(Request $request, PaymentGateway $gateway)
    {
        [$query,$from,$to] = $this->query($request);
        $summary = [];
        foreach (['cash', 'card', 'paypal', 'etransfer'] as $method) {
            $summary[$method] = ['count' => 0, 'gross' => 0, 'refunded' => 0, 'pending' => 0];
        }
        foreach ((clone $query)->lazyById(500) as $order) {
            $method = $order->payment_method;
            if (! isset($summary[$method])) {
                continue;
            }
            $summary[$method]['count']++;
            $summary[$method]['gross'] += $order->collected_cents;
            $summary[$method]['refunded'] += $order->payment?->refunded_cents ?? ($order->manual_refunded_cents ?? ($order->payment_status === 'refunded' ? $order->collected_cents : 0));
            $summary[$method]['pending'] += max(0, $order->balance_cents);
            foreach ($order->settlements as $entry) {
                if ($entry->amount_cents > 0) {
                    $summary[$entry->method]['gross'] += $entry->amount_cents;
                } else {
                    $summary[$entry->method]['refunded'] -= $entry->amount_cents;
                }
            }
        }
        $orders = $query->latest('id')->paginate(30)->withQueryString();
        $gateways = ['Stripe' => $gateway->ready('stripe'), 'PayPal' => $gateway->ready('paypal')];

        return view('admin.reports.payments', compact('orders', 'summary', 'from', 'to', 'gateways'));
    }

    public function export(Request $request)
    {
        [$query] = $this->query($request);

        return response()->streamDownload(function () use ($query) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Order', 'Order date', 'Source', 'Method', 'Gateway', 'Environment', 'Payment status', 'Total CAD', 'Refunded CAD', 'Net received CAD', 'Transaction'], ',', '"', '');
            foreach ($query->lazyById(500) as $order) {
                $total = $order->final_total_cents;
                $refund = ($order->payment?->refunded_cents ?? ($order->manual_refunded_cents ?? ($order->payment_status === 'refunded' ? $order->collected_cents : 0))) + abs((int) $order->settlements->where('amount_cents', '<', 0)->sum('amount_cents'));
                $received = $order->collected_cents + (int) $order->settlements->where('amount_cents', '>', 0)->sum('amount_cents');
                $row = [$order->number, $order->created_at->toDateTimeString(), $order->source, $order->payment_method,
                    $order->payment?->provider ?? ($order->payment_method === 'card' ? 'External terminal' : 'Manual'),
                    $order->payment?->mode ?? 'live', $order->payment_status, $total === null ? 'Unconfirmed' : number_format($total / 100, 2, '.', ''),
                    number_format($refund / 100, 2, '.', ''), number_format(($received - $refund) / 100, 2, '.', ''), $order->payment?->transaction_id ?? ''];
                $row = array_map(fn ($value) => preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'".$value : $value, $row);
                fputcsv($stream, $row, ',', '"', '');
            }
            fclose($stream);
        }, 'payments-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reconcile(Payment $payment, OnlinePayments $payments)
    {
        try {
            if (! $payment->provider_order_id && ! $payment->expires_at->isPast()) {
                $payment = $payments->start($payment);
            }
            $payments->sync($payment, true);
        } catch (\Throwable $e) {
            return back()->withErrors(['payment' => 'The provider could not confirm the payment. Check the gateway dashboard and retry reconciliation.']);
        }

        return back()->with('status', 'Payment checked with provider.');
    }

    public function cancel(Payment $payment, OnlinePayments $payments)
    {
        try {
            if (! $payment->provider_order_id && ! $payment->expires_at->isPast()) {
                $payment = $payments->start($payment);
            }
            $payments->sync($payment, false, true);
        } catch (\Throwable $e) {
            return back()->withErrors(['payment' => 'Cancellation is not confirmed. Reserved stock remains held until reconciliation succeeds.']);
        }

        return back()->with('status', 'Cancellation checked with gateway. Review the updated payment status.');
    }

    public function refund(Request $request, Payment $payment, OnlinePayments $payments)
    {
        $data = $request->validate(['confirm' => 'required|in:REFUND', 'expected_cents' => 'required|integer|min:1', 'amount' => ['nullable', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/D']]);
        try {
            $payments->refund($payment, $request->user()->id, (int) $data['expected_cents'], isset($data['amount']) ? app(\App\Services\PaymentGateway::class)->cents($data['amount']) : null);
        } catch (\Throwable $e) {
            return back()->withErrors(['payment' => 'Refund not yet confirmed. Reconcile this payment and check the provider dashboard before retrying.']);
        }

        return back()->with('status', 'Refund request sent. Review the confirmed refund amount below.');
    }
}
