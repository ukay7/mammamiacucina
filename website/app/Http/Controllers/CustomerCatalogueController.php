<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\GeneralSetting;
use App\Models\Order;
use App\Models\Product;
use App\Services\CheckoutReview;
use App\Services\CustomerTerms;
use App\Services\DeliverySchedule;
use App\Services\StorefrontCart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerCatalogueController extends Controller
{
    public function index(Request $request, CustomerTerms $terms, StorefrontCart $cart, CheckoutReview $checkout)
    {
        $customer = $request->user()->customerRecord();
        $categories = Category::where('is_active', true)->where('show_to_customer', true)->orderBy('sort_order')->orderBy('name')->get()->keyBy('id');
        $products = $this->products()->with([
            'categories' => fn ($query) => $query->whereIn('categories.id', $categories->keys()),
            'media' => fn ($query) => $query->coverImage(),
            'inventory', 'allergies',
        ])->orderBy('premium_marketing_name')->orderBy('id')->get();
        $groups = $products->groupBy(fn (Product $product) => $product->categories->firstWhere('id', $product->category_id)?->id ?? $product->categories->first()->id);
        $prices = $terms->prices($products, $customer);
        $basket = $request->session()->get('storefront_cart', []);
        $snapshot = $cart->snapshot();
        $unlisted = array_diff_key($basket, $products->keyBy('id')->all());
        $unlistedCount = array_sum($unlisted);
        $visibleCartCount = count($basket) - count($unlisted);
        $unlistedSubtotal = collect($snapshot['items'])->sum(fn ($item) => isset($unlisted[$item['product']->id]) ? $item['line_cents'] : 0);
        $cartVersion = $this->cartVersion($basket);
        $reorder = $request->boolean('reorder') ? $request->session()->pull('catalogue_reorder') : null;
        if ($reorder && $reorder['cart_version'] !== $cartVersion) {
            return redirect()->route('customer.catalogue')->withErrors(['cart' => 'Your cart changed. Open the order and try Reorder again.']);
        }
        $taxRate = GeneralSetting::findOrFail(1)->tax_basis_points;
        $review = null;

        if ($request->boolean('review')) {
            $draft = $request->session()->pull('catalogue_review_draft');
            if ($issue = $cart->checkoutIssue($snapshot)) {
                return redirect()->route('theme.cart')->with('cart_status', $issue);
            }
            if ($unlisted) {
                return redirect()->route('theme.cart')->with('cart_status', 'Some items are unavailable in the account catalogue. Review your cart to continue.');
            }
            $review = $checkout->prepare($request->user(), $snapshot, fromCatalogue: true);
            if ($draft) {
                $request->session()->flashInput($draft['fields']);
                $review['reattachReceipt'] = $draft['receipt_selected'];
            }
        }

        return view('customer.catalogue', compact('categories', 'products', 'groups', 'prices', 'customer', 'basket', 'visibleCartCount', 'unlistedCount', 'unlistedSubtotal', 'cartVersion', 'taxRate', 'review', 'reorder'));
    }

    public function reorder(Request $request, Order $order)
    {
        abort_unless((int) $order->customer_id === $request->user()->customerRecord()->id, 404);

        $lines = $order->items()->get(['product_id', 'name', 'quantity'])->groupBy('product_id');
        $products = $this->products()->whereKey($lines->keys()->filter()->all())
            ->whereRaw(Product::storefrontPriceSql().' IS NOT NULL')->with('inventory')->get()->keyBy('id');
        $basket = $request->session()->get('storefront_cart', []);
        $quantities = $basket;
        $skipped = [];
        $adjusted = [];
        $matched = 0;
        foreach ($lines as $id => $items) {
            $product = $products->get($id);
            $stock = $product?->inventory?->quantity_on_hand;
            if (! $product || ($stock !== null && $stock < 1) || (! isset($quantities[$id]) && count($quantities) >= 100)) {
                array_push($skipped, ...$items->pluck('name')->all());
                continue;
            }
            $requested = max((int) $items->sum('quantity'), $quantities[$id] ?? 0, 1);
            $quantity = min($requested, 99, $stock === null ? 99 : (int) $stock);
            if ($quantity < $requested) {
                $adjusted[] = $product->premium_marketing_name;
            }
            $quantities[$id] = $quantity;
            $matched++;
        }

        $request->session()->put('catalogue_reorder', [
            'order' => $order->number,
            'quantities' => $quantities,
            'skipped' => array_unique($skipped),
            'adjusted' => $adjusted,
            'matched' => $matched,
            'cart_version' => $this->cartVersion($basket),
        ]);

        return redirect()->route('customer.catalogue', ['reorder' => 1]);
    }

    public function save(Request $request, StorefrontCart $cart)
    {
        $data = $request->validate([
            'catalogue_version' => 'required|string|size:64',
            'selected' => 'sometimes|array|max:100',
            'selected.*' => 'required|integer|min:1|distinct',
            'quantity' => 'required_with:selected|array',
        ]);
        $basket = $request->session()->get('storefront_cart', []);
        $this->checkCartVersion($data['catalogue_version'], $basket);
        $selected = array_map('intval', $data['selected'] ?? []);
        $quantities = array_intersect_key($data['quantity'] ?? [], array_fill_keys($selected, true));
        $rules = array_fill_keys(array_map(fn ($id) => "quantity.$id", $selected), 'required|integer|min:1|max:99');
        Validator::make(['quantity' => $quantities], $rules, [], ['quantity.*' => 'selected product quantity'])->validate();

        $products = $this->products()->whereKey($selected)->whereRaw(Product::storefrontPriceSql().' IS NOT NULL')->with('inventory')->get();
        if ($products->count() !== count($selected)) {
            throw ValidationException::withMessages(['selected' => 'Some selected products are no longer available to order.']);
        }

        $visibleIds = $this->products()->whereKey(array_keys($basket))->pluck('id')->all();
        $items = array_diff_key($basket, array_fill_keys($visibleIds, true));
        foreach ($products as $product) {
            $items = $cart->withQuantity($items, $product, (int) $quantities[$product->id]);
        }
        $request->session()->put('storefront_cart', $items);
        if ($items != $basket) {
            $request->session()->forget(['checkout_token', 'delivery_quote']);
        }

        if (! $selected) {
            return redirect()->route('customer.catalogue')->with('status', 'Cart updated.');
        }

        return redirect()->route('customer.catalogue', ['review' => 1]);
    }

    public function remove(Request $request, int $product)
    {
        $data = $request->validate(['cart_version' => 'required|string|size:64']);
        $basket = $request->session()->get('storefront_cart', []);
        $this->checkCartVersion($data['cart_version'], $basket);
        unset($basket[$product]);
        $request->session()->put('storefront_cart', $basket);
        $request->session()->forget(['checkout_token', 'delivery_quote']);

        if (! $basket) {
            return redirect()->route('customer.catalogue')->with('status', 'Your cart is empty.');
        }

        $request->session()->put('catalogue_review_draft', [
            'fields' => array_filter($request->only(array_merge([
                'first_name', 'last_name', 'phone', 'fulfillment', 'notes', 'payment_method', 'delivery_address_type', 'address',
                'city', 'province', 'postal_code', 'country', 'delivery_service',
            ], DeliverySchedule::FIELDS)), fn ($value) => is_string($value) && mb_strlen($value) <= 2000),
            'receipt_selected' => $request->boolean('receipt_selected'),
        ]);

        return redirect()->route('customer.catalogue', ['review' => 1]);
    }

    private function cartVersion(array $items): string
    {
        ksort($items);

        return hash('sha256', json_encode($items));
    }

    private function checkCartVersion(string $version, array $items): void
    {
        if (! hash_equals($this->cartVersion($items), $version)) {
            throw ValidationException::withMessages(['cart' => 'Your cart changed in another view. Review the current quantities and try again.']);
        }
    }

    private function products(): Builder
    {
        return Product::where('is_active', true)->whereHas('categories', fn ($query) => $query->where('is_active', true)->where('show_to_customer', true));
    }
}
