<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehousePacking
{
    public function save(Order $order, array $data, int $user): Order
    {
        return DB::transaction(function () use ($order, $data, $user) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $fail = fn ($m) => throw ValidationException::withMessages(['warehouse' => $m]);
            if ((int) $order->revision !== (int) $data['revision']) {
                $fail('This order changed. Reload before packing.');
            }
            if ($order->source !== 'website' || ! in_array($order->status, ['warehouse_pending', 'packing'])) {
                $fail('This order is not currently assigned for packing.');
            }
            if ($order->payment && (! in_array($order->payment_status, ['paid', 'partially_refunded']) || $order->payment->attention || $order->payment->released_at)) {
                $fail('Admin must resolve the payment before packing.');
            }
            $items = $order->items()->orderBy('id')->get();
            if (count($data['items']) !== $items->count() || array_diff(array_map('strval', array_keys($data['items'])), $items->pluck('id')->map(fn ($id) => (string) $id)->all())) {
                $fail('The order items changed. Reload this order.');
            }
            $all = true;
            $issue = false;
            $changes = [];
            foreach ($items as $item) {
                $row = $data['items'][$item->id];
                $packed = (bool) $row['packed'];
                $note = trim($row['note'] ?? '');
                if ($note !== '') {
                    $packed = false;
                }
                if (! $packed) {
                    $all = false;
                    if ($note !== '') {
                        $issue = true;
                    }
                }
                if ((bool) $item->packed !== $packed || ($item->warehouse_note ?? '') !== $note) {
                    $changes[] = $item->name.': '.($packed ? 'packed' : 'not packed').($note !== '' ? ' — '.$note : '');
                }
                $item->update(['packed' => $packed, 'warehouse_note' => $note ?: null, 'packed_by' => $packed ? ($item->packed_by ?? $user) : null, 'packed_at' => $packed ? ($item->packed_at ?? now()) : null]);
            }
            $action = $data['action'];
            if ($action === 'ready' && (! $all || $items->isEmpty())) {
                $fail('Resolve item notes and tick every item as packed before marking this order ready.');
            }
            if ($action === 'return' && ! $issue && empty(trim($data['note'] ?? ''))) {
                $fail('Explain the missing item or problem before returning the order.');
            }
            $status = match ($action) {
                'ready' => 'ready_to_dispatch','return' => 'warehouse_issue',default => 'packing'
            };
            $note = trim($data['note'] ?? '');
            $order->update(['status' => $status, 'warehouse_note' => $note ?: null, 'revision' => $order->revision + 1]);
            $order->events()->create(['user_id' => $user, 'description' => 'Warehouse round '.$order->warehouse_round.': '.Order::STATUSES[$status].($note ? ' · '.$note : '').($changes ? ' · '.implode(' | ', $changes) : ''), 'created_at' => now()]);

            return $order;
        }, 3);
    }
}
