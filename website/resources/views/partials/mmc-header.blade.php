    <header class="mmc-masthead">
        <a class="mmc-brand" href="{{ route('theme.index') }}" aria-label="Mamma Mia Cucina home">
            <img src="{{ asset('logo.png') }}" alt="Mamma Mia Cucina" width="4168" height="4168">
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
                    <li><a href="{{ route('theme.product-grid', ['category' => 'cakes']) }}">Cake</a></li>
                    <li><a href="{{ route('theme.product-grid', ['category' => 'pastries']) }}">Pastry</a></li>
                    <li><a href="{{ route('theme.product-grid', ['category' => 'cannoli']) }}">Cannoli</a></li>
                </ul>
            </div>
            <a href="{{ route('theme.gallery') }}" @if(request()->routeIs('theme.gallery')) aria-current="page" @endif>Gallery</a>
            <a href="{{ route('theme.contact') }}" @if(request()->routeIs('theme.contact')) aria-current="page" @endif>Contact Us</a>
        </nav>
        <details class="mmc-header-cart">
            <summary aria-label="Shopping cart"><i class="ps-icon--shopping-cart" aria-hidden="true"></i><span class="mmc-header-cart__count">0</span></summary>
            <div class="mmc-header-cart__panel">
                <h2>Your Cart</h2>
                <div class="mmc-mini-cart-items" data-mini-cart></div>
                <p>Your cart is currently empty.</p>
                <a class="mmc-header-cart__link" href="{{ route('theme.cart') }}">View Cart</a>
                <a class="mmc-header-cart__browse" href="{{ route('theme.product-grid') }}">Explore our products</a>
            </div>
        </details>
    </header>
