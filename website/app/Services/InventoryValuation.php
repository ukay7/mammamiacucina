<?php

namespace App\Services;

use App\Models\Product;
use App\Support\NumberDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryValuation
{
    public const BASE_COLUMNS = [
        'record_id' => 'Record ID', 'product_code' => 'Product code',
        'premium_marketing_name' => 'Product name', 'supplier_unit_price_eur' => 'Product price (EUR)',
        'inventory_quantity' => 'Inventory quantity', 'inventory_value' => 'Total value (EUR)',
    ];

    public function extraLabels(): array
    {
        return array_diff_key(app(ProductColumns::class)->labels(), self::BASE_COLUMNS);
    }

    public function selected(Request $request): array
    {
        $request->validate(['columns' => 'nullable|string|max:2000']);
        $selected = array_values(array_unique(array_filter(explode(',', (string) $request->input('columns', '')))));
        if (array_diff($selected, array_keys($this->extraLabels()))) {
            throw ValidationException::withMessages(['columns' => 'Unknown product column selected.']);
        }

        return array_values(array_intersect(array_keys($this->extraLabels()), $selected));
    }

    public function headers(array $selected): array
    {
        return self::BASE_COLUMNS + array_intersect_key($this->extraLabels(), array_flip($selected));
    }

    public function withColumns(Builder $query): Builder
    {
        return $query->withCount('documents')->with(['categories', 'pricingDraft', 'media' => fn ($q) => $q->coverImage()]);
    }

    public function values(Product $product, bool $withExtras = true): array
    {
        return [
            'record_id' => $product->id,
            'product_code' => $product->product_code,
            'premium_marketing_name' => $product->premium_marketing_name,
            'supplier_unit_price_eur' => $product->supplier_unit_price_eur ?? '0',
            'inventory_quantity' => $product->stock_quantity,
            'inventory_value' => $product->stock_value,
        ] + ($withExtras ? app(ProductColumns::class)->values($product) : []);
    }

    public function missingPrice(Product $product): bool
    {
        return $product->supplier_unit_price_eur === null || (float) $product->supplier_unit_price_eur <= 0;
    }

    public function format(mixed $value, string $key): string
    {
        return match ($key) {
            'supplier_unit_price_eur' => NumberDisplay::decimal($value, 8, true, '0'),
            'inventory_value' => NumberDisplay::decimal($value),
            'inventory_quantity' => NumberDisplay::quantity($value, 'Not set'),
            default => NumberDisplay::field($value, $key),
        };
    }

    public function numeric(string $key): bool
    {
        return in_array($key, ['record_id', 'supplier_unit_price_eur', 'inventory_quantity', 'inventory_value'], true)
            || NumberDisplay::mode($key) !== null;
    }
}
