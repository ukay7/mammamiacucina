@extends('layouts.mmc-page', ['pageTitle' => 'Gallery'])
@section('page-content')
<div class="mmc-section-heading"><p class="mmc-eyebrow">A FEAST FOR THE EYES</p><h2>Moments of Italian Sweetness</h2><p>Take a closer look at our cakes, pastries and cannoli.</p></div>
<div class="mmc-gallery-grid">@foreach(config('catalogue.products') as $product)<a class="mmc-gallery-card" href="{{ route('theme.product-detail', ['product'=>$product['slug']]) }}"><img src="{{ asset($product['image']) }}" alt="{{ $product['name'] }}" loading="lazy"><h3>{{ $product['name'] }}</h3><span>Explore product →</span></a>@endforeach</div>
@endsection
