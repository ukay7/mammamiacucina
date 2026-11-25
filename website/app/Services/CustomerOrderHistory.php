<?php

namespace App\Services;

use App\Models\Order;
use App\Support\BusinessTime;
use Illuminate\Support\Facades\DB;

class CustomerOrderHistory
{
    private const LABELS = [
        'placed' => 'Order placed',
        'parked_admin' => 'Order received',
        'warehouse_issue' => 'Order under review',
    ];

    public function record(Order $order): void
    {
        DB::table('order_status_changes')->insert([
            'order_id' => $order->id, 'status' => $order->status, 'created_at' => now(),
        ]);
    }

    public function forOrder(Order $order): array
    {
        $changes = DB::table('order_status_changes')->where('order_id', $order->id)->orderBy('id')->get(['status', 'created_at']);
        $firstRecorded = $changes->first()?->created_at;
        $events = $order->events()
            ->when($firstRecorded, fn ($query) => $query->where('created_at', '<', $firstRecorded))
            ->where(fn ($query) => $query->where('description', 'like', 'Status: %')->orWhere('description', 'like', 'Warehouse round %'))
            ->orderBy('id')->get(['description', 'created_at']);
        foreach ($events as $event) {
            if ($status = $this->legacyStatus($event->description)) {
                $changes->push((object) ['status' => $status, 'created_at' => $event->created_at]);
            }
        }

        foreach (['warehouse_pending' => $order->warehouse_sent_at, 'cancelled' => $order->cancelled_at] as $status => $date) {
            if ($date && ! $changes->contains('status', $status)) {
                $changes->push((object) ['status' => $status, 'created_at' => $date]);
            }
        }

        $timeline = [];
        $previous = null;
        if ($placed = $order->ordered_at ?? $order->created_at) {
            $changes->prepend((object) ['status' => 'placed', 'created_at' => $placed]);
        }
        foreach ($changes->sortBy(fn ($row) => BusinessTime::local($row->created_at)->getTimestamp()) as $row) {
            $label = $this->label($row->status);
            if ($label && $row->status !== $previous) {
                $timeline[] = ['label' => $label, 'date' => BusinessTime::local($row->created_at)];
                $previous = $row->status;
            }
        }

        return $timeline;
    }

    public function label(string $status, ?Order $order = null): ?string
    {
        if ($status === 'ready_to_dispatch' && $order?->fulfillment === 'pickup') {
            return 'Ready for pickup';
        }

        return self::LABELS[$status] ?? Order::STATUSES[$status] ?? null;
    }

    private function legacyStatus(string $description): ?string
    {
        if (preg_match('/^Status: (\w+) → (\w+); payment:/u', $description, $parts)) {
            return $parts[1] === $parts[2] ? null : $parts[2];
        }
        if (preg_match('/^Warehouse round \d+: ([^·]+)/u', $description, $parts)) {
            $status = array_search(trim($parts[1]), Order::STATUSES, true);

            return $status === false ? null : $status;
        }

        return null;
    }
}
