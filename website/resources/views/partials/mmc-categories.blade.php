@if ($homeCategories->isNotEmpty())
<section class="mmc-categories" aria-label="Explore our categories">
    <div class="mmc-categories__controls" hidden>
        <button type="button" data-category-prev aria-label="Previous categories" aria-controls="home-category-track">&#8592;</button>
        <button type="button" data-category-next aria-label="Next categories" aria-controls="home-category-track">&#8594;</button>
    </div>
    <div class="mmc-categories__grid" id="home-category-track" tabindex="0" aria-label="Categories, scroll horizontally">
        @foreach ($homeCategories as $category)
            <a aria-label="{{ $category->name }}" class="mmc-category" href="{{ route('theme.product-grid', ['category' => $category->slug]) }}">
                <div class="mmc-category__picture">
                    @if($category->image_path)
                        <img src="{{ route('category.image', $category) }}" alt="{{ $category->name }}" loading="lazy">
                    @else
                        <span class="mmc-category__placeholder">Image coming soon</span>
                    @endif
                </div>
                <h2 class="mmc-category__name">{{ $category->name }}</h2>
                <span class="mmc-category__ornament" aria-hidden="true">♥</span>
            </a>
        @endforeach
    </div>
</section>
@endif
