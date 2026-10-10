<?php

namespace App\Http\Controllers;

use App\Models\GeneralSetting;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Services\OnlinePayments;
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
        $user=auth()->user(); $parts=explode(' ',trim($user?->name ?? ''),2); $profile=['first_name'=>$parts[0],'last_name'=>$parts[1]??'','email'=>$user?->email,'phone'=>$user?->phone,'country'=>'Canada'];
        $savedAddress=$user->customerRecord()->only(['address','city','province','postal_code','country']);
        $profile=array_merge($profile,array_filter($savedAddress,fn($value)=>$value!==null && $value!==''));
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
        $settings = GeneralSetting::findOrFail(1);
        $charges = ['delivery' => $settings->delivery_cents, 'tax' => $settings->taxFor($cart['total']), 'rate' => $settings->tax_basis_points];
        session(['checkout_charges' => ['delivery' => $settings->delivery_cents, 'rate' => $settings->tax_basis_points, 'matrix' => (bool) $settings->matrix_delivery_enabled, 'pickup_address' => $settings->pickup_address]]);
        $token = (string) Str::uuid();
        session(['checkout_token' => $token, 'checkout_quote' => $quote]);

        return response()->view('pages.checkout', compact('cart', 'token', 'charges', 'profile', 'settings'))->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $r)
    {

        $r->merge(['fulfillment' => $r->input('fulfillment', 'delivery')]);
        $d = $r->validate(['fulfillment'=>'required|in:pickup,delivery','delivery_service'=>'nullable|string|max:20','checkout_token' => 'required|uuid', 'first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100', 'email' => 'required|email|max:255', 'phone' => 'required|string|max:40', 'address' => 'required_if:fulfillment,delivery|nullable|string|max:255', 'city' => 'required_if:fulfillment,delivery|nullable|string|max:100', 'province' => 'required_if:fulfillment,delivery|nullable|string|max:100', 'postal_code' => 'required_if:fulfillment,delivery|nullable|string|max:30', 'country' => 'required_if:fulfillment,delivery|nullable|string|max:100', 'notes' => 'nullable|string|max:2000', 'payment_method' => 'sometimes|required|in:cash,etransfer,card']);
        $d['email'] = $r->user()->email;
        $method = $d['payment_method'] ?? 'cash';
        $d['payment_method'] = $method;
        foreach (['address','city','province','postal_code','country'] as $field) {
            $d[$field] = $d['fulfillment'] === 'pickup' ? '' : ($d[$field] ?? '');
        }
        // A successful retry returns the same order; the token must belong to this session.
        if (session('last_order_token') === $d['checkout_token']) {
            $previous = Order::find(session('last_order_id'));
            if ($previous?->payment && $previous->payment_status !== 'paid') {
                return redirect()->route('payment.show', $previous->payment->reference);
            }

            return redirect()->route('theme.order-success');
        }
        if (! session('checkout_token') || ! hash_equals(session('checkout_token'), $d['checkout_token'])) {
            throw ValidationException::withMessages(['cart' => 'Checkout expired. Open checkout again from your cart.']);
        }
        if ($method === 'card' && ! app(\App\Services\PaymentGateway::class)->ready('helcim')) {
            throw ValidationException::withMessages(['payment_method' => 'Card payment is currently unavailable. Please select another payment method.']);
        }
        $quantities = session('storefront_cart', []);
        $quote = session('checkout_quote', []);
        if (! $quantities || count($quantities) > 100) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty or invalid. Please review it.']);
        }
        $order = DB::transaction(function () use ($d, $quantities, $quote) {
            $settings = GeneralSetting::lockForUpdate()->findOrFail(1);
            if ($existing = Order::where('checkout_token', $d['checkout_token'])->first()) {
                return $existing;
            }

            if (session('checkout_charges') !== ['delivery' => $settings->delivery_cents, 'rate' => $settings->tax_basis_points, 'matrix' => (bool) $settings->matrix_delivery_enabled, 'pickup_address' => $settings->pickup_address]) {
                throw ValidationException::withMessages(['cart' => 'Delivery, pickup details or tax have changed. Open checkout from your cart again to review the new total.']);
            }
            if ($d['fulfillment'] === 'pickup') {
                if (!trim($settings->pickup_address ?? '')) throw ValidationException::withMessages(['fulfillment'=>'Pickup is not available until the store address is configured. Please select delivery.']);
                $d['pickup_address'] = $settings->pickup_address;
            }
            $deliverySnapshot=[];
            if($d['fulfillment'] === 'delivery' && $settings->matrix_delivery_enabled){
                if(!in_array(strtolower(trim($d['country'])),['canada','ca']))throw ValidationException::withMessages(['country'=>'Delivery is available only within supported Canadian postal areas.']);
                $deliverySnapshot=app(\App\Services\DeliveryQuote::class)->quote($settings->warehouse_postal_code??'',$d['postal_code'],$d['delivery_service']??'');
                $expected=$deliverySnapshot+['postal_code'=>\App\Services\DeliveryQuote::postal($d['postal_code'])];
                if(session('delivery_quote')!==$expected)throw ValidationException::withMessages(['delivery_service'=>'Delivery quote changed or expired. Select your service again to review the charge.']);
            }
            $d['delivery_service'] = $deliverySnapshot['delivery_service'] ?? null;
            if($deliverySnapshot)$d['postal_code']=$deliverySnapshot['delivery_to_postal'];
            $products = Product::whereIn('id', array_keys($quantities))->orderBy('id')->lockForUpdate()->get();
            if ($products->count() !== count($quantities)) {
                throw ValidationException::withMessages(['cart' => 'A product is no longer available. Please review your cart.']);
            }
            $lines = [];
            $subtotal = 0;
            foreach ($products as $p) {
                $qty = (int) $quantities[$p->id];
                $price = (int) round((float) $p->storefront_price * 100);
                if (! $p->is_active || ! $p->categories()->where('is_active', true)->exists() || $p->storefront_price === null || $price < 0 || $qty < 1 || $qty > 99) {
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
            $order = Order::create(array_merge($d, ['customer_id' => auth()->user()->customerRecord()->id, 'created_by' => auth()->id(), 'source' => 'website', 'number' => 'MMC-'.strtoupper((string) Str::ulid()), 'subtotal_cents' => $subtotal, 'delivery_cents' => ($d['fulfillment'] === 'pickup' ? 0 : ($deliverySnapshot['delivery_cents']??$settings->delivery_cents)), 'tax_cents' => $settings->taxFor($subtotal), 'tax_basis_points'=>$settings->tax_basis_points, 'payment_method' => $d['payment_method'], 'payment_status' => $d['payment_method'] === 'card' ? 'pending' : 'unpaid', 'status' => $d['payment_method'] === 'cash' ? 'warehouse_pending' : ($d['payment_method'] === 'card' ? 'awaiting_payment' : 'transfer_pending'), 'warehouse_round' => $d['payment_method'] === 'cash' ? 1 : 0, 'warehouse_sent_at' => $d['payment_method'] === 'cash' ? now() : null]));
            if($deliverySnapshot) $order->update($deliverySnapshot);
            if ($d['fulfillment'] === 'delivery') auth()->user()->customerRecord()->update(\Illuminate\Support\Arr::only($d,['address','city','province','postal_code','country']));
            // The database-generated ID avoids collisions between simultaneous orders.
            $order->update(['number' => 'mmc-'.$order->id]);
            foreach ($lines as [$p,$qty,$price,$inventory]) {
                $order->items()->create(['product_id' => $p->id, 'name' => $p->premium_marketing_name, 'product_code' => $p->product_code, 'qr_code' => $p->qr_code, 'stock_deducted' => $inventory && $inventory->quantity_on_hand !== null, 'quantity' => $qty, 'unit_cents' => $price, 'line_cents' => $price * $qty]);
                if ($inventory && $inventory->quantity_on_hand !== null) {
                    $before = $inventory->quantity_on_hand;
                    $after = ((int) round((float) $before * 1000) - $qty * 1000) / 1000;
                    $inventory->update(['quantity_on_hand' => $after]);
                    InventoryMovement::create(['product_id' => $p->id, 'quantity_change' => -$qty, 'quantity_before' => $before, 'quantity_after' => $after, 'reason' => 'Order '.$order->number, 'created_by' => null, 'created_at' => now()]);
                }
            }

            if (in_array($order->payment_method, ['card', 'paypal'], true)) {
                app(OnlinePayments::class)->initialize($order);
            }

            return $order;
        }, 3);
        session(['last_order_id' => $order->id, 'last_order_token' => $order->checkout_token]);
        session()->forget(['storefront_cart', 'checkout_token', 'checkout_quote', 'checkout_charges', 'delivery_quote']);

        if ($order->payment) {
            return app(PaymentController::class)->start($order->payment, app(OnlinePayments::class));
        }

        return redirect()->route('theme.order-success');
    }

    public function printOrder()
    {
        $order = Order::with('items')->find(session('last_order_id'));
        abort_unless($order, 404);

        return response()->view('orders.print', compact('order'))->header('Cache-Control', 'no-store, private');
    }

    public function success()
    {
        $order = Order::with('items')->find(session('last_order_id'));
        if (! $order) {
            return redirect()->route('theme.cart');
        }

        if ($order->payment && ($order->payment_status !== 'paid' || $order->status === 'payment_review')) {
            return redirect()->route('payment.show', $order->payment->reference);
        }

        return response()->view('pages.order-success', compact('order'))->header('Cache-Control', 'no-store, private');
    }
}
