@extends('admin.layout')
@section('title',$list->exists?'Edit '.$list->number:'New Pricing List')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/pricing-lists.css') }}?v={{ filemtime(public_path('admin-assets/pricing-lists.css')) }}">
<div id="pricing-editor">
<p>Prepare a price list for a customer. Your changes apply only to this list; product catalogue prices stay unchanged.</p>
<div id="pricing-errors" role="alert" class="alert alert-danger" hidden></div>
<form id="pricing-details" class="panel p-3 mb-3">
<p>Pricing list number: <strong id="list-reference">{{ $list->number ?: 'Assigned when previewed' }}</strong></p>
<div class="pricing-grid">
<label>List title<input id="list-title" class="form-control" maxlength="150" required></label>
<label>Base pricing<select id="list-tier" class="form-control"><option value="individual">Individual pricing</option><option value="business">Business pricing</option></select></label>
<label>Prices as of<input id="list-date" class="form-control" type="date" required></label>
<label>Valid through (optional)<input id="list-until" class="form-control" type="date"></label>
<label>Customer (optional)<input id="list-customer" class="form-control" list="pricing-customers" placeholder="Search customer #, business or name" autocomplete="off"><datalist id="pricing-customers">@foreach($customers as $customer)<option data-id="{{ $customer->id }}" data-recipient="{{ $customer->business_name ?: $customer->name }}" value="#{{ $customer->id }} · {{ $customer->business_name ? $customer->business_name.' · ' : '' }}{{ $customer->name }}"></option>@endforeach</datalist></label>
<label>Prepared for (optional)<input id="list-recipient" class="form-control" maxlength="255" placeholder="Customer or business name"></label>
<label class="pricing-wide">Notes printed on this list<textarea id="list-notes" class="form-control" rows="2" maxlength="3000" placeholder="Terms or a message for the recipient"></textarea></label>
</div><label class="mb-0"><input id="list-images" type="checkbox"> Include product photos in PDF</label>
<p class="text-muted mt-2 mb-0">Each and box prices are linked when the pack quantity is known. Changing the base pricing keeps your custom prices. A missing business price uses the individual price and is marked below.</p>
</form>
<section class="panel p-3 mb-3" aria-label="Product filters"><div class="pricing-grid">
<label>Find a product<input id="filter-q" class="form-control" placeholder="Code, product, category or pack"></label>
<label>Supplier<select id="filter-supplier" class="form-control"><option value="">All suppliers</option></select></label>
<label>Product status<select id="filter-active" class="form-control"><option value="active">Active products</option><option value="all">All products</option><option value="inactive">Inactive products</option></select></label>
<label>Selection<select id="filter-selection" class="form-control"><option value="all">All products</option><option value="selected">Selected products</option><option value="unselected">Unselected products</option></select></label>
<label>Prices<select id="filter-price-state" class="form-control"><option value="all">All prices</option><option value="priced">With a price</option><option value="missing">Missing both prices</option></select></label>
<label>Minimum box price<input id="filter-min-price" class="form-control" type="number" min="0" step="0.01"></label>
<label>Maximum box price<input id="filter-max-price" class="form-control" type="number" min="0" step="0.01"></label>
<label>Sort within category<select id="filter-sort" class="form-control"><option value="name">Product name</option><option value="code">Product code</option><option value="price">Box price (low to high)</option></select></label>
</div><details class="pricing-categories"><summary>Filter categories <span id="category-filter-count"></span></summary><p class="text-muted my-2">No categories checked means all categories.</p><div id="category-filters"></div></details>
<button type="button" id="clear-filters" class="btn btn-sm btn-outline-primary mt-2">Clear filters</button></section>
<div class="pricing-actions panel p-3"><div><strong id="selection-count" aria-live="polite"></strong><div class="text-muted" id="visible-count"></div></div><div class="pricing-buttons">
<button type="button" id="select-visible" class="btn btn-sm btn-outline-primary">Select visible</button><button type="button" id="clear-visible" class="btn btn-sm btn-outline-primary">Clear visible</button><button type="button" id="reset-prices" class="btn btn-sm btn-outline-primary">Reset selected prices</button>
<button type="button" id="review-save" class="btn btn-outline-primary">Review &amp; save</button><button type="button" id="review-pdf" class="btn btn-primary">Review &amp; print PDF</button></div></div>
<div class="panel pricing-table-wrap"><table class="table pricing-table"><thead><tr><th class="pricing-selection">Select</th><th>Code</th><th>Product</th><th>Pack</th><th>Each (CAD)</th><th>Box (CAD)</th></tr></thead><tbody id="pricing-rows"></tbody></table></div>
<a href="{{ route('admin.pricing-lists.index') }}" class="btn btn-outline-primary">Back to pricing lists</a>
</div>
<dialog id="pricing-confirm" class="pricing-dialog" aria-labelledby="pricing-confirm-title"><div class="pricing-dialog-head"><h2 id="pricing-confirm-title">Confirm pricing list</h2><button id="close-confirm" type="button" class="btn btn-outline-primary">Back to editing</button></div><p id="confirm-summary"></p><p>Check the recipient, dates, categories, products and prices below. This includes all selected products, even those hidden by filters.</p><iframe id="pricing-preview" title="Full pricing list preview" sandbox=""></iframe><div class="pricing-dialog-foot"><span id="confirm-error" role="alert"></span><button id="confirm-save" type="button" class="btn btn-primary">Confirm &amp; save</button></div></dialog>
<script>window.pricingListConfig = {{ Illuminate\Support\Js::from($config) }};</script>
<script src="{{ asset('admin-assets/pricing-lists.js') }}?v={{ filemtime(public_path('admin-assets/pricing-lists.js')) }}" defer></script>
@endsection
