@extends('layouts.mmc-page', ['pageTitle' => 'Product Details'])
@section('page-content')
@php
$product=collect(config('catalogue.products'))->firstWhere('slug',request('product')) ?? config('catalogue.products')[0];
@endphp
<section class="mmc-two-col ps-product--detail">@include('partials.mmc-product-media')<div><p class="mmc-eyebrow">MAMMA MIA FAVOURITES</p><h2>{{ $product['name'] }}</h2><p class="mmc-price">{{ $product['price_cents'] === null ? 'PRICE ON REQUEST' : '$'.number_format($product['price_cents']/100,2) }}</p><p>A delicious addition to your table. Explore this Italian favourite for your next gathering, celebration, or sweet moment.</p><p>Contact us for serving sizes, ingredients, allergens and availability.</p>
@if($product['price_cents'] !== null)<form data-add-product="{{ $product['slug'] }}" class="mmc-purchase"><label for="quantity">Quantity</label><input id="quantity" type="number" value="1" min="1" max="99" required><button class="mmc-button" type="submit">Add to Cart</button><p role="status" data-add-status></p></form>@else<a class="mmc-button" href="{{ route('theme.contact') }}">Enquire About This Product</a>@endif
<div class="mmc-detail-meta"><p>Category: {{ ucfirst($product['category']) }}</p><a href="{{ route('theme.product-grid', ['category'=>$product['category']]) }}">Browse this collection →</a></div></div></section>
<section class="mmc-related"><h2>More to Love</h2><div class="mmc-product-grid">@foreach(collect(config('catalogue.products'))->where('slug','!=',$product['slug'])->take(3) as $product)@include('partials.mmc-product-card')@endforeach</div></section>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/mmc-product-media.js') }}?v={{ filemtime(public_path('assets/js/mmc-product-media.js')) }}"></script>
@endpush