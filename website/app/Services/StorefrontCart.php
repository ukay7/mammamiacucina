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
        $products = Product::whereIn('id', array_keys($quantities))->where('is_active', true)->whereHas('categories', fn ($q) => $q->where('is_active', true))->whereNotNull('total_selling_price_cad')->with(['inventory', 'media' => fn ($q) => $q->coverImage()])->get();
        foreach ($products as $product) {
            $quantity = max(1, min(99, (int) $quantities[$product->id]));
            $pricing=auth()->user()?->isCustomer()?app(CustomerTerms::class)->price($product,auth()->user()->customerRecord()):['unit_cents'=>(int)round((float)$product->storefront_price*100),'original_unit_cents'=>(int)round((float)$product->storefront_price*100),'discount_bps'=>0];
            $cents=$pricing['unit_cents'];
            $items[] = $pricing + ['product' => $product, 'quantity' => $quantity, 'unit_cents' => $cents, 'line_cents' => $cents * $quantity];
            $total += $cents * $quantity;
            $count += $quantity;
        }

        $order_discount_cents=auth()->user()?->isCustomer()?app(CustomerTerms::class)->orderDiscount(auth()->user()->customerRecord(),$total):0;
        $total-=$order_discount_cents;
        return compact('items', 'count', 'total','order_discount_cents');
    }
}
