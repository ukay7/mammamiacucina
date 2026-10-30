<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderReceipt;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SupplierReceiving
{
    private function fail(string $message, string $field = 'receipt'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function units(?string $quantity): int
    {
        return BigDecimal::of($quantity ?? '0')->multipliedBy(1000)->toInt();
    }

    private function quantity(int $units): string
    {
        return (string) BigDecimal::of($units)->dividedBy(1000, 3);
    }

    public function receive(SupplierOrder $order, array $input, User $actor): SupplierOrderReceipt
    {
        abort_unless($actor->hasAdminPermission('purchases.manage'), 403);
        $data = Validator::make($input, [
            'revision' => 'required|integer|min:0', 'request_key' => 'required|uuid',
            'add_to_inventory' => 'required|boolean',
            'warehouse_id' => 'nullable|required_if:add_to_inventory,1|integer|exists:warehouses,id',
            'initialize_unset' => 'nullable|boolean', 'reason' => 'required|string|max:2000', 'confirm' => 'accepted',
        ])->validate();
        $data['add_to_inventory'] = (bool) $data['add_to_inventory'];
        $data['warehouse_id'] = $data['add_to_inventory'] ? (int) $data['warehouse_id'] : null;
        $data['initialize_unset'] = $data['add_to_inventory'] && (bool) ($data['initialize_unset'] ?? false);
        $data['reason'] = trim($data['reason']);
        if ($data['reason'] === '') {
            $this->fail('Enter a receiving note or the reason for leaving inventory unchanged.', 'reason');
        }
        if ($data['add_to_inventory']) {
            abort_unless($actor->hasAdminPermission('inventory.manage'), 403);
        }
        $hash = hash('sha256', json_encode([$order->id, $data['revision'], $data['add_to_inventory'], $data['warehouse_id'], $data['initialize_unset'], $data['reason']], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($order, $data, $actor, $hash) {
            $current = SupplierOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $existing = SupplierOrderReceipt::where('supplier_order_id', $current->id)->orWhere('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->supplier_order_id === $current->id && $existing->request_key === $data['request_key']
                    && $existing->received_by === $actor->id && hash_equals($existing->request_hash, $hash)) {
                    return $existing;
                }
                $this->fail('This order has already been received, or this receiving request was already used. Review its receiving history; stock was not added again.');
            }
            if (! in_array($current->status, ['placed', 'packing', 'ready', 'completed'], true)) {
                $this->fail('Only an open supplier order can be received. Deleted and received orders are view only.');
            }
            if ((int) $current->revision !== (int) $data['revision']) {
                $this->fail('This order changed. Reload before confirming receipt.', 'revision');
            }
            $warehouse = $data['add_to_inventory'] ? Warehouse::whereKey($data['warehouse_id'])->lockForUpdate()->firstOrFail() : null;
            if ($warehouse && ! $warehouse->is_active) {
                $this->fail('Choose an active receiving warehouse.', 'warehouse_id');
            }
            $items = $current->items()->orderBy('product_id')->get();
            if ($items->isEmpty()) {
                $this->fail('This order has no products to receive.');
            }
            // Product locks serialize first-time stock creation; inventory locks
            // coordinate with normal stock adjustments and customer orders.
            Product::whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get();
            $receipt = SupplierOrderReceipt::create([
                'supplier_order_id' => $current->id, 'add_to_inventory' => $data['add_to_inventory'],
                'warehouse_id' => $warehouse?->id, 'warehouse_name' => $warehouse?->name,
                'received_by' => $actor->id, 'received_by_name' => $actor->name, 'received_at' => now(),
                'reason' => $data['reason'], 'initialize_unset' => $data['initialize_unset'],
                'request_key' => $data['request_key'], 'request_hash' => $hash,
            ]);
            foreach ($items as $item) {
                $inventory = Inventory::where('product_id', $item->product_id)->lockForUpdate()->first();
                $beforeTotal = $inventory?->quantity_on_hand;
                $before = $after = $movementId = null;
                $afterTotal = $beforeTotal;
                if ($warehouse) {
                    $levels = WarehouseInventory::where('product_id', $item->product_id)->orderBy('warehouse_id')->lockForUpdate()->get();
                    $level = $levels->firstWhere('warehouse_id', $warehouse->id);
                    $before = $level?->quantity_on_hand;
                    if ($before === null && ! $data['initialize_unset']) {
                        $this->fail($item->name.': stock is not set in this warehouse. Confirm initialization from this delivery, or set its opening stock first.', 'initialize_unset');
                    }
                    $knownTotal = $levels->sum(fn ($row) => $this->units($row->quantity_on_hand));
                    if ($beforeTotal !== null && $this->units($beforeTotal) !== $knownTotal) {
                        $this->fail($item->name.': total inventory and warehouse quantities disagree. Reconcile its inventory before receiving this order.');
                    }
                    $amount = $this->units($item->quantity);
                    $newLevel = $this->units($before) + $amount;
                    $newTotal = $knownTotal + $amount;
                    if ($amount <= 0 || abs($newLevel) > 999999999000 || abs($newTotal) > 999999999000) {
                        $this->fail($item->name.': receiving this quantity would exceed the inventory limit.');
                    }
                    $after = $this->quantity($newLevel);
                    $afterTotal = $this->quantity($newTotal);
                    WarehouseInventory::updateOrCreate(['product_id' => $item->product_id, 'warehouse_id' => $warehouse->id], ['quantity_on_hand' => $after]);
                    // Bypass the legacy Inventory hook which would also add this
                    // delta to the default warehouse. Only the chosen location changes.
                    Inventory::withoutEvents(function () use (&$inventory, $item, $afterTotal) {
                        $inventory ??= new Inventory(['product_id' => $item->product_id, 'low_stock_threshold' => 10]);
                        $inventory->quantity_on_hand = $afterTotal;
                        $inventory->save();
                    });
                    $movement = InventoryMovement::create([
                        'product_id' => $item->product_id, 'warehouse_id' => $warehouse->id,
                        'quantity_change' => $item->quantity, 'quantity_before' => $before, 'quantity_after' => $after,
                        'reason' => 'Supplier order '.$current->number.' received. '.$data['reason'],
                        'created_by' => $actor->id, 'created_at' => $receipt->received_at,
                    ]);
                    $movementId = $movement->id;
                }
                $receipt->items()->create([
                    'supplier_order_item_id' => $item->id, 'product_id' => $item->product_id,
                    'name' => $item->name, 'product_code' => $item->product_code, 'barcode' => $item->barcode,
                    'quantity' => $item->quantity, 'quantity_before' => $before, 'quantity_after' => $after,
                    'total_before' => $beforeTotal, 'total_after' => $afterTotal, 'inventory_movement_id' => $movementId,
                ]);
            }
            $from = $current->status;
            $current->status = 'received';
            $current->revision++;
            $current->save();
            $current->events()->create(['user_id' => $actor->id, 'actor_name' => $actor->name, 'from_status' => $from, 'to_status' => 'received',
                'reason' => 'Receiving record #'.$receipt->id.'. '.($warehouse ? 'Ordered quantities added to '.$warehouse->name.'. ' : 'Inventory not added; quantities left unchanged. ').$data['reason']]);

            return $receipt;
        }, 3);
    }
}
