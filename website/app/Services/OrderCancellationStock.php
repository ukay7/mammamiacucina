<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderCancellationStock
{
    /** The caller must hold the order lock and commit the choice with its action. */
    public function choose(Order $order, array $data): void
    {
        $validated = Validator::make($data, ['restock_inventory' => 'required|boolean'], [
            'restock_inventory.required' => 'Choose whether to return this order’s items to inventory before cancelling.',
        ])->validate();

        $order->cancellation_restock = (bool) $validated['restock_inventory'];
    }

    /** Return only quantities previously deducted, inside the caller's transaction. */
    public function apply(Order $order, string $reason, ?int $userId = null): string
    {
        // Retain the original deduction flags for items deliberately not returned.
        // Gateway retries/webhooks must respect the saved decision as well.
        if ($order->cancellation_restock === false) {
            return 'Inventory: not returned; admin chose to keep stock unchanged.';
        }

        $returned = false;
        foreach ($order->items()->orderBy('product_id')->orderBy('id')->get() as $item) {
            if (! $item->stock_deducted || ! $item->product_id) {
                continue;
            }
            $inventory = Inventory::where('product_id', $item->product_id)->lockForUpdate()->first();
            if (! $inventory || $inventory->quantity_on_hand === null) {
                throw ValidationException::withMessages(['order' => 'Stock is no longer configured for '.$item->name.'. Set its inventory before returning stock.']);
            }
            $before = $inventory->quantity_on_hand;
            $after = ((int) round((float) $before * 1000) + $item->quantity * 1000) / 1000;
            if ($after > 999999999) {
                throw ValidationException::withMessages(['order' => 'Returning stock would exceed the inventory limit.']);
            }
            $inventory->update(['quantity_on_hand' => $after]);
            InventoryMovement::create([
                'product_id' => $item->product_id, 'quantity_change' => $item->quantity,
                'quantity_before' => $before, 'quantity_after' => $after,
                'reason' => $reason.' '.$order->number, 'created_by' => $userId, 'created_at' => now(),
            ]);
            $item->update(['stock_deducted' => false, 'inventory_shortage_quantity' => 0]);
            $returned = true;
        }

        return $returned ? 'Inventory: deducted items returned to stock.' : 'Inventory: no deducted stock to return.';
    }
}
