<h2>Your Cart</h2>
<div class="mmc-mini-cart-items">
@forelse($cart['items'] as $item)
@php($p=$item['product'])
<div class="mmc-mini-cart-item">
    @if($image=$p->media->first())
    <a href="{{ route('catalogue.product',$p->slug) }}"><img src="{{ route('catalogue.media',[$p,$image]) }}" alt="{{ $p->premium_marketing_name }}" width="72" height="64"></a>
    @else
    <a href="{{ route('catalogue.product',$p->slug) }}" class="mmc-cart-placeholder mmc-cart-placeholder--mini" aria-label="{{ $p->premium_marketing_name }} — image coming soon">Image coming soon</a>
    @endif
    <div class="mmc-mini-cart-info">
        <a href="{{ route('catalogue.product',$p->slug) }}"><strong>{{ $p->premium_marketing_name }}</strong></a>
        <span>${{ number_format($item['line_cents']/100,2) }}</span>
        @include('partials.cart-item-controls')
    </div>
</div>
@empty
<p>Your cart is currently empty.</p>
@endforelse
</div>
@if($cart['count'])<p>{{ $cart['count'] }} items · ${{ number_format($cart['total']/100,2) }}</p>@endif
<a class="mmc-header-cart__link" href="{{ route('theme.cart') }}">View Cart</a>
<a class="mmc-header-cart__browse" href="{{ route('theme.product-grid') }}">Explore our products</a>
