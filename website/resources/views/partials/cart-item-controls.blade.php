        <div class="mmc-mini-cart-actions">
        <form class="mmc-mini-cart-stepper" data-live-cart-form method="post" action="{{ route('cart.update',$p) }}">
            @csrf @method('PATCH')
            <button type="submit" name="quantity" value="{{ $item['quantity'] - 1 }}" aria-label="Decrease quantity of {{ $p->premium_marketing_name }}" @disabled($item['quantity'] <= 1)>−</button>
            <output aria-label="Quantity">{{ $item['quantity'] }}</output>
            <button type="submit" name="quantity" value="{{ $item['quantity'] + 1 }}" aria-label="Increase quantity of {{ $p->premium_marketing_name }}" @disabled($item['quantity'] >= 99)>+</button>
        </form>
        <form data-live-cart-form method="post" action="{{ route('cart.remove',$p) }}">
            @csrf @method('DELETE')
            <button class="mmc-mini-cart-delete" type="submit" aria-label="Remove {{ $p->premium_marketing_name }} from cart" title="Remove product">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/></svg>
            </button>
        </form>
        </div>
