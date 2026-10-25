<?php

namespace App\Services;

use App\Models\EmailHistory;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderDeletion
{
    public function remove(Order $order, array $data, User $actor): void
    {
        abort_unless($actor->isSuper(), 403);
        $data = Validator::make($data, [
            'revision' => 'required|integer|min:0', 'confirm' => 'required|accepted',
            'reason' => 'required|string|max:2000', 'restock_inventory' => 'required|boolean',
        ])->validate();
        if (trim($data['reason']) === '') {
            throw ValidationException::withMessages(['reason' => 'Enter a reason for deleting this order.']);
        }

        DB::transaction(function () use ($order, $data, $actor) {
            $current = Order::withTrashed()->lockForUpdate()->findOrFail($order->id);
            $current->assertEditable();
            if ((int) $current->revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['order' => 'Order changed. Reload and review it before deleting.']);
            }
            if ($reason = $current->deletionBlockReason()) {
                throw ValidationException::withMessages(['order' => $reason]);
            }

            $previousChoice = $current->cancellation_restock;
            $current->cancellation_restock = (bool) $data['restock_inventory'];
            $stock = app(OrderCancellationStock::class)->apply($current, 'Deleted order', $actor->id);
            $current->cancellation_restock = $previousChoice;
            // Preserve status, receipts and invoice snapshots. Deletion neither
            // moves money nor emits customer status/payment emails.
            $current->forceFill(['deleted_by' => $actor->id, 'deletion_reason' => trim($data['reason']),
                'deletion_restock' => (bool) $data['restock_inventory'], 'revision' => $current->revision + 1])->saveQuietly();
            $current->events()->create(['user_id' => $actor->id, 'created_at' => now(),
                'description' => 'Order deleted by Super Admin. Reason: '.trim($data['reason']).' · '.$stock.' Payment and invoice records retained for read-only review.']);
            $current->delete();
            EmailHistory::withoutGlobalScope('active_order')->where('order_id', $current->id)
                ->whereIn('status', ['queued', 'retry'])->update(['status' => 'cancelled', 'delivery_note' => 'Order deleted; email delivery cancelled.']);
        }, 3);
    }
}
