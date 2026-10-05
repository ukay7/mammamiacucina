<?php

namespace App\Services;

use App\Models\Product;

class QuotationProducts
{
    public function details(Product $product): array
    {
        $columns = app(ProductColumns::class)->values($product);
        $pieces = filter_var($columns['pieces_per_carton'], FILTER_VALIDATE_INT);

        return [
            'category' => $product->category?->name ?? $product->categories->first()?->name ?? 'Uncategorized',
            'pieces_per_carton' => $pieces && $pieces > 0 ? $pieces : null,
            'columns' => $columns,
        ];
    }

    // Four decimal places for quoted carton prices; totals remain integer cents.
    public static function scaled(string $value, int $places = 4): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (int) $whole * (10 ** $places) + (int) str_pad(substr($fraction, 0, $places), $places, '0');
    }

    public static function decimal(int $value, int $places = 4): string
    {
        $scale = 10 ** $places;

        return intdiv($value, $scale).'.'.str_pad((string) ($value % $scale), $places, '0', STR_PAD_LEFT);
    }
}
