<aside id="pos-recommendations" class="pos-recommendations" data-url="{{ route('admin.pos.recommendations') }}" aria-label="Suggested products for this sale">
    <section class="pos-recommendation-group" aria-labelledby="pos-favourites-title">
        <header><span class="pos-recommendation-symbol" aria-hidden="true"><i class="fas fa-history"></i></span><div><h3 id="pos-favourites-title">Customer favourites</h3><p>Most ordered · completed orders</p></div></header>
        <div id="pos-favourites" class="pos-recommendation-list" aria-live="polite"><p class="pos-recommendation-empty">Select a customer to see their favourites.</p></div>
    </section>
    <section class="pos-recommendation-group pos-stock-suggestions" aria-labelledby="pos-stock-title">
        <header><span class="pos-recommendation-symbol" aria-hidden="true"><i class="fas fa-boxes"></i></span><div><h3 id="pos-stock-title">Stock to move</h3><p>Stock above 90-day sales</p></div></header>
        <div id="pos-stock-suggestions" class="pos-recommendation-list" aria-live="polite"><p class="pos-recommendation-empty">Select a customer to see stock suggestions and their prices.</p></div>
    </section>
    <button type="button" id="pos-recommendation-retry" class="btn btn-outline-primary" hidden>Retry suggestions</button>
</aside>
