<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Services\StorefrontCart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function create(StorefrontCart $service)
    {
        $cart = $service->snapshot();
        if (! $cart['items']) {
            return redirect()->route('theme.cart')->with('cart_status', 'Your cart is empty.');
        }
        if (count($cart['items']) !== count(session('storefront_cart', []))) {
            return redirect()->route('theme.cart')->with('cart_status', 'Some products are no longer available. Please review your cart.');
        }
        $quote = [];
        foreach ($cart['items'] as $item) {
            $quote[$item['product']->id] = [$item['quantity'], $item['unit_cents']];
        }
        $token = (string) Str::uuid();
        session(['checkout_token' => $token, 'checkout_quote' => $quote]);

        return response()->view('pages.checkout', compact('cart', 'token'))->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $r)
    {
        $d = $r->validate(['checkout_token' => 'required|uuid', 'first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100', 'email' => 'required|email|max:255', 'phone' => 'required|string|max:40', 'address' => 'required|string|max:255', 'city' => 'required|string|max:100', 'province' => 'required|string|max:100', 'postal_code' => 'required|string|max:30', 'country' => 'required|string|max:100', 'notes' => 'nullable|string|max:2000']);
        // A successful retry returns the same order; the token must belong to this session.
        if (session('last_order_token') === $d['checkout_token']) {
            return redirect()->route('theme.order-success');
        }
        if (! session('checkout_token') || ! hash_equals(session('checkout_token'), $d['checkout_token'])) {
            throw ValidationException::withMessages(['cart' => 'Checkout expired. Open checkout again from your cart.']);
        }
        $quantities = session('storefront_cart', []);
        $quote = session('checkout_quote', []);
        if (! $quantities || count($quantities) > 100) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty or invalid. Please review it.']);
        }
        $order = DB::transaction(function () use ($d, $quantities, $quote) {
            if ($existing = Order::where('checkout_token', $d['checkout_token'])->first()) {
                return $existing;
            }
            $products = Product::whereIn('id', array_keys($quantities))->orderBy('id')->lockForUpdate()->get();
            if ($products->count() !== count($quantities)) {
                throw ValidationException::withMessages(['cart' => 'A product is no longer available. Please review your cart.']);
            }
            $lines = [];
            $subtotal = 0;
            foreach ($products as $p) {
                $qty = (int) $quantities[$p->id];
                $price = (int) round((float) $p->total_selling_price_cad * 100);
                if (! $p->is_active || ! $p->categories()->where('is_active', true)->exists() || $p->total_selling_price_cad === null || $price < 0 || $qty < 1 || $qty > 99) {
                    throw ValidationException::withMessages(['cart' => 'A product is no longer available. Please review your cart.']);
                }
                if (($quote[$p->id] ?? null) !== [$qty, $price]) {
                    throw ValidationException::withMessages(['cart' => 'Your cart or a price changed. Return to checkout from your cart to review the updated amount.']);
                }
                $inventory = Inventory::where('product_id', $p->id)->lockForUpdate()->first();
                if ($inventory && $inventory->quantity_on_hand !== null && (float) $inventory->quantity_on_hand < $qty) {
                    throw ValidationException::withMessages(['cart' => 'Not enough stock for '.$p->premium_marketing_name.'. Please update your cart.']);
                }
                $lines[] = [$p, $qty, $price, $inventory];
                $subtotal += $price * $qty;
            }
            $order = Order::create(array_merge($d, ['number' => 'MMC-'.strtoupper((string) Str::ulid()), 'subtotal_cents' => $subtotal, 'payment_method' => 'cash', 'payment_status' => 'unpaid', 'status' => 'placed']));
            foreach ($lines as [$p,$qty,$price,$inventory]) {
                $order->items()->create(['product_id' => $p->id, 'name' => $p->premium_marketing_name, 'product_code' => $p->product_code, 'qr_code' => $p->qr_code, 'quantity' => $qty, 'unit_cents' => $price, 'line_cents' => $price * $qty]);
                if ($inventory && $inventory->quantity_on_hand !== null) {
                    $before = $inventory->quantity_on_hand;
                    $after = ((int) round((float) $before * 1000) - $qty * 1000) / 1000;
                    $inventory->update(['quantity_on_hand' => $after]);
                    InventoryMovement::create(['product_id' => $p->id, 'quantity_change' => -$qty, 'quantity_before' => $before, 'quantity_after' => $after, 'reason' => 'Order '.$order->number, 'created_by' => null, 'created_at' => now()]);
                }
            }

            return $order;
        }, 3);
        session(['last_order_id' => $order->id, 'last_order_token' => $order->checkout_token]);
        session()->forget(['storefront_cart', 'checkout_token', 'checkout_quote']);

        return redirect()->route('theme.order-success');
    }

    public function success()
    {
        $order = Order::with('items')->find(session('last_order_id'));
        if (! $order) {
            return redirect()->route('theme.cart');
        }

        return response()->view('pages.order-success', compact('order'))->header('Cache-Control', 'no-store, private');
    }
}
