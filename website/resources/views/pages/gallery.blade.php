@extends('layouts.mmc-page', ['pageTitle' => 'Gallery'])
@section('page-content')
<div class="mmc-section-heading"><p class="mmc-eyebrow">OUR GALLERY</p><h2>Moments of Italian Sweetness</h2><p>Explore our events and celebrations.</p></div>
<div class="mmc-gallery-grid">@forelse($events as $event)<a class="mmc-gallery-card" href="{{ route('gallery.event',$event) }}"><img src="{{ route('gallery.image',$event->photos->first()) }}" alt="{{ $event->title }}" loading="lazy"><h3>{{ $event->title }}</h3><p>{{ $event->event_date?->format('d M Y') }}</p><span>{{ $event->photos_count }} photos · View event →</span></a>@empty<p>Our event photos are coming soon.</p>@endforelse</div>{{ $events->links('pagination::bootstrap-4') }}
@endsection
