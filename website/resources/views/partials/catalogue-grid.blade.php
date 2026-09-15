<div class="mmc-shop-layout">
<aside class="mmc-shop-sidebar mmc-refined-filters" aria-label="Product filters">
<div class="mmc-filter-heading"><h2>Filters</h2><a href="{{ route('theme.product-grid') }}">Clear all</a></div>
<section><h3>Categories <span>{{ $categories->count() }}</span></h3>
<label class="sr-only" for="category-search">Search categories</label><input id="category-search" class="mmc-category-search" type="search" placeholder="Search categories…" autocomplete="off">
<nav class="mmc-shop-categories mmc-filter-scroll" aria-label="Product categories">
<a href="{{ route('theme.product-grid',request()->except(['category','page','allergy'])) }}" @if(!$category) aria-current="page" @endif><span class="mmc-category-check" aria-hidden="true">{{ !$category?'✓':'' }}</span><span>All Products</span><small>{{ $allCount }}</small></a>
@foreach($categories as $item)<a data-category-name="{{ $item->name }}" href="{{ route('theme.product-grid',array_merge(request()->except(['category','page','allergy']),['category'=>$item->slug])) }}" @if($category===$item->slug) aria-current="page" @endif><span class="mmc-category-check" aria-hidden="true">{{ $category===$item->slug?'✓':'' }}</span><span>{{ $item->name }}</span><small>{{ $item->products_count }}</small></a>@endforeach
</nav><p data-category-empty hidden>No matching categories.</p></section>
<section><h3>Allergies & Dietary Labels</h3><p class="mmc-filter-hint">Show products with any selected label.</p>
<form class="mmc-allergy-filter" method="get" action="{{ route('theme.product-grid') }}">
@foreach(['category'=>$category,'sort'=>$sort,'show'=>$perPage,'view'=>$view,'min'=>$min,'max'=>$max] as $key=>$value)@if($value!==null)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
<div class="mmc-filter-scroll mmc-allergy-options">
@foreach($allergies as $label)<label class="mmc-allergy-option" for="allergy-{{ $label->id }}"><input type="checkbox" id="allergy-{{ $label->id }}" name="allergies[]" value="{{ $label->id }}" @checked(in_array($label->id,$selectedAllergies))><img src="{{ $label->iconUrl() }}" alt="" width="26" height="26" loading="lazy"><span>{{ $label->name }}</span></label>@endforeach
</div><noscript><button class="mmc-button" type="submit">Apply Labels</button></noscript></form></section>
<section><h3>Price range <span>CAD</span></h3><form method="get" action="{{ route('theme.product-grid') }}" class="mmc-price-filter">
@foreach($selectedAllergies as $id)<input type="hidden" name="allergies[]" value="{{ $id }}">@endforeach
@foreach(['category'=>$category,'sort'=>$sort,'show'=>$perPage,'view'=>$view] as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
<div class="mmc-price-fields"><label>Min ($)<input type="number" name="min" min="0" step="0.01" placeholder="0" value="{{ $min }}"></label><span aria-hidden="true">–</span><label>Max ($)<input type="number" name="max" min="0" step="0.01" placeholder="Any" value="{{ $max }}"></label></div>
<button class="mmc-button" type="submit">Apply price</button>
<p class="mmc-filter-hint">Products without a price are excluded when a price filter is set.</p>
</form></section>
</aside>
<div class="mmc-shop-main">
<form class="mmc-shop-toolbar" method="get" action="{{ route('theme.product-grid') }}" aria-label="Product display options">
@foreach($selectedAllergies as $id)<input type="hidden" name="allergies[]" value="{{ $id }}">@endforeach
<input type="hidden" name="category" value="{{ $category }}">
@if($min!==null)<input type="hidden" name="min" value="{{ $min }}">@endif
@if($max!==null)<input type="hidden" name="max" value="{{ $max }}">@endif
<label for="shop-sort">Sort by<select id="shop-sort" name="sort"><option value="featured" @selected($sort==='featured')>Latest products</option><option value="name" @selected($sort==='name')>Name: A–Z</option><option value="price-low" @selected($sort==='price-low')>Price: low to high</option><option value="price-high" @selected($sort==='price-high')>Price: high to low</option></select></label>
<label for="shop-show">Show<select id="shop-show" name="show">@foreach([6,12,24] as $count)<option value="{{ $count }}" @selected($perPage===$count)>{{ $count }} products</option>@endforeach</select></label>
<button class="mmc-toolbar-apply" name="view" value="{{ $view }}">Apply</button>
<div class="mmc-shop-switch" aria-label="View mode"><button type="submit" name="view" value="grid" aria-label="Grid view" aria-pressed="{{ $view==='grid' ? 'true' : 'false' }}"><i class="fa fa-th" aria-hidden="true"></i></button><button type="submit" name="view" value="list" aria-label="List view" aria-pressed="{{ $view==='list' ? 'true' : 'false' }}"><i class="fa fa-list" aria-hidden="true"></i></button></div>
</form>
<p class="mmc-results" role="status" tabindex="-1">{{ $total ? (($page-1)*$perPage+1).'–'.min($page*$perPage,$total).' of '.$total : 'No' }} products</p>
<div class="mmc-product-grid mmc-shop-products {{ $view==='list' ? 'is-list' : '' }}">
@forelse($products as $product)@include('partials.catalogue-card')@empty<div class="mmc-shop-empty"><h2>No products found</h2><p>Try another category or a wider price range.</p><a class="mmc-button" href="{{ route('theme.product-grid') }}">View All Products</a></div>@endforelse
</div>
{{ $products->onEachSide(1)->links('partials.catalogue-pagination') }}
</div>
</div>
