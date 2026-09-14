<div class="mmc-shop-layout">
<aside class="mmc-shop-sidebar" aria-label="Product filters">
<section><h2>Category</h2><nav class="mmc-shop-categories" aria-label="Product categories">
<a href="{{ route('theme.product-grid',request()->except(['category','page'])) }}" @if(!$category) aria-current="page" @endif><span class="mmc-category-check" aria-hidden="true">{{ !$category?'✓':'' }}</span>All Products <small>({{ $allCount }})</small></a>
@foreach($categories as $item)<a href="{{ route('theme.product-grid',array_merge(request()->except(['category','page']),['category'=>$item->slug])) }}" @if($category===$item->slug) aria-current="page" @endif><span class="mmc-category-check" aria-hidden="true">{{ $category===$item->slug?'✓':'' }}</span>{{ $item->name }} <small>({{ $item->products_count }})</small></a>@endforeach
</nav></section>
<section><h2>Filter Prices</h2><form method="get" action="{{ route('theme.product-grid') }}" class="mmc-price-filter">
@foreach(['category'=>$category,'sort'=>$sort,'show'=>$perPage,'view'=>$view] as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
<div class="mmc-price-fields"><label>Min ($)<input type="number" name="min" min="0" step="0.01" placeholder="0" value="{{ $min }}"></label><span aria-hidden="true">–</span><label>Max ($)<input type="number" name="max" min="0" step="0.01" placeholder="Any" value="{{ $max }}"></label></div>
<button class="mmc-button" type="submit">Filter</button><a href="{{ route('theme.product-grid',['category'=>$category]) }}">Reset</a>
<p class="mmc-note">Price-on-request products are excluded when a price filter is applied.</p>
</form></section>

</aside>
<div class="mmc-shop-main">
<form class="mmc-shop-toolbar" method="get" action="{{ route('theme.product-grid') }}" aria-label="Product display options">
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
