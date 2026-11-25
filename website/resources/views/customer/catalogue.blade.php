@extends('admin.layout')
@section('title', 'Product Catalogue')
@push('styles')
<link rel="stylesheet" href="{{ asset('admin-assets/customer-catalogue.css') }}?v={{ filemtime(public_path('admin-assets/customer-catalogue.css')) }}">
@endpush
@section('content')
@php
    $prefill = $reorder['quantities'] ?? $basket;
    $restoreSelection = ! $reorder && old('catalogue_version') === $cartVersion;
    $selection = $restoreSelection
        ? collect(old('selected'))->filter(fn ($id) => is_scalar($id))->map(fn ($id) => (int) $id)
        : collect(array_keys($prefill));
@endphp
@if($reorder)
    <section class="panel panel-content mb-3" role="status">
        <strong>Reorder {{ mb_strtoupper($reorder['order']) }}</strong>
        <p class="mb-1">{{ $reorder['matched'] ? 'Available items are selected at current prices. Check the quantities, then review your order.' : 'No products from this order could be selected.' }}</p>
        @if($reorder['adjusted'])<p class="mb-1">Quantities adjusted to current limits for {{ implode(', ', $reorder['adjusted']) }}.</p>@endif
        @if($reorder['skipped'])<p class="mb-0">Could not select {{ implode(', ', $reorder['skipped']) }} because of availability or cart limits.</p>@endif
    </section>
@endif
<form action="{{ route('customer.catalogue.cart') }}" method="post" data-catalogue
      data-discount-bps="{{ $customer->order_discount_bps }}" data-tax-bps="{{ $taxRate }}"
      data-unlisted-subtotal="{{ $unlistedSubtotal }}" data-unlisted-count="{{ $unlistedCount }}"
      data-visible-cart-count="{{ $visibleCartCount }}" novalidate>
    @csrf
    <input type="hidden" name="catalogue_version" value="{{ $cartVersion }}">
    <div class="panel panel-content customer-catalogue-controls">
        <div class="customer-catalogue-filter">
            <label for="catalogue-search">Find a product</label>
            <input id="catalogue-search" class="form-control" type="search" placeholder="Product name or item code" data-catalogue-search>
        </div>
        <div class="customer-catalogue-filter">
            <label for="catalogue-category">Category</label>
            <select id="catalogue-category" class="form-control" data-catalogue-category>
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="customer-catalogue-pricing">
            <strong>{{ auth()->user()->account_type === 'business' ? 'Business pricing' : 'Individual pricing' }}</strong>
            @if($customer->order_discount_bps)
                <span>{{ $customer->order_discount_bps / 100 }}% account discount applied to the product subtotal</span>
            @endif
        </div>
    </div>

    <div class="panel customer-catalogue-controls customer-catalogue-selection">
        <div class="customer-catalogue-selection-info">
            <strong data-selection-count aria-live="polite">{{ $selection->count() }} selected</strong>
            <small data-selection-invalid hidden>Check the selected quantities.</small>
        </div>
        <dl class="customer-catalogue-totals" data-selection-summary hidden>
            <div><dt>Product subtotal</dt><dd data-selection-subtotal></dd></div>
            @if($customer->order_discount_bps)
                <div><dt>Account discount</dt><dd data-selection-discount></dd></div>
            @endif
            <div><dt>Tax</dt><dd data-selection-tax></dd></div>
            <div class="customer-catalogue-total"><dt>Total before delivery</dt><dd data-selection-total aria-live="polite"></dd></div>
        </dl>
        <div class="customer-catalogue-actions">
            @if($unlistedCount)
                <a class="btn btn-outline-primary" href="{{ route('theme.cart') }}">View website cart ({{ $unlistedCount }} unlisted)</a>
            @endif
            <button type="submit" class="btn btn-primary" data-review-selection>Review order</button>
        </div>
    </div>

    <div class="panel customer-catalogue-table-wrap">
        <table class="table customer-catalogue-table">
            <thead>
                <tr><th scope="col">Select</th><th scope="col">Code</th><th scope="col">Product</th><th scope="col">Pack</th><th scope="col">Unit price (CAD)</th><th scope="col">Qty</th><th scope="col" class="customer-catalogue-amount">Amount (CAD)</th></tr>
            </thead>
            @foreach($categories as $category)
                @if(! $groups->has($category->id)) @continue @endif
                <tbody data-category-group>
                    <tr class="customer-catalogue-group"><th scope="rowgroup" colspan="7">{{ $category->name }}</th></tr>
                    @foreach($groups[$category->id] as $product)
                        @php
                            $price = $prices[$product->id];
                            $code = $product->qr_code ?: $product->product_code;
                            $photo = $product->media->first();
                            $imageUrl = $photo ? route('catalogue.media', [$product, $photo]) : null;
                            $pack = $product->pieces_per_pack > 0 ? (float) $product->pieces_per_pack.' pcs' : ($product->uom ?: '—');
                            $stock = $product->inventory?->quantity_on_hand;
                            $max = $price['unit_cents'] === null ? 0 : ($stock === null ? 99 : min(99, (int) $stock));
                            $editable = $max > 0 || isset($basket[$product->id]);
                            $savedQuantity = $prefill[$product->id] ?? 1;
                            $quantity = $restoreSelection ? old('quantity.'.$product->id, $savedQuantity) : $savedQuantity;
                        @endphp
                        <tr data-catalogue-product data-categories="{{ implode(' ', $product->categories->modelKeys()) }}" data-search="{{ $product->premium_marketing_name }} {{ $product->product_code }} {{ $product->qr_code }} {{ $product->italian_subtitle }}" data-unit-cents="{{ $price['unit_cents'] }}">
                            <td><input type="checkbox" class="customer-catalogue-check" name="selected[]" value="{{ $product->id }}" aria-label="Select {{ $product->premium_marketing_name }}" @checked($editable && $selection->contains($product->id)) @disabled(! $editable)></td>
                            <td>{{ $code ?: '—' }}</td>
                            <td>
                                <div class="customer-catalogue-product">
                                    @if($imageUrl)<img src="{{ $imageUrl }}" alt="" loading="lazy" width="44" height="44">@endif
                                    <a href="{{ route('catalogue.product', $product->slug) }}" data-product-details>{{ $product->premium_marketing_name }}</a>
                                </div>
                                <template data-product-template>
                                    <div class="customer-catalogue-details">
                                        @if($imageUrl)<img src="{{ $imageUrl }}" alt="{{ $product->premium_marketing_name }}">@endif
                                        <div>
                                            @if($product->italian_subtitle)<p>{{ $product->italian_subtitle }}</p>@endif
                                            <dl>
                                                @if($code)<dt>Item code</dt><dd>{{ $code }}</dd>@endif
                                                <dt>Pack</dt><dd>{{ $pack }}</dd>
                                                @if($product->pack_weight_kg > 0)<dt>Pack weight</dt><dd>{{ (float) $product->pack_weight_kg }} kg</dd>@endif
                                                @if($product->unit_weight_g > 0)<dt>Unit weight</dt><dd>{{ (float) $product->unit_weight_g }} g</dd>@endif
                                                <dt>Price (CAD)</dt><dd>@include('customer.catalogue-price', compact('price'))</dd>
                                                @if($product->allergies->isNotEmpty())<dt>Allergens &amp; dietary information</dt><dd>{{ $product->allergies->pluck('name')->implode(', ') }}</dd>@endif
                                            </dl>
                                        </div>
                                    </div>
                                </template>
                            </td>
                            <td>{{ $pack }}</td>
                            <td>@include('customer.catalogue-price', compact('price'))</td>
                            <td>
                                @if($editable)
                                    <input class="form-control customer-catalogue-qty" type="number" name="quantity[{{ $product->id }}]" min="1" max="{{ $max }}" value="{{ is_scalar($quantity) ? $quantity : $savedQuantity }}" inputmode="numeric" aria-label="Quantity for {{ $product->premium_marketing_name }}" required>
                                @endif
                                @if($max === 0 && $price['unit_cents'] !== null)
                                    <span class="customer-catalogue-muted">Out of stock</span>
                                @elseif(! $editable)
                                    <span aria-label="Quantity unavailable">—</span>
                                @endif
                            </td>
                            <td class="customer-catalogue-amount" data-line-amount>—</td>
                        </tr>
                    @endforeach
                </tbody>
            @endforeach
            <tbody data-catalogue-empty @if($products->isNotEmpty()) hidden @endif>
                <tr><td colspan="7">No products match your search.</td></tr>
            </tbody>
        </table>
    </div>
</form>

<dialog class="customer-catalogue-dialog customer-product-dialog" aria-labelledby="catalogue-details-title" data-details-dialog>
    <div class="customer-catalogue-dialog-header">
        <h2 id="catalogue-details-title"></h2>
        <form method="dialog"><button type="submit" class="btn btn-outline-primary">Close</button></form>
    </div>
    <div class="customer-catalogue-dialog-body" data-details-content></div>
</dialog>

@if($review)
    <dialog class="customer-catalogue-dialog" aria-labelledby="catalogue-review-title" data-checkout-dialog>
        <div class="customer-catalogue-dialog-header">
            <h2 id="catalogue-review-title">Review &amp; place order</h2>
            <a class="btn btn-outline-primary" href="{{ route('customer.catalogue') }}">Back to catalogue</a>
        </div>
        <div class="customer-catalogue-dialog-body">
            @if($review['reattachReceipt'] ?? false)
                <p class="mmc-error-alert" role="status">Please reattach your receipt or cheque photo before placing the order.</p>
            @endif
            <form id="catalogue-remove-item" method="post" data-catalogue-remove hidden>
                @csrf<input type="hidden" name="cart_version" value="{{ $cartVersion }}">
            </form>
            @include('partials.checkout-form', $review + ['catalogueReview' => true])
        </div>
    </dialog>
    @include('partials.mmc-shop-data')
    <noscript><a href="{{ route('theme.checkout') }}">Continue to checkout</a></noscript>
@endif
<script src="{{ asset('admin-assets/customer-catalogue.js') }}?v={{ filemtime(public_path('admin-assets/customer-catalogue.js')) }}" defer></script>
@endsection
