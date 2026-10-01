<section class="ps-section ps-section--home-testimonial mmc-tradition" aria-labelledby="tradition-title">
    <div class="mmc-story-inner">
    <div class="mmc-tradition__copy">
        <p class="mmc-tradition__eyebrow">{{ $homeSection->content['eyebrow'] }}</p>
        <div class="mmc-tradition__rule" aria-hidden="true"><span>♥</span></div>
        <h2 id="tradition-title">{!! nl2br(e($homeSection->content['heading'])) !!}</h2>
        <p class="mmc-tradition__description">{!! nl2br(e($homeSection->content['description'])) !!}</p>
        <div class="mmc-tradition__ornament" aria-hidden="true">── ❧♧❧ ──</div>
    </div>
    <figure class="mmc-story-art"><img src="{{ $homeSection->imageUrl() }}" alt="{{ $homeSection->content['image_alt'] }}" loading="lazy" width="1920" height="1080"></figure>
    </div>
</section>
