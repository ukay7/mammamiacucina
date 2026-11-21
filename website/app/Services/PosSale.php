<?php

namespace App\Services;

use App\Models\{GeneralSetting, Inventory, InventoryMovement, Order, Product, User};
use Illuminate\Support\Facades\{Crypt, DB};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosSale
{
    public static function adjustmentRules(): array
    {
        return [
            'items.*.price_override_cents' => 'nullable|integer|min:0|max:999999999',
            'items.*.manual_discount_bps' => 'nullable|integer|min:0|max:10000',
        ];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['sale' => $message]);
    }

    private function lines(array $items, \App\Models\Customer $customer, bool $lock = false): array
    {
        $query = Product::whereIn('id', array_column($items, 'id'))->orderBy('id');
        $products = ($lock ? $query->lockForUpdate() : $query)->get()->keyBy('id');
        if ($products->count() !== count($items)) {
            $this->fail('A product was removed. Review the sale.');
        }
        $lines = [];
        foreach (collect($items)->sortBy('id') as $item) {
            $product = $products[$item['id']];
            $pricing = app(CustomerTerms::class)->price($product, $customer);
            $price=$pricing['unit_cents'];
            if (!$product->is_active || $price === null || $price < 0) {
                $this->fail($product->premium_marketing_name.' is inactive or has no selling price.');
            }
            $query = Inventory::where('product_id', $product->id);
            $inventory = ($lock ? $query->lockForUpdate() : $query)->first();
            $adjustment = [];
            $override = $item['price_override_cents'] ?? null;
            $manualDiscount = (int) ($item['manual_discount_bps'] ?? 0);
            if ($override !== null || $manualDiscount > 0) {
                $base = $override === null ? $price : (int) $override;
                $adjustment = [
                    'price_override_cents' => $override === null ? null : $base,
                    'manual_discount_bps' => $manualDiscount,
                    'customer_unit_cents' => $price,
                ];
                $pricing = [
                    'original_unit_cents' => $base,
                    'discount_bps' => $manualDiscount,
                    'unit_cents' => $base - (int) round($base * $manualDiscount / 10000),
                ];
            }
            $lines[] = ['product' => $product, 'inventory' => $inventory, 'quantity' => (int) $item['quantity'],
                'adjustment' => $adjustment] + $pricing;
        }
        return $lines;
    }

    private function snapshot(array $lines): array
    {
        return array_map(fn ($line) => ['id' => $line['product']->id, 'quantity' => $line['quantity'], 'unit_cents' => $line['unit_cents'],'original_unit_cents'=>$line['original_unit_cents'],'discount_bps'=>$line['discount_bps']] + $line['adjustment'], $lines);
    }

    public function quote(array $items, string $fulfillment, int $userId, array $destination=[]): array
    {
        $profile = app(PosCustomer::class)->resolve((int) ($destination['customer_id'] ?? 0));
        $addressSnapshot = [];
        if ($fulfillment === 'delivery' && isset($destination['delivery_address_type'])) {
            $addressSnapshot = app(CustomerDeliveryAddress::class)->resolve($profile, $destination, false);
            $destination = array_replace($destination, $addressSnapshot);
        }
        $lines = $this->lines($items, $profile);
        $settings = GeneralSetting::findOrFail(1);
        $subtotal = array_sum(array_map(fn ($line) => $line['unit_cents'] * $line['quantity'], $lines));
        if ($subtotal > 999999999) $this->fail('This sale exceeds the supported total.');
        $route=[];
        if($fulfillment==='delivery' && $settings->matrix_delivery_enabled){
            app(DeliveryQuote::class)->options($settings->warehouse_postal_code??'',$destination['postal_code']??'',$destination['country']??'');
            $route=app(DeliveryQuote::class)->quote($settings->warehouse_postal_code??'',$destination['postal_code']??'',$destination['delivery_service']??'');
        }
        $delivery = $fulfillment === 'delivery' ? ($route['delivery_cents']??$settings->delivery_cents) : 0;
        $discount=app(CustomerTerms::class)->orderDiscount($profile,$subtotal);
        $subtotal-=$discount;
        $tax = $settings->taxFor($subtotal);
        $data = ['token' => (string) Str::uuid(), 'user_id' => $userId, 'expires' => now()->addMinutes(30)->timestamp,
            'commercial_revision'=>$profile->commercial_revision,'customer_id'=>$profile->id,'pricing_type'=>$profile->user->account_type,'matrix'=>(bool)$settings->matrix_delivery_enabled,'delivery_route'=>$route,'items' => $this->snapshot($lines), 'fulfillment' => $fulfillment, 'delivery' => $delivery, 'rate' => $settings->tax_basis_points];
        if ($addressSnapshot) $data['delivery_address'] = $addressSnapshot + ['type'=>$destination['delivery_address_type']];
        return ['credit_order_warning'=>app(CustomerTerms::class)->warning($profile,$subtotal+$delivery+$tax),'allowed_methods'=>app(CustomerTerms::class)->allowed($profile,'pos'),'credit_warning'=>app(CustomerTerms::class)->warning($profile),'order_discount_cents'=>$discount,'order_discount_bps'=>$profile->order_discount_bps,'delivery_route'=>$route,'quote' => Crypt::encryptString(json_encode($data)), 'subtotal' => $subtotal, 'delivery' => $delivery,
            'tax' => $tax, 'total' => $subtotal + $delivery + $tax, 'tax_percent' => $settings->tax_basis_points / 100,
            'items' => array_map(fn ($line) => ['id'=>$line['product']->id,'name' => $line['product']->premium_marketing_name, 'quantity' => $line['quantity'],
                'subtitle' => $line['product']->italian_subtitle, 'source_name' => $line['product']->original_description,
                'unit_cents' => $line['unit_cents'],'original_unit_cents'=>$line['original_unit_cents'],'discount_bps'=>$line['discount_bps']] + $line['adjustment'], $lines)];
    }

    public function complete(string $encrypted, array $customer, int $userId): Order
    {
        try {
            $quote = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->fail('Invalid sale review. Review the sale again.');
        }
        if (($quote['user_id'] ?? null) !== $userId) $this->fail('This sale review belongs to another staff member.');
        return DB::transaction(function () use ($quote, $customer, $userId) {
            $draft=null;
            if (!empty($customer['pos_draft_id'])) {
                abort_unless(User::with('role')->findOrFail($userId)->isSuper(),403);
                $draft=\App\Models\PosDraft::whereKey($customer['pos_draft_id'])->lockForUpdate()->first();
                if (!$draft) $this->fail('This draft was deleted. Reopen Draft Orders before placing the sale.');
                if ($draft->order_id) {
                    $placed=$draft->order;
                    if ($placed && $placed->checkout_token===$quote['token']) {
                        $placed->assertEditable();
                        return $placed;
                    }
                    $this->fail('This draft has already been placed as an order.');
                }
                if ($draft->revision!==(int)($customer['pos_draft_revision']??-1)) $this->fail('This draft changed in another window. Reopen it from Draft Orders before placing the sale.');
            }
            // A shared lock serializes checkout against stock and settings changes. A retry never deducts twice.
            $settings = GeneralSetting::lockForUpdate()->findOrFail(1);
            $creator = User::with('role')->findOrFail($userId);
            $warehouseSale = $creator->isWarehouseUserType();
            if ($existing = Order::withTrashed()->where('checkout_token', $quote['token'])->first()) {
                if ($draft) $this->fail('This sale reference already belongs to an order. Reopen Draft Orders.');
                if ($existing->source !== 'pos' || (int) $existing->created_by !== $userId) $this->fail('Invalid sale reference.');
                $existing->assertEditable();
                return $existing;
            }
            if ($quote['expires'] < now()->timestamp) $this->fail('Sale review expired. Review the sale again.');
            $deliverySchedule = app(DeliverySchedule::class)->resolve($customer, $quote['fulfillment'] === 'delivery');
            $profile = app(PosCustomer::class)->resolve((int) ($customer['customer_id'] ?? 0), true);
            if (($quote['customer_id'] ?? null) !== $profile->id || ($quote['pricing_type'] ?? null) !== $profile->user->account_type) $this->fail('Customer or pricing type changed. Review the sale again.');
            if(($quote['commercial_revision']??0)!==$profile->commercial_revision)$this->fail('Customer settings changed. Review the sale again.');
            $addresses = app(CustomerDeliveryAddress::class);
            if ($quote['fulfillment'] === 'delivery') {
                $selected = $addresses->resolve($profile, $customer);
                if (isset($quote['delivery_address']) && $quote['delivery_address'] !== $selected + ['type'=>$customer['delivery_address_type'] ?? 'alternate']) {
                    $this->fail('Delivery address changed. Review the sale again before placing the order.');
                }
                $customer = array_replace($customer, $selected);
            }
            $route=[];
            if($quote['fulfillment']==='delivery'){
                if(($quote['matrix']??false)!==(bool)$settings->matrix_delivery_enabled)$this->fail('Delivery settings changed. Review the sale again.');
                if($settings->matrix_delivery_enabled){
                    app(DeliveryQuote::class)->options($settings->warehouse_postal_code??'',$customer['postal_code']??'',$customer['country']??'');
                    $route=app(DeliveryQuote::class)->quote($settings->warehouse_postal_code??'',$customer['postal_code']??'',$customer['delivery_service']??'');
                    if(($quote['delivery_route']??[])!==$route)$this->fail('Delivery destination, service or price changed. Review the sale again before collecting payment.');
                    $customer['postal_code']=$route['delivery_to_postal'];
                }
            }
            $delivery = $quote['fulfillment'] === 'delivery' ? ($route['delivery_cents']??$settings->delivery_cents) : 0;
            if ($quote['rate'] !== $settings->tax_basis_points || $quote['delivery'] !== $delivery) {
                $this->fail('Delivery or tax changed. Review the sale again before collecting payment.');
            }
            $parts = explode(' ', trim($profile->name), 2);
            $customer['first_name']=$parts[0];$customer['last_name']=$parts[1] ?? '';
            $customer['email']=$profile->email;$customer['phone']=trim($customer['phone'] ?? '') ?: $profile->phone;
            $lines = $this->lines($quote['items'], $profile, true);
            if ($this->snapshot($lines) !== $quote['items']) $this->fail('A price changed. Review the sale again before collecting payment.');
            $subtotal = array_sum(array_map(fn ($line) => $line['unit_cents'] * $line['quantity'], $lines));
            $discount=app(CustomerTerms::class)->orderDiscount($profile,$subtotal);$subtotal-=$discount;
            $warning=app(CustomerTerms::class)->check($profile,'pos',$customer['payment_method'],$subtotal+$delivery+$settings->taxFor($subtotal));
            $pickup = $quote['fulfillment'] === 'pickup';
            if (!$pickup) {
                foreach (['first_name', 'phone', 'address', 'city', 'province', 'postal_code', 'country'] as $field) {
                    if (empty($customer[$field])) $this->fail('Customer name, phone and full address are required for delivery.');
                }
            }
            $transfer = in_array($customer['payment_method'],['etransfer','cheque','credit']);
            $customer['payment_status'] = $transfer ? 'unpaid' : $customer['payment_status'];
            if (!in_array($customer['payment_status'], ['paid', 'unpaid'], true)) $this->fail('Choose Paid or Unpaid for this sale.');
            if (!$pickup) $addresses->remember($profile, $customer, $customer);
            $details = [];
            foreach (['first_name','last_name','email','phone','address','city','province','postal_code','country'] as $field) {
                $details[$field] = $customer[$field] ?? '';
            }
            $details['first_name'] = $details['first_name'] ?: 'Walk-in customer';
            if ($pickup) {
                foreach (['address','city','province','postal_code','country'] as $field) $details[$field] = '';
            }
            $order = Order::create($details + $route + app(CustomerTerms::class)->snapshot($profile,$customer['payment_method']) + [
                'order_discount_cents'=>$discount,'credit_warning'=>$warning,
                'customer_id'=>$profile?->id, 'checkout_token' => $quote['token'], 'number' => 'POS-'.Str::ulid(),
                'notes' => $customer['notes'] ?? null, 'source' => 'pos', 'created_by' => $userId,
                'order_channel' => $customer['order_channel'] ?? null,
                'fulfillment' => $quote['fulfillment'], 'pickup_address'=>$pickup ? $settings->pickup_address : null,
                'delivery_scheduled_at' => $deliverySchedule,
                'status' => $warehouseSale ? 'ready_to_dispatch' : 'warehouse_pending',
                'warehouse_round'=>1,
                'warehouse_sent_at'=>now(),
                'subtotal_cents' => $subtotal, 'delivery_cents' => $delivery, 'tax_cents' => $settings->taxFor($subtotal), 'tax_basis_points'=>$settings->tax_basis_points,
                'payment_method' => $customer['payment_method'], 'payment_status' => $customer['payment_status'],
                'paid_at' => $customer['payment_status'] === 'paid' ? now() : null,
            ]);
            $order->update(['number' => 'mmc-'.$order->id]);
            if ($draft) $draft->update(['order_id'=>$order->id,'revision'=>$draft->revision+1,'updated_by'=>$userId]);
            foreach ($lines as $line) {
                ['product' => $product, 'inventory' => $inventory, 'quantity' => $quantity, 'unit_cents' => $price] = $line;
                $tracked = $inventory && $inventory->quantity_on_hand !== null;
                $shortage = $tracked ? max(0, $quantity - max(0, (float) $inventory->quantity_on_hand)) : null;
                $order->items()->create(['product_id' => $product->id, 'name' => $product->premium_marketing_name,
                    'product_code' => $product->product_code, 'qr_code' => $product->qr_code, 'stock_deducted' => $tracked,
                    'inventory_shortage_quantity'=>$shortage,'packed'=>$warehouseSale,'packed_by'=>$warehouseSale ? $userId : null,'packed_at'=>$warehouseSale ? now() : null,'original_unit_cents'=>$line['original_unit_cents'],'discount_bps'=>$line['discount_bps'],'quantity' => $quantity, 'unit_cents' => $price, 'line_cents' => $quantity * $price]);
                if ($tracked) {
                    $before = $inventory->quantity_on_hand;
                    $after = ((int) round((float) $before * 1000) - $quantity * 1000) / 1000;
                    $inventory->update(['quantity_on_hand' => $after]);
                    InventoryMovement::create(['product_id' => $product->id, 'quantity_change' => -$quantity,
                        'quantity_before' => $before, 'quantity_after' => $after, 'reason' => 'POS order '.$order->number,
                        'created_by' => $userId, 'created_at' => now()]);
                }
            }
            $order->events()->create(['user_id' => $userId, 'description' => 'Quick Sale created Â· '.($pickup ? 'Pick up' : 'Delivery').' Â· '.$customer['payment_method'].' '.$customer['payment_status'], 'created_at' => now()]);
            $adjustedCount = count(array_filter($lines, fn ($line) => $line['adjustment'] !== []));
            if ($adjustedCount > 0) {
                $order->events()->create(['user_id' => $userId, 'description' => 'Manual pricing applied in Quick Sale to '.$adjustedCount.' item line(s).', 'created_at' => now()]);
            }
            if ($warehouseSale) app(CustomerInvoicing::class)->issue($order);
            app(CustomerEmails::class)->order($order, 'order_confirmation', 'order-created:'.$order->id);
            return $order;
        }, 3);
    }
}
