@if (count($arrivalProducts))
<section class="mmc-arrivals" aria-labelledby="mmc-arrivals-title">
    <header class="mmc-arrivals__heading">
        <p>New Arrivals</p>
        <h2 id="mmc-arrivals-title">More Italian Favourites</h2>
        <span class="mmc-arrivals__ornament" aria-hidden="true">♥</span>
    </header>
    <div id="mmc-arrivals-track" aria-label="Featured products" tabindex="0" class="mmc-arrivals__grid" style="--arrival-columns: {{ min(5, count($arrivalProducts)) }}">
        @foreach ($arrivalProducts as $product)
            @php
                $productUrl = route('catalogue.product', $product->slug);
                $price = $product->total_selling_price_cad === null ? 'Price on request' : '$'.number_format($product->total_selling_price_cad, 2);
            @endphp
            <article class="mmc-arrival" data-name="{{ $product->premium_marketing_name }}" data-price="{{ $price }}" data-url="{{ $productUrl }}">
                <div class="mmc-arrival__media">
                    <a class="mmc-arrival__image-link" href="{{ $productUrl }}" aria-label="View {{ $product->premium_marketing_name }}">
                        <img src="{{ route('catalogue.media', [$product, $product->media->first()]) }}" alt="{{ $product->premium_marketing_name }}" width="1536" height="1024" loading="lazy">
                    </a>
                    @if($product->total_selling_price_cad !== null)
                    <div class="mmc-arrival__actions">
                        <form data-live-cart-form method="post" action="{{ route('cart.add', $product) }}">@csrf<input type="hidden" name="quantity" value="1"><button type="submit" aria-label="Add {{ $product->premium_marketing_name }} to cart" title="Add to Cart">@include('partials.cart-icon')</button></form>
                    </div>
                    @endif
                </div>
                <h3><a href="{{ $productUrl }}">{{ $product->premium_marketing_name }}</a></h3>
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

</section>
@endif

