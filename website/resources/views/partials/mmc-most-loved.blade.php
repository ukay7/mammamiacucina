@php
    $lovedProducts = collect($products ?? config('most-loved.products', []))
        ->filter(fn ($product) => !empty($product['most_loved']))->values();
@endphp
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
                            <a class="mmc-loved__product" href="{{ route('theme.product-detail', ['product' => $product['slug']]) }}">
                                <span class="mmc-loved__picture"><img src="{{ asset($product['image']) }}" alt="" width="1536" height="1024" loading="eager"></span>
                                <span class="mmc-loved__details">
                                    <span class="mmc-loved__name">{{ $product['name'] }}</span>
                                    <span class="mmc-loved__price">${{ number_format($product['price_cents'] / 100, 2) }}</span>
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
