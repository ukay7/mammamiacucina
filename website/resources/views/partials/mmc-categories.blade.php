@php
    $categories = $categories ?? config('homepage.categories', []);
@endphp
@if (count($categories))
<section class="mmc-categories" aria-label="Explore our categories">
    <div class="mmc-categories__grid" style="--category-columns: {{ min(4, count($categories)) }}">
        @foreach ($categories as $category)
            <a aria-label="{{ $category['name'] }}" class="mmc-category" href="{{ route('theme.product-grid', ['category' => $category['slug']]) }}">
                <div class="mmc-category__picture {{ isset($category['sprite_x']) ? 'mmc-category__picture--reference' : '' }}" @isset($category['sprite_x']) style="--sprite-x: {{ $category['sprite_x'] }}" @endisset>
                    <img src="{{ asset($category['image']) }}" alt="{{ $category['name'] }}" loading="eager">
                </div>
                <h2 class="mmc-category__name">{{ $category['name'] }}</h2>
                <span class="mmc-category__ornament" aria-hidden="true">♥</span>
            </a>
        @endforeach
    </div>
</section>
@endif


