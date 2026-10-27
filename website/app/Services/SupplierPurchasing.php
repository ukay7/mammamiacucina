<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPricingDraft;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\Warehouse;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SupplierPurchasing
{
    public function products(int $supplierId, ?int $warehouseId = null, ?string $active = '1')
    {
        $filters = $active === null ? [] : ['active' => $active];
        $quantity = $warehouseId ? 'location_stock.quantity_on_hand' : 'stock_totals.quantity_on_hand';

        return app(InventoryProducts::class)->query($filters, $warehouseId)->where('products.supplier_id', $supplierId)
            ->with('pricingDraft')->reorder()->orderByRaw('CASE WHEN '.$quantity.' IS NULL THEN 1 ELSE 0 END')
            ->orderBy('stock_quantity')->orderBy('products.premium_marketing_name')->orderBy('products.id');
    }

    private function validate(array $input): array
    {
        return Validator::make($input, [
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'items' => 'required|array|min:1|max:500',
            'items.*.product_id' => 'required|integer|distinct|exists:products,id',
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000', 'regex:/^\d{1,7}(\.\d{1,3})?$/D'],
            'notes' => 'nullable|string|max:5000',
        ])->validate();
    }

    private function build(array $input, bool $lock = false): array
    {
        $supplier = Supplier::findOrFail($input['supplier_id']);
        if (! $supplier->is_active) {
            $this->fail('supplier_id', 'Choose an active supplier.');
        }
        $warehouse = ! empty($input['warehouse_id']) ? Warehouse::findOrFail($input['warehouse_id']) : null;
        if ($warehouse && ! $warehouse->is_active) {
            $this->fail('warehouse_id', 'Choose an active warehouse.');
        }
        $ids = array_column($input['items'], 'product_id');
        if ($lock) {
            Product::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            ProductPricingDraft::whereIn('product_id', $ids)->orderBy('product_id')->lockForUpdate()->get();
        }
        $products = $this->products($supplier->id, $warehouse?->id, null)->whereIn('products.id', $ids)->get()->keyBy('id');
        $totals = [];
        $items = [];
        foreach ($input['items'] as $entry) {
            $product = $products->get($entry['product_id']);
            if (! $product) {
                $this->fail('items', 'Every selected product must belong to the chosen supplier. Reload the product list.');
            }
            $valuation = app(InventoryValuation::class)->calculate($product);
            if ($valuation['issues'] || $valuation['missing_price']) {
                $this->fail('items', $product->premium_marketing_name.': complete the supplier pricing setup before placing an order.');
            }
            $quantity = (string) BigDecimal::of((string) $entry['quantity'])->toScale(3);
            $lineTotal = BigDecimal::of($valuation['carton_cost'])->multipliedBy($quantity)->toScale(2, RoundingMode::HalfUp);
            $currency = $valuation['currency'];
            $totals[$currency] = ($totals[$currency] ?? BigDecimal::zero())->plus($lineTotal);
            $items[] = ['product_id' => $product->id, 'name' => $product->premium_marketing_name,
                'product_code' => $product->product_code, 'barcode' => $product->qr_code,
                'quantity' => $quantity, 'currency' => $currency, 'unit_cost' => $valuation['carton_cost'],
                'line_total' => (string) $lineTotal,
                'pricing_snapshot' => ['valuation' => $valuation, 'stock_quantity' => $product->stock_quantity]];
        }
        ksort($totals);
        $plan = ['supplier' => $supplier->snapshot(), 'warehouse_id' => $warehouse?->id,
            'warehouse_name' => $warehouse?->name ?? 'All warehouses combined', 'items' => $items,
            'totals' => array_map(fn ($value) => (string) $value->toScale(2), $totals)];
        // Stock can change while the user reviews. The purchase quantities, supplier
        // details and exact prices must still agree with the confirmed preview.
        $signed = $plan;
        foreach ($signed['items'] as &$item) {
            unset($item['pricing_snapshot']['stock_quantity'], $item['pricing_snapshot']['valuation']['inventory_value']);
        }
        unset($item);
        $plan['preview_hash'] = hash_hmac('sha256', json_encode($signed, JSON_THROW_ON_ERROR), config('app.key'));

        return $plan;
    }

    public function preview(array $input): array
    {
        $data = $this->validate($input);

        return DB::transaction(fn () => $this->build($data));
    }

    public function create(array $input, int $actor): SupplierOrder
    {
        $data = $this->validate($input);
        $confirmation = Validator::make($input, ['preview_hash' => 'required|string|size:64', 'request_key' => 'required|uuid'])->validate();

        return DB::transaction(function () use ($data, $confirmation, $actor) {
            Supplier::whereKey($data['supplier_id'])->lockForUpdate()->firstOrFail();
            $existing = SupplierOrder::where('request_key', $confirmation['request_key'])->first();
            if ($existing) {
                if ((int) $existing->created_by !== $actor || ! hash_equals($existing->request_hash, $confirmation['preview_hash'])) {
                    $this->fail('request_key', 'This order request has already been used. Reload before creating another order.');
                }

                return $existing;
            }
            $plan = $this->build($data, true);
            if (! hash_equals($plan['preview_hash'], $confirmation['preview_hash'])) {
                $this->fail('preview_hash', 'Supplier details or product pricing changed. Close this preview and review the order again.');
            }
            $order = SupplierOrder::create(['supplier_id' => $data['supplier_id'], 'supplier_snapshot' => $plan['supplier'],
                'warehouse_id' => $plan['warehouse_id'], 'warehouse_name' => $plan['warehouse_name'],
                'status' => 'placed', 'notes' => $data['notes'] ?? null, 'totals' => $plan['totals'],
                'request_key' => $confirmation['request_key'], 'request_hash' => $plan['preview_hash'], 'created_by' => $actor]);
            $order->update(['number' => 'PO-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);
            foreach ($plan['items'] as $item) {
                $item['unit_cost'] = (string) BigDecimal::of($item['unit_cost'])->toScale(8, RoundingMode::HalfUp);
                $order->items()->create($item);
            }
            $order->events()->create(['user_id' => $actor, 'to_status' => 'placed', 'reason' => 'Supplier order created.']);

            return $order;
        }, 3);
    }

    public function transition(SupplierOrder $order, array $input, int $actor): SupplierOrder
    {
        $data = Validator::make($input, ['status' => 'required|in:placed,completed,deleted', 'revision' => 'required|integer|min:0',
            'reason' => 'nullable|required_if:status,deleted|string|max:2000', 'confirm' => 'accepted'])->validate();

        return DB::transaction(function () use ($order, $data, $actor) {
            $current = SupplierOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($current->status === 'deleted') {
                $this->fail('status', 'Deleted supplier orders are view only.');
            }
            if ((int) $current->revision !== (int) $data['revision']) {
                $this->fail('revision', 'This order changed. Reload before updating it.');
            }
            if ($current->status === $data['status']) {
                return $current;
            }
            $from = $current->status;
            $current->status = $data['status'];
            $current->revision++;
            if ($data['status'] === 'completed') {
                $current->completed_at = now();
                $current->completed_by = $actor;
            } elseif ($data['status'] === 'placed') {
                $current->completed_at = null;
                $current->completed_by = null;
            } else {
                $current->deleted_at = now();
                $current->deleted_by = $actor;
                $current->deletion_reason = $data['reason'];
            }
            $current->save();
            $current->events()->create(['user_id' => $actor, 'from_status' => $from,
                'to_status' => $data['status'], 'reason' => $data['reason'] ?? null]);

            return $current;
        }, 3);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
