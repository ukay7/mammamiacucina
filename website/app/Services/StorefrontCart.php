<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class StorefrontCart
{
    public function withQuantity(array $items, Product $product, int $quantity): array
    {
        if ($quantity > 99) {
            throw ValidationException::withMessages(['quantity' => 'Maximum quantity is 99 per product.']);
        }
        if (count($items) >= 100 && ! isset($items[$product->id])) {
            throw ValidationException::withMessages(['quantity' => 'Your cart can contain up to 100 different products.']);
        }
        $stock = $product->inventory?->quantity_on_hand;
        if ($stock !== null && $quantity > (float) $stock) {
            throw ValidationException::withMessages(['quantity' => 'The requested quantity exceeds the available stock.']);
        }

        $items[$product->id] = $quantity;

        return $items;
    }

    public function checkoutIssue(array $cart): ?string
    {
        if (! $cart['items']) {
            return 'Your cart is empty.';
        }
        if (count($cart['items']) !== count(session('storefront_cart', []))) {
            return 'Some products are no longer available. Please review your cart.';
        }

        return null;
    }

    public function snapshot(): array
    {
        $quantities = session('storefront_cart', []);
        $items = [];
        $count = 0;
        $total = 0;
        $products = Product::whereIn('id', array_keys($quantities))->where('is_active', true)->whereHas('categories', fn ($q) => $q->where('is_active', true))->whereRaw(Product::storefrontPriceSql().' IS NOT NULL')->with(['inventory', 'media' => fn ($q) => $q->coverImage()])->get();
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
