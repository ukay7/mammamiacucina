@php
    $banners = [
        ['image' => 'italian-pastries-hero.png', 'title' => ['Authentic Italian', 'Cakes & Pastries'], 'text' => 'Homemade with love · Product of Italy', 'button' => 'Explore the Menu', 'route' => 'theme.product-grid', 'alt' => 'Sicilian cassata, cannoli and chocolate-drizzled pastries'],
        ['image' => 'cakes-hero.png', 'title' => ['A Taste of Italy,', 'A Slice of Joy'], 'text' => 'Italian favourites · Made to celebrate', 'button' => 'Discover Our Cakes', 'route' => 'theme.product-grid', 'category' => 'cakes', 'alt' => 'Chocolate caprese, ricotta cake and a fresh berry tart'],
        ['image' => 'pastries-hero.png', 'title' => ['Golden Layers,', 'Sweet Moments'], 'text' => 'Flaky pastries · A little Italian indulgence', 'button' => 'Explore Our Pastries', 'route' => 'theme.product-grid', 'category' => 'pastries', 'alt' => 'Golden sfogliatelle and cornetti with orange and espresso'],
        ['image' => 'cannoli-hero.png', 'title' => ['A Sicilian Classic,', 'Filled with Love'], 'text' => 'Crisp shells · Deliciously creamy centres', 'button' => 'Discover Our Cannoli', 'route' => 'theme.product-grid', 'category' => 'cannoli', 'alt' => 'Pistachio and chocolate chip ricotta-filled Sicilian cannoli'],
    ];
@endphp
<section class="mmc-hero" aria-label="Mamma Mia Cucina highlights" aria-roledescription="carousel">
    <div class="mmc-slides" aria-live="off">
        @foreach ($banners as $banner)
            <div class="mmc-slide" role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ count($banners) }}" aria-hidden="{{ $loop->first ? 'false' : 'true' }}" @if (!$loop->first) hidden inert @endif>
                <img class="mmc-hero__image" src="{{ asset('assets/images/mmc/'.$banner['image']) }}" alt="{{ $banner['alt'] }}" fetchpriority="{{ $loop->first ? 'high' : 'low' }}" width="2129" height="739">
                <div class="mmc-hero__content">
                    @if ($loop->first)
                        <h1 id="mmc-hero-title">{{ $banner['title'][0] }}<br>{{ $banner['title'][1] }}</h1>
                    @else
                        <h2>{{ $banner['title'][0] }}<br>{{ $banner['title'][1] }}</h2>
                    @endif
                    <div class="mmc-heart-rule" aria-hidden="true"><span>♥</span></div>
                    <p>{{ $banner['text'] }}</p>
                    <a class="mmc-menu-cta" href="{{ route($banner['route'], isset($banner['category']) ? ['category' => $banner['category']] : []) }}">{{ $banner['button'] }}</a>
                    <div class="mmc-flourish" aria-hidden="true"><span>❧</span><b>♧</b><span>❧</span></div>
                </div>
            </div>
        @endforeach
    </div>
    @include('partials.mmc-header')
    <button class="mmc-slider-arrow mmc-slider-prev" type="button" aria-label="Previous banner"><span aria-hidden="true">‹</span></button>
    <button class="mmc-slider-arrow mmc-slider-next" type="button" aria-label="Next banner"><span aria-hidden="true">›</span></button>
    <div class="mmc-slider-controls">
        @foreach ($banners as $banner)
            <button class="mmc-slider-dot" type="button" aria-label="Show banner {{ $loop->iteration }}: {{ implode(' ', $banner['title']) }}" aria-current="{{ $loop->first ? 'true' : 'false' }}" data-slide="{{ $loop->index }}"></button>
        @endforeach
        <button class="mmc-slider-pause" type="button" aria-label="Pause banner rotation"><span aria-hidden="true">Ⅱ</span></button>
    </div>
</section>
