<div class="purchase-modal" data-supplier-pdf-modal hidden>
<section class="purchase-dialog supplier-pdf-dialog" role="dialog" aria-modal="true" aria-labelledby="supplier-pdf-title">
    <header class="purchase-filter-actions"><h2 id="supplier-pdf-title" tabindex="-1">Supplier order PDF</h2><button type="button" class="btn btn-outline-primary" data-supplier-pdf-close aria-label="Close PDF options">×</button></header>
    <p data-supplier-pdf-context></p><p class="text-muted">Select columns in the order you want them printed. Use the arrow buttons to rearrange them. Quantities and prices come from the saved order.</p>
    <p class="alert alert-danger" role="alert" data-supplier-pdf-error hidden></p>
    <div data-supplier-pdf-content hidden>
        <div class="purchase-filter-actions my-3"><button class="btn btn-sm btn-outline-primary" type="button" data-supplier-pdf-preset="default">Default columns</button><button class="btn btn-sm btn-outline-primary" type="button" data-supplier-pdf-preset="all">All columns</button><button class="btn btn-sm btn-outline-primary" type="button" data-supplier-pdf-preset="clear">Clear selection</button></div>
        <fieldset class="supplier-pdf-choices"><legend>Choose PDF columns</legend><div data-supplier-pdf-choices></div></fieldset>
        <h3 class="mt-3">Selected column order</h3><ol class="supplier-pdf-order" data-supplier-pdf-order></ol>
        <p class="text-muted" data-supplier-pdf-layout aria-live="polite"></p>
        <p class="text-muted" data-supplier-pdf-legacy hidden>For older orders, source name, manufacturer and brand use current product details where available. Saved prices, quantities and order names are unchanged.</p>
        <h3>Table preview</h3><div class="supplier-pdf-preview" data-supplier-pdf-preview></div><div class="purchase-totals" data-supplier-pdf-totals></div>
    </div>
    <footer class="purchase-filter-actions mt-3"><button type="button" class="btn btn-outline-primary" data-supplier-pdf-close>Cancel</button><form method="get" data-supplier-pdf-form><input type="hidden" name="columns"><button type="submit" class="btn btn-primary" data-supplier-pdf-generate disabled>Generate PDF</button></form></footer>
</section></div>
<script defer src="{{ asset('admin-assets/supplier-order-pdf.js') }}?v={{ filemtime(public_path('admin-assets/supplier-order-pdf.js')) }}"></script>
