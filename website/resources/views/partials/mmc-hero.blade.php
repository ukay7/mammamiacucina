<section class="mmc-hero {{ $banners->isEmpty()?'mmc-hero--empty':'' }}" aria-label="Mamma Mia Cucina highlights" aria-roledescription="carousel">
    <div class="mmc-slides" aria-live="off">
        @foreach ($banners as $banner)
            <div class="mmc-slide" role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ count($banners) }}" aria-hidden="{{ $loop->first ? 'false' : 'true' }}" @if (!$loop->first) hidden inert @endif>
                <img class="mmc-hero__image" src="{{ $banner->image_url }}" alt="{{ $banner->alt_text }}" fetchpriority="{{ $loop->first ? 'high' : 'low' }}" width="2129" height="739">
                <div class="mmc-hero__content">
                    @if ($loop->first)
                        <h1 id="mmc-hero-title">{!! nl2br(e($banner->heading)) !!}</h1>
                    @else
                        <h2>{!! nl2br(e($banner->heading)) !!}</h2>
                    @endif
                    <div class="mmc-heart-rule" aria-hidden="true"><span>♥</span></div>
                    <p>{{ $banner->subheading }}</p>
                    <a class="mmc-menu-cta" href="{{ route('theme.product-grid') }}">{{ $banner->button_text }}</a>
                    <div class="mmc-flourish" aria-hidden="true"><span>❧</span><b>♧</b><span>❧</span></div>
                </div>
            </div>
        @endforeach
    </div>
    @include('partials.mmc-header')
    @if($banners->count()>1)
    <button class="mmc-slider-arrow mmc-slider-prev" type="button" aria-label="Previous banner"><span aria-hidden="true">‹</span></button>
    <button class="mmc-slider-arrow mmc-slider-next" type="button" aria-label="Next banner"><span aria-hidden="true">›</span></button>
    <div class="mmc-slider-controls">
        @foreach ($banners as $banner)
            <button class="mmc-slider-dot" type="button" aria-label="Show banner {{ $loop->iteration }}: {{ str_replace("\n",' ',$banner->heading) }}" aria-current="{{ $loop->first ? 'true' : 'false' }}" data-slide="{{ $loop->index }}"></button>
        @endforeach
        <button class="mmc-slider-pause" type="button" aria-label="Pause banner rotation"><span aria-hidden="true">Ⅱ</span></button>
    </div>
    @endif
</section>
