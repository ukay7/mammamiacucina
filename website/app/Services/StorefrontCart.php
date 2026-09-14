<?php

namespace App\Services;

use App\Models\Product;

class StorefrontCart
{
    public function snapshot(): array
    {
        $quantities = session('storefront_cart', []);
        $items = [];
        $count = 0;
        $total = 0;
        $products = Product::whereIn('id', array_keys($quantities))->where('is_active', true)->whereHas('categories', fn ($q) => $q->where('is_active', true))->whereNotNull('total_selling_price_cad')->with(['inventory', 'media' => fn ($q) => $q->where('kind', 'image')->limit(1)])->get();
        foreach ($products as $product) {
            $quantity = max(1, min(99, (int) $quantities[$product->id]));
            $cents = (int) round((float) $product->total_selling_price_cad * 100);
            $items[] = ['product' => $product, 'quantity' => $quantity, 'unit_cents' => $cents, 'line_cents' => $cents * $quantity];
            $total += $cents * $quantity;
            $count += $quantity;
        }

        return compact('items', 'count', 'total');
    }
}
