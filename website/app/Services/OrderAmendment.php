<?php

namespace App\Services;

use App\Models\GeneralSetting;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderAmendment
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['order' => $message]);
    }

    public static function cents(string $value): int
    {
        $parts = explode('.', $value);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public function amend(Order $order, array $data, int $user): void
    {
        DB::transaction(function () use ($order, $data, $user) {
            $settings = GeneralSetting::lockForUpdate()->findOrFail(1);
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if ((int) $order->revision !== (int) $data['revision']) {
                $this->fail('This order changed. Reload before editing.');
            }
            if (in_array($order->status, ['cancelled', 'completed', 'delivered', 'out_for_delivery'])) {
                $this->fail('Items and fulfillment cannot change after dispatch or completion. Handle physical returns separately.');
            }
            if ($order->payment && (! $order->payment->paid_at || $order->payment->attention || $order->payment->released_at)) {
                $this->fail('Resolve or cancel the outstanding gateway checkout before editing its order.');
            }
            $items = $order->items()->orderBy('product_id')->get()->keyBy('id');
            if (array_diff(array_keys($data['items']), $items->keys()->all()) || count($data['items']) !== $items->count()) {
                $this->fail('Order items changed. Reload before editing.');
            }
            $ids = $items->pluck('product_id')->filter()->merge(array_column($data['add_items'] ?? [], 'product_id'))->unique()->sort()->values();
            $products = Product::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $inventories = Inventory::whereIn('product_id', $ids)->orderBy('product_id')->lockForUpdate()->get()->keyBy('product_id');
            $oldTotal = $order->final_total_cents;
            if (! $order->payment && $order->manual_collected_cents === null) {
                $order->manual_collected_cents = $order->payment_status === 'paid' ? ($oldTotal ?? 0) : 0;
            }
            $before = $items->map(fn ($i) => ['name' => $i->name, 'quantity' => $i->quantity, 'unit_cents' => $i->unit_cents])->values()->all();
            $stock = function ($productId, int $difference, string $name) use ($inventories, $order, $user) {
                if (! $difference) {
                    return;
                }
                $inv = $inventories->get($productId);
                if (! $inv || $inv->quantity_on_hand === null) {
                    $this->fail('Inventory is no longer configured for '.$name.'. Resolve it before changing quantity.');
                }
                $before = $inv->quantity_on_hand;
                $after = (int) round((float) $before * 1000) - $difference * 1000;
                if ($after < 0 || $after > 999999999000) {
                    $this->fail('Insufficient stock or inventory limit for '.$name.'.');
                }
                $inv->update(['quantity_on_hand' => $after / 1000]);
                InventoryMovement::create(['product_id' => $productId, 'quantity_change' => -$difference, 'quantity_before' => $before, 'quantity_after' => $after / 1000, 'reason' => 'Order amended '.$order->number, 'created_by' => $user, 'created_at' => now()]);
            };
            foreach ($items as $id => $item) {
                $row = $data['items'][$id];
                $qty = (int) $row['quantity'];
                $difference = $qty - (int) $item->quantity;
                if ($item->stock_deducted) {
                    $stock($item->product_id, $difference, $item->name);
                } elseif ($difference && $inventories->get($item->product_id)?->quantity_on_hand !== null && $inventories->has($item->product_id)) {
                    $this->fail('Stock tracking changed for '.$item->name.'. Resolve the original untracked reservation before changing quantity.');
                }
                if (! $qty) {
                    $item->delete();

                    continue;
                }
                $unit = self::cents($row['unit_price']);
                $changes = ['quantity' => $qty, 'unit_cents' => $unit, 'line_cents' => $qty * $unit];
                // Price/contact changes do not invalidate a physical packing check.
                if ($difference !== 0) {
                    $changes += ['packed' => false, 'packed_by' => null, 'packed_at' => null];
                }
                $item->update($changes);
            }
            foreach ($data['add_items'] ?? [] as $row) {
                $p = $products->get($row['product_id']);
                if (! $p || ! $p->is_active) {
                    $this->fail('Choose an active product to add.');
                }
                if ($order->items()->where('product_id', $p->id)->exists()) {
                    $this->fail('Product is already in the order. Change its existing quantity.');
                }
                $qty = (int) $row['quantity'];
                $unit = isset($row['unit_price']) && $row['unit_price'] !== '' ? self::cents($row['unit_price']) : ($p->total_selling_price_cad === null ? null : (int) round((float) $p->total_selling_price_cad * 100));
                if ($unit === null) {
                    $this->fail('Enter a selling price for '.$p->premium_marketing_name.'.');
                }
                $tracked = $inventories->has($p->id) && $inventories[$p->id]->quantity_on_hand !== null;
                if ($tracked) {
                    $stock($p->id, $qty, $p->premium_marketing_name);
                }
                $order->items()->create(['product_id' => $p->id, 'name' => $p->premium_marketing_name, 'product_code' => $p->product_code, 'qr_code' => $p->qr_code, 'stock_deducted' => $tracked, 'quantity' => $qty, 'unit_cents' => $unit, 'line_cents' => $qty * $unit]);
            }
            if ($order->items()->count() > 100) {
                $this->fail('An order can contain up to 100 different products.');
            }
            if (! $order->items()->exists()) {
                $this->fail('Keep at least one item, or cancel the order instead.');
            }
            $subtotal = (int) $order->items()->sum('line_cents');
            $delivery = $data['fulfillment'] === 'pickup' ? 0 : self::cents($data['delivery']);
            $route=[];
            $routeFields=['delivery_service','delivery_service_name','delivery_from_postal','delivery_to_postal','delivery_from_zone','delivery_to_zone','delivery_rate_cents'];
            if($data['fulfillment']==='pickup'){
                $route=array_fill_keys($routeFields,null);
            }elseif($order->delivery_service || $settings->matrix_delivery_enabled){
                if(!in_array(strtolower(trim($data['country']??'')),['canada','ca']))$this->fail('Choose a supported Canadian delivery address.');
                $postal=DeliveryQuote::postal($data['postal_code']??'');
                $chosen=$data['delivery_service']??$order->delivery_service;
                $same=$order->fulfillment==='delivery' && $order->delivery_service && $chosen===$order->delivery_service && $postal===DeliveryQuote::postal($order->delivery_to_postal?:$order->postal_code);
                if($same){
                    if($delivery!==(int)$order->delivery_cents)$this->fail('Keep the saved delivery charge for this route, or change the service/destination to obtain a new quote.');
                }else{
                    $route=app(DeliveryQuote::class)->quote($settings->warehouse_postal_code??'',$postal,$chosen??'');
                    if($delivery!==$route['delivery_cents'])$this->fail('Delivery rate changed. Select the service again to review the updated total.');
                }
                $data['postal_code']=$postal;
            }
            $order->fill($route);
            $rate = $order->tax_basis_points ?? $settings->tax_basis_points;
            $tax = ($data['tax_mode'] ?? 'manual') === 'recalculate' ? intdiv($subtotal * $rate + 5000, 10000) : self::cents($data['tax']);
            if ($subtotal + $delivery + $tax > 999999999) {
                $this->fail('Order total exceeds the supported limit.');
            }
            if ($data['fulfillment'] === 'delivery') {
                foreach (['address', 'city', 'province', 'postal_code', 'country'] as $field) {
                    if (empty(trim($data[$field] ?? ''))) {
                        $this->fail('A complete address is required for delivery.');
                    }
                }
            }
            $order->pickup_address = $data['fulfillment'] === 'pickup' ? ($order->pickup_address ?: $settings->pickup_address) : null;
            $order->fill(collect($data)->only(['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'province', 'postal_code', 'country', 'fulfillment'])->all());
            $order->subtotal_cents = $subtotal;
            $order->delivery_cents = $delivery;
            $order->tax_cents = $tax;
            if (($data['tax_mode'] ?? 'manual') === 'recalculate') {
                $order->tax_basis_points = $rate;
            }
            if (in_array($order->status, ['warehouse_pending', 'packing', 'ready_to_dispatch'])) {
                $order->status = 'warehouse_issue';
                $order->warehouse_note = 'Order changed by admin; send back to warehouse to check changed and outstanding items.';
            }
            $order->revision++;
            $order->save();
            $after = $order->items()->get()->map(fn ($i) => ['name' => $i->name, 'quantity' => $i->quantity, 'unit_cents' => $i->unit_cents])->all();
            $order->events()->create(['user_id' => $user, 'description' => 'Order amended: '.$data['reason'].' · fulfillment '.$order->fulfillment.' · total CAD '.number_format(($oldTotal ?? 0) / 100, 2).' → '.number_format($order->final_total_cents / 100, 2).' · Items before: '.json_encode($before, JSON_UNESCAPED_UNICODE).' · Items after: '.json_encode($after, JSON_UNESCAPED_UNICODE), 'created_at' => now()]);
        }, 3);
    }

    public function settle(Order $order, array $data, int $user): void
    {
        DB::transaction(function () use ($order, $data, $user) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if ((int) $order->revision !== (int) $data['revision']) {
                $this->fail('Order balance changed. Reload before recording payment.');
            }
            if (($order->status === 'cancelled' && $data['direction'] !== 'refund') || ($order->payment && (! $order->payment->paid_at || $order->payment->attention))) {
                $this->fail('Resolve the original payment first.');
            }
            if ($order->final_total_cents === null) {
                $this->fail('Finalize the order total before recording a payment.');
            }
            if ($order->payment_method === 'etransfer' && $data['direction'] === 'collect'
                && $data['method'] === 'etransfer' && empty($data['receipt_path']) && !$order->transfer_receipt_path) {
                $this->fail('Attach the e-transfer receipt or upload it to the order before confirming funds received.');
            }
            $amount = self::cents($data['amount']);
            $balance = $order->balance_cents;
            if ($amount <= 0 || ($data['direction'] === 'collect' ? $balance < $amount : -$balance < $amount)) {
                $this->fail('The amount exceeds the current amount due or refund due.');
            }
            if (! $order->payment && $order->manual_collected_cents === null) {
                $order->manual_collected_cents = $order->collected_cents;
            }
            if ($data['direction'] === 'refund' && $order->payment) {
                $manualAvailable = (int) $order->settlements()->where('method', $data['method'])->sum('amount_cents');
                if ($amount > $manualAvailable) {
                    $this->fail('Refund the original gateway payment through its provider and reconcile it here. This action only records refunds of separately collected amounts.');
                }
            }
            $order->settlements()->create(['receipt_path' => $data['receipt_path'] ?? null, 'amount_cents' => $data['direction'] === 'refund' ? -$amount : $amount, 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'note' => $data['note'], 'user_id' => $user]);
            $order->unsetRelation('settlements');
            if (! $order->payment && $order->balance_cents <= 0 && $order->status !== 'cancelled') {
                $order->payment_status = 'paid';
                $order->paid_at = $order->paid_at ?? now();
            }
            $order->revision++;
            $order->save();
            $order->events()->create(['user_id' => $user, 'description' => 'External '.($data['direction'] === 'refund' ? 'refund' : 'collection').' recorded: CAD '.number_format($amount / 100, 2).' via '.$data['method'].' · '.$data['note'], 'created_at' => now()]);
            // Use the same warehouse transition as the website receipt approval flow.
            if ($order->payment_method === 'etransfer' && $order->status === 'transfer_pending'
                && $order->payment_status === 'paid' && $order->balance_cents === 0) {
                app(OrderManagement::class)->update($order, [
                    'revision' => $order->revision, 'status' => 'warehouse_pending', 'payment_status' => 'paid',
                    'delivery' => number_format($order->delivery_cents / 100, 2, '.', ''),
                    'tax' => number_format($order->tax_cents / 100, 2, '.', ''),
                    'reason' => 'Admin confirmed payments received in full through the payment ledger; sent to warehouse.',
                ], $user);
            }

        }, 3);
    }
}
