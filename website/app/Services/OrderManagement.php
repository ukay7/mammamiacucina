<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderManagement
{
    public function update(Order $order, array $d, int $userId): void
    {
        DB::transaction(function () use ($order, $d, $userId) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $fail = fn ($message) => throw ValidationException::withMessages(['order' => $message]);
            if ((int) $order->revision !== (int) $d['revision']) {
                $fail('This order changed. Reload it before saving.');
            }
            $money = static function ($value) {
                if ($value === null || $value === '') {
                    return null;
                }$parts = explode('.', (string) $value);

                return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
            };
            $delivery = $money($d['delivery']);
            if($order->delivery_service && $delivery !== (int)$order->delivery_cents)$fail('Delivery uses a saved postal-code rate. Change the service or destination through Edit Order instead.');
            $tax = $money($d['tax']);
            $status = $d['status'];
            $payment = $d['payment_status'];
            if ($order->payment_method === 'etransfer') {
                if ($payment === 'paid' && $order->payment_status !== 'paid' && !$order->transfer_receipt_path) {
                    $fail('Upload the e-transfer receipt and verify the payment before marking it paid.');
                }
                if ($status !== $order->status && !in_array($status, ['transfer_pending', 'cancelled'], true) && $payment !== 'paid') {
                    $fail('Verify the e-transfer and mark payment received before sending this order to warehouse.');
                }
                if ($status === 'warehouse_pending' && $order->payment_status === 'paid' && $order->balance_cents !== 0) {
                    $fail('Resolve the outstanding balance before sending this order to warehouse.');
                }
            }
            if ($order->fulfillment === 'pickup' && $delivery !== 0) $fail('Pickup orders must have zero delivery charge.');
            if ($order->payment) {
                if ($payment !== $order->payment_status || $delivery !== (int) $order->delivery_cents || $tax !== (int) $order->tax_cents) {
                    $fail('Online payment status and totals are managed by the gateway. Use Reconcile or Refund.');
                }
                if ($status !== $order->status && (! in_array($order->payment_status, ['paid', 'partially_refunded'], true) || in_array($order->status, ['awaiting_payment', 'payment_review'], true) || $status === 'cancelled')) {
                    $fail('Confirm online payment before fulfillment. Use the gateway refund or cancellation action for this order.');
                }
            }
            if (! $order->payment && ! in_array($payment, ['unpaid', 'paid', 'refunded'], true)) {
                $fail('Select Unpaid, Paid or Refunded for manually collected payments.');
            }
            if ($order->payment?->attention && $status !== $order->status) {
                $fail('Resolve the payment review before changing fulfillment status.');
            }
            if ($status !== $order->status) {
                if ($status !== 'cancelled' && ! array_key_exists($status, $order->status_options)) {
                    $fail('Select an available next status.');
                }
                if (in_array($order->status, ['completed', 'delivered', 'cancelled', 'out_for_delivery']) && $status === 'cancelled') {
                    $fail('Completed or dispatched orders require a return/refund, not cancellation.');
                }
                if ($status === 'warehouse_pending') {
                    if (!in_array($order->source, ['website','pos'], true)) {
                        $fail('This order does not support warehouse packing.');
                    }
                    $order->warehouse_round++;
                    $order->warehouse_sent_at = now();
                    $order->warehouse_note = null;
                    // A new warehouse round retains completed packing for unchanged items.
                    $order->items()->update(['warehouse_note' => null]);
                }
                if ($status === 'warehouse_issue') {
                    $order->warehouse_note = $d['reason'] ?? 'Admin recalled this order for review.';
                }
                if ($status === 'completed' && (! in_array($payment, ['paid', 'partially_refunded']) || (in_array($order->payment_status, ['paid', 'partially_refunded']) && $order->balance_cents !== 0))) {
                    $fail('Resolve the outstanding payment before completing the order.');
                }
            }
            if ($order->status === 'cancelled' && ($delivery !== ($order->delivery_cents === null ? null : (int) $order->delivery_cents) || $tax !== ($order->tax_cents === null ? null : (int) $order->tax_cents) || $payment !== $order->payment_status)) {
                $fail('Cancelled orders cannot be edited.');
            }
            if ($order->payment_status !== 'unpaid' && ($delivery !== ($order->delivery_cents === null ? null : (int) $order->delivery_cents) || $tax !== ($order->tax_cents === null ? null : (int) $order->tax_cents))) {
                $fail('Charges cannot change after payment has been collected.');
            }
            if ($payment !== $order->payment_status) {
                if ($payment === 'paid' && ($order->payment_status !== 'unpaid' || $delivery === null || $tax === null || $status === 'cancelled')) {
                    $fail('Confirm delivery and tax before recording payment received.');
                }
                if ($payment === 'unpaid') {
                    $fail('Payment received cannot be reset to unpaid. Record a refund if payment was returned.');
                }
                if ($payment === 'refunded' && (! in_array($order->payment_status, ['paid', 'unpaid']) || $order->net_received_cents <= 0 || $status !== 'cancelled')) {
                    $fail('Record a refund only when cancelling an order whose payment was collected.');
                }
            }
            if ($status === 'cancelled' && $payment === 'unpaid' && $order->net_received_cents > 0) {
                $fail('Return the collected deposit before cancelling. Mark the payment refunded after returning all money.');
            }
            if ($status === 'cancelled' && $payment === 'paid') {
                $fail('Return the collected payment and select Refunded when cancelling this order.');
            }
            if ($status === 'cancelled' && $order->status !== 'cancelled') {
                if (! $order->payment && $payment === 'refunded') {
                    if ($order->manual_collected_cents === null) {
                        $order->manual_collected_cents = $order->collected_cents;
                    }
                    $remaining = $order->net_received_cents;
                    $order->manual_refunded_cents = $order->manual_refunded_cents ?? 0;
                    if ($remaining > 0) {
                        $order->settlements()->create(['amount_cents' => -$remaining, 'method' => $order->payment_method, 'note' => 'Admin confirmed remaining collected money returned on cancellation', 'user_id' => $userId]);
                    }
                }
                foreach ($order->items()->orderBy('product_id')->get() as $item) {
                    if (! $item->stock_deducted || ! $item->product_id) {
                        continue;
                    }
                    $inventory = Inventory::where('product_id', $item->product_id)->lockForUpdate()->first();
                    if (! $inventory || $inventory->quantity_on_hand === null) {
                        $fail('Stock is no longer configured for '.$item->name.'. Set its inventory before cancelling.');
                    }
                    $before = $inventory->quantity_on_hand;
                    $after = ((int) round((float) $before * 1000) + $item->quantity * 1000) / 1000;
                    if ($after > 999999999) {
                        $fail('Restoring stock would exceed the inventory limit.');
                    }
                    $inventory->update(['quantity_on_hand' => $after]);
                    $item->update(['stock_deducted' => false]);
                    InventoryMovement::create(['product_id' => $item->product_id, 'quantity_change' => $item->quantity, 'quantity_before' => $before, 'quantity_after' => $after, 'reason' => 'Cancelled order '.$order->number, 'created_by' => $userId, 'created_at' => now()]);
                }
            }
            if (! $order->payment && $payment === 'paid' && $order->payment_status !== 'paid') {
                $order->manual_collected_cents = max(0, $order->subtotal_cents + ($delivery ?? 0) + ($tax ?? 0) - (int) $order->settlements()->sum('amount_cents'));
            }
            $description = 'Status: '.$order->status.' → '.$status.'; payment: '.$order->payment_status.' → '.$payment.'; delivery: '.($delivery === null ? 'pending' : number_format($delivery / 100, 2)).'; tax: '.($tax === null ? 'pending' : number_format($tax / 100, 2));
            $order->update(['status' => $status, 'payment_status' => $payment, 'delivery_cents' => $delivery, 'tax_cents' => $tax, 'revision' => $order->revision + 1, 'paid_at' => $payment === 'paid' ? ($order->paid_at ?? now()) : $order->paid_at, 'cancelled_at' => $status === 'cancelled' ? ($order->cancelled_at ?? now()) : null]);
            $order->events()->create(['user_id' => $userId, 'description' => $description.(! empty($d['reason']) ? ' · '.$d['reason'] : ''), 'created_at' => now()]);
        }, 3);
    }
}
