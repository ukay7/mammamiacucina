@extends('layouts.mmc-page', ['pageTitle' => 'Our Products'])
@section('page-content')
@php
$catalogue=collect(config('catalogue.products'));
$category=in_array(request('category'),['cakes','pastries','cannoli'],true) ? request('category') : '';
$sort=in_array(request('sort'),['name','price-low','price-high'],true) ? request('sort') : 'featured';
$perPage=in_array((int)request('show'),[6,12,24],true) ? (int)request('show') : 12;
$view=request('view')==='list' ? 'list' : 'grid';
$min=is_numeric(request('min')) ? max(0,(float)request('min')) : null;
$max=is_numeric(request('max')) ? max(0,(float)request('max')) : null;
$products=$catalogue->filter(fn($p)=>!$category || $p['category']===$category);
if($min!==null || $max!==null) $products=$products->filter(fn($p)=>$p['price_cents']!==null && ($min===null || $p['price_cents'] >= $min*100) && ($max===null || $p['price_cents'] <= $max*100));
if($sort==='name') $products=$products->sortBy('name');
if($sort==='price-low') $products=$products->sortBy(fn($p)=>$p['price_cents'] ?? PHP_INT_MAX);
if($sort==='price-high') $products=$products->sortByDesc(fn($p)=>$p['price_cents'] ?? -1);
$total=$products->count();
$page=max(1,min(max(1,(int)ceil($total/$perPage)),(int)request('page',1)));
$products=$products->values()->slice(($page-1)*$perPage,$perPage);
@endphp
<div class="mmc-shop-layout">
<aside class="mmc-shop-sidebar" aria-label="Product filters">
<section><h2>Category</h2><nav class="mmc-shop-categories" aria-label="Product categories">
@foreach([''=>'All Products','cakes'=>'Cake','pastries'=>'Pastry','cannoli'=>'Cannoli'] as $key=>$label)
<a href="{{ route('theme.product-grid', array_merge(request()->except(['category','page']),$key ? ['category'=>$key] : [])) }}" @if($category===$key) aria-current="page" @endif><span class="mmc-category-check" aria-hidden="true">{{ $category===$key ? '✓' : '' }}</span>{{ $label }} <small>({{ $key ? $catalogue->where('category',$key)->count() : $catalogue->count() }})</small></a>
@endforeach
</nav></section>
<section><h2>Filter Prices</h2><form method="get" action="{{ route('theme.product-grid') }}" class="mmc-price-filter">
@foreach(['category'=>$category,'sort'=>$sort,'show'=>$perPage,'view'=>$view] as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
<div class="mmc-price-fields"><label>Min ($)<input type="number" name="min" min="0" step="0.50" placeholder="0" value="{{ $min }}"></label><span aria-hidden="true">–</span><label>Max ($)<input type="number" name="max" min="0" step="0.50" placeholder="Any" value="{{ $max }}"></label></div>
<button class="mmc-button" type="submit">Filter</button><a href="{{ route('theme.product-grid',['category'=>$category]) }}">Reset</a>
<p class="mmc-note">Price-on-request products are excluded when a price filter is applied.</p>
</form></section>
<section><h2>Italian Favourites</h2><a class="mmc-sidebar-banner" href="{{ route('theme.product-grid',['category'=>'cannoli']) }}"><img src="{{ asset('assets/images/mmc/category-cannoli-hd.png') }}" alt="Cannoli and cornetti"><span>A little taste of Sicily</span><strong>Explore Cannoli →</strong></a></section>
</aside>
<div class="mmc-shop-main">
<div class="mmc-shop-banners ps-shop-features">
<a class="mmc-shop-promo" href="{{ route('theme.product-grid',['category'=>'cakes']) }}"><img src="{{ asset('assets/images/mmc/category-cakes-hd.png') }}" alt="Traditional cassata cake"><div><span>MADE FOR SHARING</span><h2>Italian Cakes</h2><b>Discover the collection →</b></div></a>
<a class="mmc-shop-promo mmc-shop-promo--wide" href="{{ route('theme.product-grid',['category'=>'pastries']) }}"><img src="{{ asset('assets/images/mmc/pastries-hero.png') }}" alt="Golden Italian pastries"><div><span>GOLDEN LAYERS, SWEET MOMENTS</span><h2>Discover Our Pastries</h2><b>Explore pastries →</b></div></a>
</div>
<form class="mmc-shop-toolbar" method="get" action="{{ route('theme.product-grid') }}" aria-label="Product display options">
<input type="hidden" name="category" value="{{ $category }}">
@if($min!==null)<input type="hidden" name="min" value="{{ $min }}">@endif
@if($max!==null)<input type="hidden" name="max" value="{{ $max }}">@endif
<label for="shop-sort">Sort by<select id="shop-sort" name="sort"><option value="featured" @selected($sort==='featured')>Featured products</option><option value="name" @selected($sort==='name')>Name: A–Z</option><option value="price-low" @selected($sort==='price-low')>Price: low to high</option><option value="price-high" @selected($sort==='price-high')>Price: high to low</option></select></label>
<label for="shop-show">Show<select id="shop-show" name="show">@foreach([6,12,24] as $count)<option value="{{ $count }}" @selected($perPage===$count)>{{ $count }} products</option>@endforeach</select></label>
<button class="mmc-toolbar-apply" name="view" value="{{ $view }}">Apply</button>
<div class="mmc-shop-switch" aria-label="View mode"><button type="submit" name="view" value="grid" aria-label="Grid view" aria-pressed="{{ $view==='grid' ? 'true' : 'false' }}"><i class="fa fa-th" aria-hidden="true"></i></button><button type="submit" name="view" value="list" aria-label="List view" aria-pressed="{{ $view==='list' ? 'true' : 'false' }}"><i class="fa fa-list" aria-hidden="true"></i></button></div>
</form>
<p class="mmc-results" role="status">{{ $total ? (($page-1)*$perPage+1).'–'.min($page*$perPage,$total).' of '.$total : 'No' }} products</p>
<div class="mmc-product-grid mmc-shop-products {{ $view==='list' ? 'is-list' : '' }}">
@forelse($products as $product)@include('partials.mmc-product-card')@empty<div class="mmc-shop-empty"><h2>No products found</h2><p>Try another category or a wider price range.</p><a class="mmc-button" href="{{ route('theme.product-grid') }}">View All Products</a></div>@endforelse
</div>
@if($total>$perPage)<nav class="mmc-shop-pagination" aria-label="Product pages">@for($i=1;$i<=ceil($total/$perPage);$i++)<a href="{{ route('theme.product-grid',array_merge(request()->query(),['page'=>$i])) }}" @if($page===$i) aria-current="page" @endif>{{ $i }}</a>@endfor</nav>@endif
</div>
</div>
@endsection
