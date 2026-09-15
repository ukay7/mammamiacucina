    <header class="mmc-masthead">
        <a class="mmc-brand" href="{{ route('theme.index') }}" aria-label="Mamma Mia Cucina home">
            <img src="{{ $siteSettings?->logoUrl() ?? asset('logo.png') }}" alt="Mamma Mia Cucina" width="4168" height="4168">
        </a>
        <button class="mmc-menu-toggle" type="button" aria-expanded="false" aria-controls="mmc-menu">
            <span class="mmc-menu-toggle__icon" aria-hidden="true"></span><span>Menu</span>
        </button>
        <nav class="mmc-menu" id="mmc-menu" aria-label="Main navigation">
            <a href="{{ route('theme.index') }}" @if(request()->routeIs('theme.index')) aria-current="page" @endif>Home</a>
            <a href="{{ route('theme.about') }}" @if(request()->routeIs('theme.about')) aria-current="page" @endif>About Us</a>
            <div class="mmc-products-menu">
                <button class="mmc-products-toggle" type="button" aria-expanded="false" aria-controls="mmc-products-submenu">Products <span aria-hidden="true">⌄</span></button>
                <ul class="mmc-products-submenu" id="mmc-products-submenu" hidden>
                    <li><a href="{{ route('theme.product-grid') }}">All Products</a></li>
                    @foreach($menuCategories as $menuCategory)
                    <li><a href="{{ route('theme.product-grid', ['category' => $menuCategory->slug]) }}">{{ $menuCategory->name }}</a></li>
                    @endforeach
                </ul>
            </div>
            <a href="{{ route('theme.gallery') }}" @if(request()->routeIs('theme.gallery')) aria-current="page" @endif>Gallery</a>
            <a href="{{ route('theme.contact') }}" @if(request()->routeIs('theme.contact')) aria-current="page" @endif>Contact Us</a>
        </nav>
        <a class="mmc-header-track" href="{{ route('order.track-form') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h11v11H3zM14 10h4l3 4v3h-7M3 10h5"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg><span>Track Order</span></a>
        <details class="mmc-header-cart">
            <summary aria-label="Shopping cart">@include('partials.cart-icon')<span class="mmc-header-cart__count">{{ $cart['count'] }}</span></summary>
            <div class="mmc-header-cart__panel"><div data-live-mini-cart>@include('partials.live-mini-cart')</div><p data-mini-cart-feedback role="status"></p></div>
        </details>
    </header>

<a class="mmc-floating-cart" data-floating-cart href="{{ route('theme.cart') }}" aria-label="View shopping cart" hidden>
    @include('partials.cart-icon')
    <span class="mmc-header-cart__count">{{ $cart['count'] }}</span>
</a>
