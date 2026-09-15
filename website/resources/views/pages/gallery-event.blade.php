@extends('layouts.mmc-page', ['pageTitle' => $event->title])
@section('page-content')
<a class="mmc-text-link" href="{{ route('theme.gallery') }}">← All Events</a><div class="mmc-section-heading"><h2>{{ $event->title }}</h2><p>{{ $event->event_date?->format('d M Y') }}</p><p style="white-space:pre-line">{{ $event->description }}</p></div>
<div class="mmc-gallery-grid">@foreach($photos as $photo)<a class="mmc-gallery-card" href="{{ route('gallery.image',$photo) }}" target="_blank" rel="noopener" aria-label="Open {{ $event->title }} photo {{ $loop->iteration }}"><img src="{{ route('gallery.image',$photo) }}" alt="{{ $event->title }} — photo {{ $loop->iteration }}" loading="lazy"><span>View full picture ↗</span></a>@endforeach</div>{{ $photos->links('pagination::bootstrap-4') }}
@endsection
