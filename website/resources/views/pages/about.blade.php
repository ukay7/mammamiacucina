@extends('layouts.mmc-page', ['pageTitle' => 'About Us'])
@section('page-content')
<section class="mmc-two-col ps-section--about">
<div>@if($about->eyebrow)<p class="mmc-eyebrow">{{ $about->eyebrow }}</p>@endif<h2 style="white-space:pre-line">{{ $about->heading }}</h2><div class="mmc-gold-rule">♥</div>
@foreach(preg_split('/\R\s*\R/u',$about->description) as $paragraph)<p style="white-space:pre-line;overflow-wrap:anywhere">{{ $paragraph }}</p>@endforeach
<a class="mmc-button" href="{{ route('theme.'.$about->button_page) }}">{{ $about->button_name }}</a></div>
<img class="mmc-feature-photo" src="{{ $about->imageUrl() }}" alt="{{ $about->image_alt }}">
</section>
@if($about->closing_sentence)
<section class="mmc-about-quote" aria-label="Our Italian promise">
<blockquote>{{ $about->closing_sentence }}</blockquote>
<div class="mmc-about-quote__divider" aria-hidden="true"><span></span><svg viewBox="0 0 80 60" width="70" height="54" focusable="false"><path d="M30 49 Q7 43 6 9 Q30 17 30 49Z" fill="#236739"/><path d="M40 54 Q24 31 40 5 Q56 31 40 54Z" fill="none" stroke="#b48b43" stroke-width="2"/><path d="M50 49 Q51 20 74 9 Q73 40 50 49Z" fill="#af1020"/><path d="M13 19 L28 45 M67 19 L52 45" fill="none" stroke="#fff8ec" stroke-width="2"/></svg><span></span></div>
@if($about->quote_supporting_text)<p>{{ $about->quote_supporting_text }}</p>@endif
</section>
@endif
@if(count($about->items))
<section class="mmc-about-values" aria-label="What makes us special" data-about-carousel>
<div class="mmc-about-values__track" id="about-values" tabindex="0" aria-label="About us highlights. Scroll to browse.">
@foreach($about->items as $item)<article><span>{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><h3>{{ $item['title'] }}</h3><p>{{ $item['description'] }}</p></article>@endforeach
</div>
<div class="mmc-about-values__controls" hidden><button type="button" data-direction="-1" aria-label="Previous highlights" aria-controls="about-values">←</button><button type="button" data-direction="1" aria-label="Next highlights" aria-controls="about-values">→</button></div>
</section>
@endif
<section class="mmc-callout"><p class="mmc-eyebrow">SOMETHING SWEET AWAITS</p><h2>Bring everyone together.</h2><a class="mmc-button" href="{{ route('theme.contact') }}">Talk to Us</a></section>
@endsection
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mmc-about.css') }}?v={{ filemtime(public_path('assets/css/mmc-about.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('assets/js/mmc-about.js') }}?v={{ filemtime(public_path('assets/js/mmc-about.js')) }}"></script>
@endpush
