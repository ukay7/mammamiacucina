@php
    $arrivalProducts = $arrivalProducts ?? config('new-arrivals.products', []);
@endphp
@if (count($arrivalProducts))
<section class="mmc-arrivals" aria-labelledby="mmc-arrivals-title">
    <header class="mmc-arrivals__heading">
        <p>New Arrivals</p>
        <h2 id="mmc-arrivals-title">More Italian Favourites</h2>
        <span class="mmc-arrivals__ornament" aria-hidden="true">♥</span>
    </header>
    <div id="mmc-arrivals-track" aria-label="New arrival products" tabindex="0" class="mmc-arrivals__grid" style="--arrival-columns: {{ min(5, count($arrivalProducts)) }}">
        @foreach ($arrivalProducts as $product)
            @php
                $productUrl = route('theme.product-detail', ['product' => $product['slug']]);
                $price = $product['price_cents'] === null ? 'Price on request' : '$'.number_format($product['price_cents'] / 100, 2);
            @endphp
            <article class="mmc-arrival" data-name="{{ $product['name'] }}" data-price="{{ $price }}" data-url="{{ $productUrl }}">
                <div class="mmc-arrival__media">
                    <a class="mmc-arrival__image-link" href="{{ $productUrl }}" aria-label="View {{ $product['name'] }}">
                        <img src="{{ asset($product['image']) }}" alt="{{ $product['name'] }}" width="1536" height="1024" loading="eager">
                    </a>
                    <div class="mmc-arrival__actions" aria-label="Actions for {{ $product['name'] }}">
                        <button type="button" class="mmc-arrival__quickview" aria-label="Quick view {{ $product['name'] }}" title="Quick view"><i class="ps-icon--search" aria-hidden="true"></i></button>
                        <a href="{{ route('theme.whist-list', ['product' => $product['slug']]) }}" aria-label="Wishlist for {{ $product['name'] }}" title="Wishlist"><i class="ps-icon--heart" aria-hidden="true"></i></a>
                        <a href="{{ route('theme.compare', ['product' => $product['slug']]) }}" aria-label="Compare {{ $product['name'] }}" title="Compare"><i class="ps-icon--reload" aria-hidden="true"></i></a>
                        <a href="{{ route('theme.cart', ['product' => $product['slug']]) }}" aria-label="View cart for {{ $product['name'] }}" title="View cart"><i class="ps-icon--shopping-cart" aria-hidden="true"></i></a>
                    </div>
                </div>
                <h3><a href="{{ $productUrl }}">{{ $product['name'] }}</a></h3>
                <p class="mmc-arrival__price">{{ $price }}</p>
            </article>
        @endforeach
    </div>
    <div class="mmc-arrivals__slider-controls" hidden>
        <button type="button" data-arrival-prev aria-label="Previous products" aria-controls="mmc-arrivals-track">‹</button>
        <span class="mmc-arrivals__position" aria-live="off"></span>
        <button type="button" data-arrival-next aria-label="Next products" aria-controls="mmc-arrivals-track">›</button>
        <button type="button" data-arrival-pause aria-label="Pause product rotation">Ⅱ</button>
    </div>
    <dialog class="mmc-arrivals__dialog" aria-labelledby="mmc-arrival-preview-title">
        <button type="button" class="mmc-arrivals__close" aria-label="Close quick view">×</button>
        <img class="mmc-arrivals__preview-image" alt="" width="1536" height="1024">
        <h2 id="mmc-arrival-preview-title"></h2>
        <p class="mmc-arrivals__preview-price"></p>
        <a class="mmc-arrivals__details" href="{{ route('theme.product-detail') }}">View product</a>
    </dialog>
</section>
@endif

