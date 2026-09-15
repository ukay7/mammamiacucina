@php
$extraImage = match($product['slug']) {
    'cassata-siciliana' => 'assets/images/mmc/category-cakes-hd.png',
    'operetta-pastries' => 'assets/images/mmc/pastries-hero.png',
    'cannoli-cornetti' => 'assets/images/mmc/cannoli-hero.png',
    default => null,
};
$videoId=$siteSettings?->youtube_video_id;
$hasVideo=is_string($videoId) && preg_match('/^[A-Za-z0-9_-]{11}$/',$videoId);
@endphp
<div class="mmc-media-gallery" data-product-media>
    <div class="mmc-media-stage">
        <div class="mmc-media-photo" data-media-photo><img src="{{ asset($product['image']) }}" alt="{{ $product['name'] }}" data-media-image></div>
        <div class="mmc-media-video" data-media-video hidden></div>
    </div>
    <div class="mmc-media-thumbnails" role="group" aria-label="Product photos and videos">
        <button type="button" aria-pressed="true" data-photo="{{ asset($product['image']) }}" data-caption="{{ $product['name'] }} — product photo"><img src="{{ asset($product['image']) }}" alt=""><span>Product photo</span></button>
        <button type="button" aria-pressed="false" data-photo="{{ asset($product['image']) }}" data-closeup="true" data-caption="{{ $product['name'] }} — close-up"><img src="{{ asset($product['image']) }}" alt="" class="mmc-media-thumb-closeup"><span>Close-up</span></button>
        @if($extraImage)<button type="button" aria-pressed="false" data-photo="{{ asset($extraImage) }}" data-caption="{{ $product['name'] }} — collection photo"><img src="{{ asset($extraImage) }}" alt=""><span>Collection photo</span></button>@endif
        @if($hasVideo)<button type="button" aria-pressed="false" data-video="{{ $videoId }}" data-caption="Italian recipe inspiration — the YouTube video shared for this website"><span class="mmc-media-play" aria-hidden="true">▶</span><span>Recipe video</span></button>@endif
    </div>
    <p class="mmc-media-caption" data-media-caption aria-live="polite">{{ $product['name'] }} — product photo</p>
</div>
