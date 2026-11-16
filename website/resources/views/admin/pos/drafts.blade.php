<dialog id="pos-drafts" class="pos-drafts-dialog" aria-labelledby="pos-drafts-title">
    <header class="pos-panel-heading"><div><span class="pos-eyebrow">CONTINUE AN UNFINISHED SALE</span><h2 id="pos-drafts-title">Draft Orders</h2></div><button type="button" class="pos-link" id="close-pos-drafts">Close ×</button></header>
    <div class="pos-drafts-body">
        <p>Saved by Super Admins. Drafts have not been placed and do not reserve stock.</p>
        <form id="pos-draft-search-form" class="pos-draft-search"><label for="pos-draft-search" class="sr-only">Search draft orders</label><input id="pos-draft-search" class="form-control" type="search" maxlength="150" placeholder="Draft number, business or customer name"><button class="btn btn-outline-primary">Search</button></form>
        <p id="pos-draft-error" class="pos-error" role="alert" hidden></p>
        <div id="pos-draft-rows" aria-live="polite"></div>
        <div class="pos-draft-pages"><button type="button" id="pos-draft-prev" class="btn btn-outline-primary">Previous</button><span id="pos-draft-page"></span><button type="button" id="pos-draft-next" class="btn btn-outline-primary">Next</button></div>
    </div>
</dialog>
