@php
    $youtubeId = config('homepage.youtube_video_id');
    $hasVideo = is_string($youtubeId) && preg_match('/^[A-Za-z0-9_-]{11}$/', $youtubeId);
@endphp
<section class="mmc-about-video" aria-label="About Mamma Mia Cucina">
    <div class="mmc-about-video__grid">
        <div class="mmc-about-video__about">
            <h2>A Little Italy, A Lot of Love</h2>
            <p>Some of the best moments happen around the table. At Mamma Mia Cucina, we celebrate those moments with Italian favourites—from delicate pastries and creamy cannoli to cakes made for sharing.</p>
            <p>Whether it’s a family celebration, coffee with friends, or a sweet treat just for you, discover something to make the occasion a little more delicious.</p>
            <span class="mmc-about-video__signature">Bring everyone together. Share a taste of Italy.</span>
        </div>
        <div class="mmc-about-video__video">
            <h2>Italian Inspiration, Served Fresh</h2>
            @if ($hasVideo)
                <div class="mmc-about-video__player">
                    <iframe src="https://www.youtube-nocookie.com/embed/{{ $youtubeId }}" title="Mamma Mia Cucina video" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                </div>
                <a class="mmc-about-video__watch" href="https://www.youtube.com/watch?v={{ $youtubeId }}" target="_blank" rel="noopener noreferrer">Watch on YouTube ↗</a>
            @else
                <div class="mmc-about-video__player mmc-about-video__placeholder">
                    <img src="{{ asset('assets/images/mmc/italian-pastries-hero.png') }}" alt="Italian cakes and pastries">
                    <span>Our video is coming soon</span>
                </div>
            @endif
        </div>
    </div>
</section>

