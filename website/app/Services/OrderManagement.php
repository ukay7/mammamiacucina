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
            $tax = $money($d['tax']);
            $status = $d['status'];
            $payment = $d['payment_status'];
            $path = ['placed', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'];
            if ($status !== $order->status) {
                if (in_array($order->status, ['delivered', 'cancelled'])) {
                    $fail('Completed or cancelled orders cannot change status.');
                }
                if ($status !== 'cancelled' && array_search($status, $path, true) !== array_search($order->status, $path, true) + 1) {
                    $fail('Move the order to the next status in sequence.');
                }
            }
            if ($order->status === 'cancelled' && ($delivery !== ($order->delivery_cents===null?null:(int)$order->delivery_cents) || $tax !== ($order->tax_cents===null?null:(int)$order->tax_cents) || $payment !== $order->payment_status)) {
                $fail('Cancelled orders cannot be edited.');
            }
            if ($order->payment_status !== 'unpaid' && ($delivery !== ($order->delivery_cents === null ? null : (int) $order->delivery_cents) || $tax !== ($order->tax_cents === null ? null : (int) $order->tax_cents))) {
                $fail('Charges cannot change after cash has been collected.');
            }
            if ($payment !== $order->payment_status) {
                if ($payment === 'paid' && ($order->payment_status !== 'unpaid' || $delivery === null || $tax === null || $status === 'cancelled')) {
                    $fail('Confirm delivery and tax before recording cash received.');
                }
                if ($payment === 'unpaid') {
                    $fail('Cash received cannot be reset to unpaid. Record a refund if cash was returned.');
                }
                if ($payment === 'refunded' && ($order->payment_status !== 'paid' || $status !== 'cancelled')) {
                    $fail('Record a refund only when cancelling an order whose cash was collected.');
                }
            }
            if ($status === 'cancelled' && $payment === 'paid') {
                $fail('Return the collected cash and select Refunded when cancelling this order.');
            }
            if ($status === 'cancelled' && $order->status !== 'cancelled') {
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
                    InventoryMovement::create(['product_id' => $item->product_id, 'quantity_change' => $item->quantity, 'quantity_before' => $before, 'quantity_after' => $after, 'reason' => 'Cancelled order '.$order->number, 'created_by' => $userId, 'created_at' => now()]);
                }
            }
            $description = 'Status: '.$order->status.' → '.$status.'; payment: '.$order->payment_status.' → '.$payment.'; delivery: '.($delivery === null ? 'pending' : number_format($delivery / 100, 2)).'; tax: '.($tax === null ? 'pending' : number_format($tax / 100, 2));
            $order->update(['status' => $status, 'payment_status' => $payment, 'delivery_cents' => $delivery, 'tax_cents' => $tax, 'revision' => $order->revision + 1, 'paid_at' => $payment === 'paid' ? ($order->paid_at ?? now()) : $order->paid_at, 'cancelled_at' => $status === 'cancelled' ? ($order->cancelled_at ?? now()) : null]);
            $order->events()->create(['user_id' => $userId, 'description' => $description.(! empty($d['reason']) ? ' · '.$d['reason'] : ''), 'created_at' => now()]);
        }, 3);
    }
}
