<?php

namespace App\Services;

use App\Models\Product;
use App\Support\NumberDisplay;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Shared, database-backed filters for stock management and valuation exports. */
class InventoryProducts
{
    public function filters(Request $request): array
    {
        $rules = [
            'q' => 'nullable|string|max:200', 'product' => 'nullable|string|max:200',
            'code' => 'nullable|string|max:100', 'record_id' => 'nullable|integer|min:1',
            'category' => 'nullable|integer|exists:categories,id', 'uom' => 'nullable|string|max:40',
            'warehouse' => 'nullable|integer|exists:warehouses,id',
            'active' => 'nullable|in:0,1',
            'stock' => 'nullable|in:tracked,unset,healthy,low,out,shortage',
            'pricing' => 'nullable|in:missing,priced',
            'quantity_min' => 'nullable|numeric', 'quantity_max' => 'nullable|numeric',
            'threshold_min' => 'nullable|numeric|min:0', 'threshold_max' => 'nullable|numeric|min:0',
            'price_min' => 'nullable|numeric|min:0', 'price_max' => 'nullable|numeric|min:0',
            'value_min' => 'nullable|numeric', 'value_max' => 'nullable|numeric',
            'per_page' => 'nullable|in:10,20,50,100',
        ];
        foreach (['quantity', 'threshold', 'price', 'value'] as $field) {
            if ($request->filled($field.'_min')) {
                $rules[$field.'_max'] .= '|gte:'.$field.'_min';
            }
        }

        return $request->validate($rules);
    }

    public function query(array $filters, ?int $warehouseId = null): Builder
    {
        $query = Product::query()->leftJoin('inventories as stock_totals', 'stock_totals.product_id', '=', 'products.id');
        $quantity = 'stock_totals.quantity_on_hand';
        if ($warehouseId !== null) {
            $query->leftJoin('warehouse_inventories as location_stock', function ($join) use ($warehouseId) {
                $join->on('location_stock.product_id', '=', 'products.id')->where('location_stock.warehouse_id', $warehouseId);
            });
            $quantity = 'location_stock.quantity_on_hand';
        }

        $threshold = 'COALESCE(stock_totals.low_stock_threshold, 0)';
        $price = 'COALESCE(products.supplier_unit_price_eur, 0)';
        // Round each line once; totals sum those same lines, using SQL decimal arithmetic on MySQL.
        $value = 'ROUND('.$price.' * COALESCE('.$quantity.', 0), 2)';
        $query->select('products.*')->selectRaw($quantity.' as stock_quantity, '.$threshold.' as stock_threshold, '.$value.' as stock_value');

        if (filled($filters['q'] ?? null)) {
            $query->searchTerm($filters['q'], true);
        }
        if (filled($filters['product'] ?? null)) {
            $query->where('products.premium_marketing_name', 'like', '%'.$filters['product'].'%');
        }
        if (filled($filters['code'] ?? null)) {
            $query->where('products.product_code', 'like', '%'.$filters['code'].'%');
        }
        if (filled($filters['record_id'] ?? null)) {
            $query->where('products.id', $filters['record_id']);
        }
        if (filled($filters['uom'] ?? null)) {
            $query->where('products.uom', 'like', '%'.$filters['uom'].'%');
        }
        if (filled($filters['category'] ?? null)) {
            $query->whereHas('categories', fn ($q) => $q->where('categories.id', $filters['category']));
        }
        if (isset($filters['active'])) {
            $query->where('products.is_active', $filters['active']);
        }

        match ($filters['stock'] ?? '') {
            'tracked' => $query->whereNotNull($quantity),
            'unset' => $query->whereNull($quantity),
            'healthy' => $query->whereRaw($quantity.' > '.$threshold),
            'low' => $query->where($quantity, '>', 0)->whereRaw($quantity.' <= '.$threshold),
            'out' => $query->where($quantity, 0),
            'shortage' => $query->where($quantity, '<', 0),
            default => null,
        };
        if (($filters['pricing'] ?? '') === 'missing') {
            $query->whereRaw($price.' <= 0');
        }
        if (($filters['pricing'] ?? '') === 'priced') {
            $query->whereRaw($price.' > 0');
        }

        foreach (['quantity' => $quantity, 'threshold' => $threshold, 'price' => $price, 'value' => $value] as $field => $expression) {
            foreach (['min' => '>=', 'max' => '<='] as $suffix => $operator) {
                if (isset($filters[$field.'_'.$suffix])) {
                    $query->whereRaw($expression.' '.$operator.' ?', [$filters[$field.'_'.$suffix]]);
                }
            }
        }

        return $query->orderBy('products.premium_marketing_name')->orderBy('products.id');
    }

    public function stockSummary(Builder $query): array
    {
        $row = DB::query()->fromSub((clone $query)->reorder()->toBase(), 'matching_stock')
            ->selectRaw('COUNT(*) as records, COALESCE(SUM(stock_quantity), 0) as quantity, SUM(CASE WHEN stock_quantity IS NULL THEN 1 ELSE 0 END) as unset_quantities')
            ->first();
        $quantity = BigDecimal::of((string) $row->quantity);

        return ['records' => (int) $row->records, 'unset_quantities' => (int) $row->unset_quantities,
            'quantity' => NumberDisplay::quantity((string) $quantity),
            'quantity_times_five' => NumberDisplay::quantity((string) $quantity->multipliedBy(5))];
    }

    public function summary(Builder $query): array
    {
        // A subquery prevents joins, optional columns and pagination from changing the total.
        $row = DB::query()->fromSub((clone $query)->reorder()->toBase(), 'valued_products')
            ->selectRaw('COUNT(*) as records, COALESCE(SUM(stock_value), 0) as total, SUM(CASE WHEN supplier_unit_price_eur IS NULL OR supplier_unit_price_eur <= 0 THEN 1 ELSE 0 END) as missing_prices')
            ->first();

        return ['records' => (int) $row->records, 'total' => number_format((float) $row->total, 2, '.', ''), 'missing_prices' => (int) $row->missing_prices];
    }
}
