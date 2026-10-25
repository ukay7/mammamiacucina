<?php

namespace App\Services;

use App\Models\EmailHistory;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class CustomerEmails
{
    public function queue(string $key, string $type, string $recipient, array $values, ?array $document = null): void
    {
        $orderId = null;
        if (preg_match('/^order-(?:created|status|paid):(\d+)(?::|$)/', $key, $match)) {
            $orderId = $match[1];
        } elseif (preg_match('/^(settlement|invoice-email):(\d+)$/', $key, $match)) {
            $orderId = \Illuminate\Support\Facades\DB::table($match[1] === 'settlement' ? 'order_settlements' : 'customer_invoices')->where('id', $match[2])->value('order_id');
        }
        if ($orderId && !Order::whereKey($orderId)->exists()) return;
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $message = EmailTemplate::renderMessage($type, $values + ['site_name' => 'Mamma Mia Cucina']);
        // Unique event key is the final concurrency guard; payload is encrypted at rest.
        EmailHistory::firstOrCreate(['event_key' => $key], [
            'order_id' => $orderId, 'recipient' => $recipient, 'type' => $type, 'subject' => $message['subject'], 'status' => 'queued',
            'available_at' => now(), 'payload' => ['body' => $message['body'], 'document' => $document, 'idempotency_key' => 'mmc-'.Str::uuid()],
        ]);
    }

    public function business(User $user): void
    {
        if ($user->account_type !== 'business' || ! $user->business_approved_at || ! $user->is_active) {
            return;
        }
        if ($user->email_verified_at) {
            $this->queue('business-active:'.$user->id, 'business_active', $user->email,
                ['name' => $user->name, 'account_url' => route('customer.login')]);
        } else {
            $this->queue('business-approved:'.$user->id, 'business_approved', $user->email,
                ['name' => $user->name, 'verification_url' => URL::temporarySignedRoute('customer.invite', now()->addDays(2), ['user' => $user->id, 'hash' => sha1($user->email)])]);
        }
    }

    public function order(Order $order, string $type, string $key, ?int $amount = null): void
    {
        $order = $order->fresh(['items', 'payment', 'settlements']);
        if (! $order || $order->trashed() || ! $order->email) {
            return;
        }
        $money = fn ($c) => $c === null ? 'To be confirmed' : ($c < 0 ? '-' : '').'$'.number_format(abs($c) / 100, 2).' '.($order->currency ?: 'CAD');
        $status = $order->status === 'ready_to_dispatch' && $order->fulfillment === 'pickup' ? 'Ready for pickup' : $order->status_label;
        $instructions = match ($order->payment_method) {
            'cheque', 'etransfer' => 'If payment is outstanding, sign in to your customer portal and upload your payment receipt or cheque photo. Uploading a receipt does not confirm payment; our team verifies funds received or cheque clearance separately from delivery.',
            'credit' => 'This order is on customer credit. The outstanding balance remains due until settled.',
            'cash' => 'Any outstanding cash payment is due on '.($order->fulfillment === 'pickup' ? 'pickup.' : 'delivery.'),
            default => 'Payment is only confirmed after verification by the payment provider.',
        };
        $test = $order->payment?->mode === 'sandbox';
        $values = ['name' => trim($order->first_name.' '.$order->last_name), 'order_number' => $order->number,
            'status' => $status, 'payment_status' => $order->payment_status_label,
            'total' => $money($order->final_total_cents), 'received' => $money($order->net_received_cents),
            'balance' => $order->final_total_cents === null ? 'To be confirmed' : ($order->balance_cents < 0 ? 'Refund due: ' : 'Amount due: ').$money(abs($order->balance_cents)),
            'amount' => $money($amount), 'payment_method' => $order->payment_label,
            'fulfillment' => $order->fulfillment === 'pickup' ? 'Pickup: '.$order->pickup_address : 'Delivery: '.implode(', ', array_filter([$order->address, $order->city, $order->province, $order->postal_code, $order->country])),
            'order_url' => route('order.track', $order->tracking_token), 'account_url' => route('customer.login'),
            'instructions' => $instructions, 'environment' => $test ? 'TEST ORDER — no live payment is represented by this email.' : '',
        ];
        $document = in_array($type, ['order_confirmation', 'payment_received']) ? [
            'values' => $values, 'date' => now()->toDateTimeString(), 'subtotal' => $money($order->subtotal_cents),
            'delivery' => $money($order->delivery_cents), 'tax' => $money($order->tax_cents),
            'items' => $order->items->map(fn ($i) => ['name' => $i->name, 'code' => $i->product_code, 'quantity' => $i->quantity, 'unit' => $money($i->unit_cents), 'total' => $money($i->line_cents)])->all(),
        ] : null;
        if ($document) {
            $document['doc'] = app(DocumentTheme::class)->order($order);
            if ($type === 'payment_received') {
                $document['doc']['title'] = 'Payment Receipt';
                $document['doc']['totals'] = ['This payment'=>$money($amount)] + $document['doc']['totals'];
            }
        }
        $this->queue($key, $type, $order->email, $values, $document);
    }

    public function changed(Order $order): void
    {
        // Internal warehouse issues and administrative edits do not email customers.
        if ($order->wasChanged('status') && in_array($order->status, ['parked_admin', 'packing', 'warehouse_pending', 'ready_to_dispatch', 'out_for_delivery', 'delivered', 'completed', 'cancelled'])) {
            $this->order($order, 'order_status', 'order-status:'.$order->id.':'.$order->revision.':'.$order->status);
        }
        if ($order->wasChanged('payment_status') && $order->payment_status === 'paid') {
            $increase = (int) $order->manual_collected_cents - (int) $order->getRawOriginal('manual_collected_cents');
            if (! $order->settlements()->exists() || $increase > 0) {
                $this->order($order, 'payment_received', 'order-paid:'.$order->id, $increase > 0 ? $increase : $order->fresh()->collected_cents);
            }
        }
    }
}
