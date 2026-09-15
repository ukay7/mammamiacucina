<footer class="ps-footer mmc-footer-compact">
        <div class="container">
          <div class="row">
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 ">
                  <div class="ps-widget ps-widget--footer ps-widget--brand">
                    <a class="mmc-footer-brand" href="{{ route('theme.index') }}" aria-label="Mamma Mia Cucina home">
                      <img src="{{ $siteSettings?->logoUrl() ?? asset('logo.png') }}" alt="Mamma Mia Cucina" loading="lazy">
                    </a>
                  </div>
                </div>
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 ">
                  <div class="ps-widget ps-widget--footer ps-widget--worktime">
                    <div class="ps-widget__header">
                      <h3 class="ps-widget__title">Opening Hours</h3>
                    </div>
                    <div class="ps-widget__content">
                      @include('partials.site-hours')
                    </div>
                  </div>
                </div>
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 ">
                  <div class="ps-widget ps-widget--footer ps-widget--order">
                    <div class="ps-widget__header">
                      <h3 class="ps-widget__title">Pages</h3>
                    </div>
                    <div class="ps-widget__content">
                          <ul class="ps-list--line">
                            <li><a href="{{ route('theme.index') }}">Home</a></li>
                            <li><a href="{{ route('theme.about') }}">About Us</a></li>
                            <li><a href="{{ route('theme.product-grid') }}">Products</a></li>
                            <li><a href="{{ route('theme.contact') }}">Contact Us</a></li>
                          </ul>
                    </div>
                  </div>
                </div>
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 ">
                  <div class="ps-widget ps-widget--footer ps-widget--connect">
                    <div class="ps-widget__header">
                      <h3 class="ps-widget__title">CONNECT US</h3>
                    </div>
                    <div class="ps-widget__content">
                          @include('partials.site-contact')
                          @if($siteSettings?->facebook_url || $siteSettings?->instagram_url || $siteSettings?->twitter_url)
                          <ul class="ps-widget__social">
                            @foreach(['facebook_url'=>['Facebook','facebook'],'twitter_url'=>['Twitter / X','twitter'],'instagram_url'=>['Instagram','instagram']] as $field=>$social)
                            @if($siteSettings?->$field)<li><a href="{{ $siteSettings->$field }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $social[0] }}"><i class="fa fa-{{ $social[1] }}" aria-hidden="true"></i></a></li>@endif
                            @endforeach
                          </ul>
                          @endif
                      <p>@2026 Design and Developed by <span class="mmc-footer-credit">360 Creative Agency.</span></p>
                    </div>
                  </div>
                </div>
          </div>
        </div>
      </footer>