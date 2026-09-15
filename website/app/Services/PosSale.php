<?php

namespace App\Services;

use App\Models\{GeneralSetting, Inventory, InventoryMovement, Order, Product};
use Illuminate\Support\Facades\{Crypt, DB};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosSale
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['sale' => $message]);
    }

    private function lines(array $items, bool $lock = false): array
    {
        $query = Product::whereIn('id', array_column($items, 'id'))->orderBy('id');
        $products = ($lock ? $query->lockForUpdate() : $query)->get()->keyBy('id');
        if ($products->count() !== count($items)) {
            $this->fail('A product was removed. Review the sale.');
        }
        $lines = [];
        foreach (collect($items)->sortBy('id') as $item) {
            $product = $products[$item['id']];
            if (!$product->is_active || $product->total_selling_price_cad === null || (float) $product->total_selling_price_cad < 0) {
                $this->fail($product->premium_marketing_name.' is inactive or has no selling price.');
            }
            $query = Inventory::where('product_id', $product->id);
            $inventory = ($lock ? $query->lockForUpdate() : $query)->first();
            if ($inventory && $inventory->quantity_on_hand !== null && (float) $inventory->quantity_on_hand < $item['quantity']) {
                $this->fail('Not enough stock for '.$product->premium_marketing_name.'. Available: '.$inventory->quantity_on_hand);
            }
            $lines[] = ['product' => $product, 'inventory' => $inventory, 'quantity' => (int) $item['quantity'],
                'unit_cents' => (int) round((float) $product->total_selling_price_cad * 100)];
        }
        return $lines;
    }

    private function snapshot(array $lines): array
    {
        return array_map(fn ($line) => ['id' => $line['product']->id, 'quantity' => $line['quantity'], 'unit_cents' => $line['unit_cents']], $lines);
    }

    public function quote(array $items, string $fulfillment, int $userId): array
    {
        $lines = $this->lines($items);
        $settings = GeneralSetting::findOrFail(1);
        $subtotal = array_sum(array_map(fn ($line) => $line['unit_cents'] * $line['quantity'], $lines));
        if ($subtotal > 999999999) $this->fail('This sale exceeds the supported total.');
        $delivery = $fulfillment === 'delivery' ? $settings->delivery_cents : 0;
        $tax = $settings->taxFor($subtotal);
        $data = ['token' => (string) Str::uuid(), 'user_id' => $userId, 'expires' => now()->addMinutes(30)->timestamp,
            'items' => $this->snapshot($lines), 'fulfillment' => $fulfillment, 'delivery' => $delivery, 'rate' => $settings->tax_basis_points];
        return ['quote' => Crypt::encryptString(json_encode($data)), 'subtotal' => $subtotal, 'delivery' => $delivery,
            'tax' => $tax, 'total' => $subtotal + $delivery + $tax, 'tax_percent' => $settings->tax_basis_points / 100,
            'items' => array_map(fn ($line) => ['name' => $line['product']->premium_marketing_name, 'quantity' => $line['quantity'],
                'unit_cents' => $line['unit_cents']], $lines)];
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
            // A shared lock serializes checkout against stock and settings changes. A retry never deducts twice.
            $settings = GeneralSetting::lockForUpdate()->findOrFail(1);
            if ($existing = Order::where('checkout_token', $quote['token'])->first()) {
                if ($existing->source !== 'pos' || (int) $existing->created_by !== $userId) $this->fail('Invalid sale reference.');
                return $existing;
            }
            if ($quote['expires'] < now()->timestamp) $this->fail('Sale review expired. Review the sale again.');
            $delivery = $quote['fulfillment'] === 'delivery' ? $settings->delivery_cents : 0;
            if ($quote['rate'] !== $settings->tax_basis_points || $quote['delivery'] !== $delivery) {
                $this->fail('Delivery or tax changed. Review the sale again before collecting payment.');
            }
            $lines = $this->lines($quote['items'], true);
            if ($this->snapshot($lines) !== $quote['items']) $this->fail('A price changed. Review the sale again before collecting payment.');
            $subtotal = array_sum(array_map(fn ($line) => $line['unit_cents'] * $line['quantity'], $lines));
            $pickup = $quote['fulfillment'] === 'pickup';
            if (!$pickup) {
                foreach (['first_name', 'phone', 'address', 'city', 'province', 'postal_code', 'country'] as $field) {
                    if (empty($customer[$field])) $this->fail('Customer name, phone and full address are required for delivery.');
                }
            }
            if ($pickup && $customer['payment_status'] !== 'paid') $this->fail('Record payment received before completing a counter sale.');
            $details = [];
            foreach (['first_name','last_name','email','phone','address','city','province','postal_code','country'] as $field) {
                $details[$field] = $customer[$field] ?? '';
            }
            $details['first_name'] = $details['first_name'] ?: 'Walk-in customer';
            if ($pickup) {
                foreach (['address','city','province','postal_code','country'] as $field) $details[$field] = '';
            }
            $order = Order::create($details + [
                'checkout_token' => $quote['token'], 'number' => 'POS-'.Str::ulid(),
                'notes' => $customer['notes'] ?? null, 'source' => 'pos', 'created_by' => $userId,
                'fulfillment' => $quote['fulfillment'], 'status' => $pickup ? 'delivered' : 'placed',
                'subtotal_cents' => $subtotal, 'delivery_cents' => $delivery, 'tax_cents' => $settings->taxFor($subtotal),
                'payment_method' => $customer['payment_method'], 'payment_status' => $customer['payment_status'],
                'paid_at' => $customer['payment_status'] === 'paid' ? now() : null,
            ]);
            $order->update(['number' => 'mmc-'.$order->id]);
            foreach ($lines as $line) {
                ['product' => $product, 'inventory' => $inventory, 'quantity' => $quantity, 'unit_cents' => $price] = $line;
                $tracked = $inventory && $inventory->quantity_on_hand !== null;
                $order->items()->create(['product_id' => $product->id, 'name' => $product->premium_marketing_name,
                    'product_code' => $product->product_code, 'qr_code' => $product->qr_code, 'stock_deducted' => $tracked,
                    'quantity' => $quantity, 'unit_cents' => $price, 'line_cents' => $quantity * $price]);
                if ($tracked) {
                    $before = $inventory->quantity_on_hand;
                    $after = ((int) round((float) $before * 1000) - $quantity * 1000) / 1000;
                    $inventory->update(['quantity_on_hand' => $after]);
                    InventoryMovement::create(['product_id' => $product->id, 'quantity_change' => -$quantity,
                        'quantity_before' => $before, 'quantity_after' => $after, 'reason' => 'POS order '.$order->number,
                        'created_by' => $userId, 'created_at' => now()]);
                }
            }
            $order->events()->create(['user_id' => $userId, 'description' => 'Quick Sale created · '.($pickup ? 'Collected in store' : 'Delivery').' · '.$customer['payment_method'].' '.$customer['payment_status'], 'created_at' => now()]);
            return $order;
        }, 3);
    }
}