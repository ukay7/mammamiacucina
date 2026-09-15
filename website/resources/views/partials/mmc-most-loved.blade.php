@if ($lovedProducts->isNotEmpty())
<section class="mmc-loved" aria-labelledby="mmc-loved-title">
    <div class="mmc-loved__panel">
        <header class="mmc-loved__heading">
            <p>Signature Selection</p>
            <h2 id="mmc-loved-title">Our Most-Loved Treats</h2>
            <span class="mmc-loved__ornament" aria-hidden="true">♥</span>
        </header>
        <div class="mmc-loved__columns">
            @foreach ($lovedProducts->chunk((int) ceil($lovedProducts->count() / 2)) as $column)
                <ul class="mmc-loved__list">
                    @foreach ($column as $product)
                        <li>
                            <a class="mmc-loved__product" href="{{ route('catalogue.product', $product->slug) }}">
                                <span class="mmc-loved__picture">@if($product->media->first())<img src="{{ route('catalogue.media',[$product,$product->media->first()]) }}" alt="" width="1536" height="1024" loading="lazy">@else<span class="mmc-loved__placeholder">Image coming soon</span>@endif</span>
                                <span class="mmc-loved__details">
                                    <span class="mmc-loved__name">{{ $product->premium_marketing_name }}</span>
                                    <span class="mmc-loved__price">{{ $product->total_selling_price_cad === null ? 'Price on request' : '$'.number_format($product->total_selling_price_cad, 2) }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    </div>
</section>
@endif
