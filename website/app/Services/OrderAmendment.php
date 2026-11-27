<?php

namespace App\Services;

use App\Models\GeneralSetting;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\OrderRevision;
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

    public function savedLinePricing(OrderItem $item): array
    {
        $unit = (int) $item->unit_cents;
        $base = (int) ($item->original_unit_cents ?? $unit);
        $discount = (int) $item->discount_bps;
        if ($this->discountedUnit($base, $discount) !== $unit) {
            return ['original_unit_cents' => $unit, 'discount_bps' => 0];
        }

        return ['original_unit_cents' => $base, 'discount_bps' => $discount];
    }

    private function discountedUnit(int $base, int $discount): int
    {
        return $base - (int) round($base * $discount / 10000);
    }

    public function productPrices(Order $order, \Illuminate\Support\Collection $products): array
    {
        $order->loadMissing('customer.user');
        if ($order->customer?->user) {
            return app(CustomerTerms::class)->prices($products, $order->customer);
        }

        // Legacy orders without a customer account keep the individual-price default.
        return $products->mapWithKeys(function ($product) {
            $base = $product->total_selling_price_cad === null ? null : (int) round((float) $product->total_selling_price_cad * 100);
            return [$product->id => ['original_unit_cents' => $base, 'discount_bps' => 0, 'unit_cents' => $base]];
        })->all();
    }

    public function amend(Order $order, array $data, int $user): void
    {
        DB::transaction(function () use ($order, $data, $user) {
            $settings = GeneralSetting::lockForUpdate()->findOrFail(1);
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $order->assertEditable();
            $actor = User::findOrFail($user);
            $fulfilled = $order->isFulfilled();
            if ($fulfilled) abort_unless($actor->is_active && $actor->isSuper(), 403);
            $data['reason'] = trim($data['reason'] ?? '');
            if ($data['reason'] === '') $this->fail('Enter the reason for this order change.');
            $order->load(['payment'=>fn($q)=>$q->lockForUpdate(), 'settlements'=>fn($q)=>$q->lockForUpdate()]);
            if ((int) $order->revision !== (int) $data['revision']) {
                $this->fail('This order changed. Reload before editing.');
            }
            if (in_array($order->status, ['cancelled', 'out_for_delivery'])) {
                $this->fail('Items and fulfillment cannot change after dispatch or completion. Handle physical returns separately.');
            }
            if ($order->payment && (! $order->payment->paid_at || $order->payment->attention || $order->payment->released_at)) {
                $this->fail('Resolve or cancel the outstanding gateway checkout before editing its order.');
            }
            if ($order->pending_refund_cents > 0) $this->fail('Clear or cancel pending refund cheques before changing this order.');
            if ($order->payment && DB::table('payment_refunds')->where('payment_id', $order->payment->id)->whereNotIn('status', ['completed','succeeded','failed'])->lockForUpdate()->exists()) {
                $this->fail('Resolve the pending gateway refund before changing this order.');
            }
            if ($order->payment_status === 'refunded' && !$order->refund_credit_cents && $order->net_received_cents === 0) {
                $this->fail('This legacy fully refunded order needs payment reconciliation before it can be amended.');
            }
            if ($fulfilled) app(CustomerInvoicing::class)->issue($order);
            $snapshotBefore = $fulfilled ? $this->snapshot($order) : null;
            $stockReturns = [];
            $previousSchedule = $order->delivery_scheduled_at;
            if (array_key_exists('delivery_date', $data) || array_key_exists('delivery_time', $data)) {
                $order->delivery_scheduled_at = app(DeliverySchedule::class)->resolve($data, $data['fulfillment'] === 'delivery', $previousSchedule);
            }
            $items = $order->items()->orderBy('product_id')->get()->keyBy('id');
            if (array_diff(array_keys($data['items']), $items->keys()->all()) || count($data['items']) !== $items->count()) {
                $this->fail('Order items changed. Reload before editing.');
            }
            $ids = $items->pluck('product_id')->filter()->merge(array_column($data['add_items'] ?? [], 'product_id'))->unique()->sort()->values();
            $products = Product::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $prices = $this->productPrices($order, $products);
            $inventories = Inventory::whereIn('product_id', $ids)->orderBy('product_id')->lockForUpdate()->get()->keyBy('product_id');
            $oldTotal = $order->final_total_cents;
            if (! $order->payment && $order->manual_collected_cents === null) {
                $order->manual_collected_cents = $order->payment_status === 'paid' ? ($oldTotal ?? 0) : 0;
            }
            if (!$order->payment) $order->manual_refunded_cents ??= $order->payment_status === 'refunded' ? $order->collected_cents : 0;
            $eventFields = ['name', 'quantity', 'original_unit_cents', 'discount_bps', 'unit_cents'];
            $before = $items->map(fn ($item) => $item->only($eventFields))->values()->all();
            $stock = function ($productId, int $difference, string $name) use ($inventories, $order, $user) {
                if (! $difference) {
                    return null;
                }
                $inv = $inventories->get($productId);
                if (! $inv || $inv->quantity_on_hand === null) {
                    return null;
                }
                $before = $inv->quantity_on_hand;
                $after = (int) round((float) $before * 1000) - $difference * 1000;
                if ($after < -999999999000 || $after > 999999999000) {
                    $this->fail('Inventory limit exceeded for '.$name.'.');
                }
                $inv->update(['quantity_on_hand' => $after / 1000]);
                InventoryMovement::create(['product_id' => $productId, 'quantity_change' => -$difference, 'quantity_before' => $before, 'quantity_after' => $after / 1000, 'reason' => 'Order amended '.$order->number, 'created_by' => $user, 'created_at' => now()]);
                return ['before' => (float) $before, 'after' => $after / 1000];
            };
            foreach ($items as $id => $item) {
                $row = $data['items'][$id];
                $qty = (int) $row['quantity'];
                $difference = $qty - (int) $item->quantity;
                $restock = $fulfilled ? filter_var($row['restock_returned'] ?? false, FILTER_VALIDATE_BOOLEAN) : true;
                if ($fulfilled && $restock && $difference >= 0) $this->fail('Only a reduced or removed quantity can be returned to stock.');
                if ($fulfilled && $difference < 0) $stockReturns[] = ['product_id'=>$item->product_id, 'name'=>$item->name, 'quantity'=>-$difference, 'restocked'=>$restock && (bool)$item->stock_deducted && isset($inventories[$item->product_id]) && $inventories[$item->product_id]->quantity_on_hand !== null];
                if ($item->stock_deducted) {
                    $movement = $stock($item->product_id, $difference < 0 && !$restock ? 0 : $difference, $item->name);
                    if ($difference > 0 && $movement) {
                        $item->inventory_shortage_quantity = (float) ($item->inventory_shortage_quantity ?? 0) + max(0, $difference - max(0, $movement['before']));
                    } elseif ($difference < 0) {
                        $item->inventory_shortage_quantity = max(0, (float) ($item->inventory_shortage_quantity ?? 0) + $difference);
                    }
                }
                if (! $qty) {
                    $item->delete();

                    continue;
                }
                $unit = self::cents($row['unit_price']);
                $priceFields = [];
                if (isset($row['discount_percent'])) {
                    $discount = self::cents($row['discount_percent']);
                    $priceFields = ['original_unit_cents' => $unit, 'discount_bps' => $discount];
                    $unit = $this->discountedUnit($unit, $discount);
                } elseif ($unit !== (int) $item->unit_cents) {
                    $priceFields = ['original_unit_cents' => $unit, 'discount_bps' => 0];
                }
                $changes = ['quantity' => $qty, 'unit_cents' => $unit, 'line_cents' => $qty * $unit, 'inventory_shortage_quantity' => $item->inventory_shortage_quantity];
                // Price/contact changes do not invalidate a physical packing check.
                if ($difference !== 0) {
                    $changes += ['packed' => false, 'packed_by' => null, 'packed_at' => null];
                }
                $item->update($changes + $priceFields);
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
                $pricing = $prices[$p->id];
                $entered = isset($row['unit_price']) && $row['unit_price'] !== '' ? self::cents($row['unit_price']) : null;
                if (isset($row['discount_percent'])) {
                    $base = $entered ?? $pricing['original_unit_cents'];
                    $discount = self::cents($row['discount_percent']);
                } elseif ($entered === null || $entered === $pricing['unit_cents']) {
                    $base = $pricing['original_unit_cents'];
                    $discount = $pricing['discount_bps'];
                } else {
                    $base = $entered;
                    $discount = 0;
                }
                if ($base === null) {
                    $this->fail('Enter a selling price for '.$p->premium_marketing_name.'.');
                }
                $unit = $this->discountedUnit($base, $discount);
                $tracked = $inventories->has($p->id) && $inventories[$p->id]->quantity_on_hand !== null;
                $shortage = null;
                if ($tracked) {
                    $movement = $stock($p->id, $qty, $p->premium_marketing_name);
                    $shortage = max(0, $qty - max(0, $movement['before']));
                }
                $order->items()->create(['product_id' => $p->id, 'name' => $p->premium_marketing_name, 'product_code' => $p->product_code, 'qr_code' => $p->qr_code, 'stock_deducted' => $tracked, 'inventory_shortage_quantity' => $shortage, 'quantity' => $qty, 'unit_cents' => $unit, 'line_cents' => $qty * $unit,
                    'original_unit_cents' => $base, 'discount_bps' => $discount]);
            }
            if ($order->items()->count() > 100) {
                $this->fail('An order can contain up to 100 different products.');
            }
            if (! $order->items()->exists()) {
                $this->fail('Keep at least one item, or cancel the order instead.');
            }
            $subtotal = (int) $order->items()->sum('line_cents');
            $order->order_discount_cents=(int)round($subtotal*$order->order_discount_bps/10000);$subtotal-=$order->order_discount_cents;
            $delivery = $data['fulfillment'] === 'pickup' ? 0 : self::cents($data['delivery']);
            $route=[];
            $routeFields=['delivery_service','delivery_service_name','delivery_from_postal','delivery_to_postal','delivery_from_zone','delivery_to_zone','delivery_rate_cents'];
            if($data['fulfillment']==='pickup'){
                $route=array_fill_keys($routeFields,null);
                $order->delivery_scheduled_at = null;
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
            if($order->payment_method==='credit'){
                $profile=\App\Models\Customer::lockForUpdate()->findOrFail($order->customer_id);
                $delta=max(0,$subtotal+$delivery+$tax-max(0,$order->final_total_cents));
                $order->credit_warning=app(CustomerTerms::class)->check($profile,$order->source==='pos'?'pos':'website','credit',$delta);
            }
            $order->subtotal_cents = $subtotal;
            $order->delivery_cents = $delivery;
            $order->tax_cents = $tax;
            if ($order->final_total_cents < (int)$order->refund_credit_cents || $tax < (int)$order->refund_credit_tax_cents
                || $subtotal + $delivery < (int)$order->refund_credit_cents - (int)$order->refund_credit_tax_cents) {
                $this->fail('The revised amounts cannot be below sales credits already issued. Keep those credits in the revised calculation.');
            }
            if (($data['tax_mode'] ?? 'manual') === 'recalculate') {
                $order->tax_basis_points = $rate;
            }
            if (in_array($order->status, ['warehouse_pending', 'packing', 'ready_to_dispatch'])) {
                $order->status = 'warehouse_issue';
                $order->warehouse_note = 'Order changed by admin; send back to warehouse to check changed and outstanding items.';
            }
            $order->revision++;
            $order->save();
            if ($fulfilled) {
                $snapshotAfter = $this->snapshot($order->fresh(['items','payment','settlements']));
                $record = OrderRevision::create(['order_id'=>$order->id, 'customer_id'=>$order->customer_id, 'created_by'=>$user,
                    'revision'=>$order->revision, 'invoice_number'=>$order->invoice?->label, 'reason'=>$data['reason'],
                    'difference_cents'=>$order->final_total_cents - $oldTotal, 'tax_difference_cents'=>$tax - $snapshotBefore['order']['tax_cents'],
                    'before'=>$snapshotBefore, 'after'=>$snapshotAfter, 'stock_returns'=>$stockReturns, 'brand'=>app(DocumentTheme::class)->brand()]);
                $record->update(['number'=>'ADJ-'.str_pad((string)$record->id, 5, '0', STR_PAD_LEFT)]);
            }
            $scheduleNote = $previousSchedule?->timestamp !== $order->delivery_scheduled_at?->timestamp
                ? ' · Delivery date & time (Toronto): '.(\App\Support\BusinessTime::format($previousSchedule) ?? 'Not set').' → '.(\App\Support\BusinessTime::format($order->delivery_scheduled_at) ?? 'Not set')
                : '';
            $after = $order->items()->get()->map(fn ($item) => $item->only($eventFields))->all();
            $order->events()->create(['user_id' => $user, 'description' => ($fulfilled ? $record->number.' · Super Admin correction after delivery. ' : '').'Order amended: '.$data['reason'].$scheduleNote.' · fulfillment '.$order->fulfillment.' · total CAD '.number_format(($oldTotal ?? 0) / 100, 2).' → '.number_format($order->final_total_cents / 100, 2).' · Items before: '.json_encode($before, JSON_UNESCAPED_UNICODE).' · Items after: '.json_encode($after, JSON_UNESCAPED_UNICODE).($fulfilled ? ' · Stock returns: '.json_encode($stockReturns, JSON_UNESCAPED_UNICODE) : ''), 'created_at' => now()]);
        }, 3);
    }

    private function snapshot(Order $order): array
    {
        return ['customer'=>$order->customer?->only(['id','name','business_name','account_type','email','phone']), 'order'=>$order->only(['number','first_name','last_name','email','phone','address','city','province','postal_code','country','fulfillment','pickup_address','delivery_scheduled_at','status','subtotal_cents','delivery_cents','tax_cents','order_discount_cents','refund_credit_cents','refund_credit_tax_cents']),
            'items'=>$order->items()->get()->map(fn($item)=>$item->only(['product_id','name','product_code','quantity','original_unit_cents','discount_bps','unit_cents','line_cents','packed','packed_at']))->all(),
            'total_cents'=>$order->final_total_cents, 'adjusted_total_cents'=>$order->adjusted_total_cents,
            'net_received_cents'=>$order->net_received_cents, 'balance_cents'=>$order->balance_cents];
    }

    public function settle(Order $order, array $data, int $user): void
    {
        DB::transaction(function () use ($order, $data, $user) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $order->assertEditable();
            if ((int) $order->revision !== (int) $data['revision']) {
                $this->fail('Order balance changed. Reload before recording payment.');
            }
            if (($order->status === 'cancelled' && $data['direction'] !== 'refund') || ($order->payment && (! $order->payment->paid_at || $order->payment->attention))) {
                $this->fail('Resolve the original payment first.');
            }
            if ($order->final_total_cents === null) {
                $this->fail('Finalize the order total before recording a payment.');
            }
            $amount = self::cents($data['amount']);
            $balance = $order->balance_cents;
            if ($data['direction'] === 'refund' && $amount > $order->net_received_cents - (int)$order->pending_refund_cents) $this->fail('Pending customer refund cheques reserve these funds. Resolve them before recording another refund.');
            if ($amount <= 0 || ($data['direction'] === 'collect' ? $balance < $amount : -$balance < $amount)) {
                $this->fail('The amount exceeds the current amount due or refund due.');
            }
            if ($data['direction'] === 'collect' && $amount > $balance - app(CustomerReceipts::class)->pendingForOrder($order)) {
                $this->fail('Part of this balance is reserved for a cheque awaiting clearance. Resolve the cheque on its split receipt before recording another payment for that amount.');
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
            $settlement = $order->settlements()->create(['receipt_path' => $data['receipt_path'] ?? null, 'amount_cents' => $data['direction'] === 'refund' ? -$amount : $amount, 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'note' => $data['note'], 'user_id' => $user]);
            $order->unsetRelation('settlements');
            if (! $order->payment && $order->balance_cents <= 0 && $order->status !== 'cancelled') {
                $order->payment_status = 'paid';
                $order->paid_at = $order->paid_at ?? now();
            }
            $order->revision++;
            $order->save();
            if ($data['direction'] === 'collect') app(CustomerEmails::class)->order($order, 'payment_received', 'settlement:'.$settlement->id, $amount, $settlement);
            $order->events()->create(['user_id' => $user, 'description' => 'External '.($data['direction'] === 'refund' ? 'refund' : 'collection').' recorded: CAD '.number_format($amount / 100, 2).' via '.$data['method'].' · '.$data['note'], 'created_at' => now()]);


        }, 3);
    }
}
