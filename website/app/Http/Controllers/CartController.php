<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\StorefrontCart;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function index(StorefrontCart $cart)
    {
        return view('pages.cart', ['cart' => $cart->snapshot()]);
    }

    public function save(Request $r, Product $product, StorefrontCart $cart)
    {
        $data = $r->validate(['quantity' => 'required|integer|min:1|max:99']);
        abort_unless($product->is_active && $product->categories()->where('is_active', true)->exists(), 404);
        if ($product->storefront_price === null) {
            throw ValidationException::withMessages(['quantity' => 'Please enquire about this product for pricing.']);
        }
        $items = session('storefront_cart', []);
        $quantity = $r->isMethod('patch') ? $data['quantity'] : (int) ($items[$product->id] ?? 0) + $data['quantity'];
        session(['storefront_cart' => $cart->withQuantity($items, $product, $quantity)]);

        return $this->respond($r, $cart, $r->isMethod('patch') ? 'Cart updated.' : 'Added to cart.');
    }

    public function remove(Request $r, Product $product, StorefrontCart $cart)
    {
        $items = session('storefront_cart', []);
        unset($items[$product->id]);
        session(['storefront_cart' => $items]);

        return $this->respond($r, $cart, 'Product removed.');
    }

    private function respond(Request $r, StorefrontCart $service, string $message)
    {
        $cart = $service->snapshot();
        if ($r->expectsJson()) {
            return response()->json(['message' => $message, 'count' => $cart['count'], 'mini' => view('partials.live-mini-cart', compact('cart'))->render(), 'content' => view('partials.live-cart', compact('cart'))->render()]);
        }

return redirect()->route('theme.cart')->with('cart_status',$message);
    }
}
