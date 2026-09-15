@extends('admin.layout')
@section('title','Quick Sale / POS')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/pos.css') }}?v={{ filemtime(public_path('admin-assets/pos.css')) }}">
<div id="pos-app" data-products="{{ route('admin.pos.products') }}" data-quote="{{ route('admin.pos.quote') }}" data-store="{{ route('admin.pos.store') }}" data-csrf="{{ csrf_token() }}">
<div class="pos-intro"><div><span class="pos-eyebrow">THE COUNTER</span><p>Scan. Add. Serve with a smile.</p></div><span class="pos-badge">Prices in CAD</span></div>
<div id="pos-feedback" class="pos-feedback" role="status" aria-live="polite" hidden></div>
<div class="pos-layout">
<section class="pos-panel pos-discovery" aria-labelledby="scan-heading">
<div class="pos-panel-heading"><h2 id="scan-heading">Find a product</h2><button type="button" id="camera-start" class="btn btn-outline-primary"><i class="fas fa-camera" aria-hidden="true"></i> Scan with camera</button></div>
<form id="pos-scan-form" class="pos-search"><label for="scan-code">QR / barcode scanner</label><div><input id="scan-code" type="text" class="form-control" autocomplete="off" placeholder="Scan or enter a code, then press Enter"><button class="btn btn-primary" type="submit">Add</button></div></form>
<p class="pos-help">Connected scanners work in the code field. Each scan adds one item.</p>
<div id="camera-area" hidden><video id="pos-video" muted playsinline></video><div class="pos-camera-tools"><span>Hold the code steady, then move it away to scan again.</span><button id="camera-stop" type="button" class="btn btn-outline-primary">Stop camera</button></div></div>
<div class="pos-search"><label for="product-search">Or search by product name or code</label><input id="product-search" type="search" class="form-control" placeholder="Start typing to find products…" autocomplete="off"></div>
<div id="pos-results" class="pos-results" aria-live="polite"><p class="pos-empty">Your next sale starts with a scan.<br><small>You can also search products above.</small></p></div>
</section>
<section class="pos-panel pos-basket" aria-labelledby="sale-heading">
<div class="pos-panel-heading"><h2 id="sale-heading">Current sale <span id="pos-item-count">0</span></h2><button type="button" id="clear-sale" class="pos-link">Clear sale</button></div>
<div id="pos-lines"><p class="pos-empty">No products added yet.</p></div>
<div class="pos-basket-footer"><label for="fulfillment">How will the customer receive it?</label><select class="form-control" id="fulfillment"><option value="pickup">Collected in store · no delivery charge</option><option value="delivery">Delivery · charge from General Settings</option></select>
<div class="pos-subtotal"><span>Product subtotal</span><strong id="pos-subtotal">$0.00</strong></div><p class="pos-help">Tax and any delivery charge appear in the checkout review. Stock is checked when you complete the sale.</p>
<button id="review-sale" class="btn btn-primary pos-checkout" type="button" disabled>Review & Checkout <span aria-hidden="true">→</span></button>
</div></section>
</div>
<dialog id="pos-checkout" aria-labelledby="checkout-heading">
<form id="complete-sale"><header class="pos-panel-heading"><div><span class="pos-eyebrow">FINAL CHECK</span><h2 id="checkout-heading">Complete this sale</h2></div><button type="button" id="close-checkout" class="pos-link">Close ×</button></header>
<div class="pos-checkout-body"><div id="quote-lines"></div><div class="pos-totals" id="quote-totals"></div>
<p id="customer-help" class="pos-help"></p>
<div class="pos-customer-grid"><label>First name<input class="form-control" name="first_name" maxlength="100" autocomplete="given-name"></label><label>Last name<input class="form-control" name="last_name" maxlength="100" autocomplete="family-name"></label><label>Phone<input class="form-control" name="phone" maxlength="40" type="tel" autocomplete="tel"></label><label>Email (optional)<input class="form-control" name="email" maxlength="255" type="email" autocomplete="email"></label></div>
<div id="delivery-fields" class="pos-customer-grid" hidden><label class="pos-wide">Delivery address<input class="form-control" name="address" maxlength="255" autocomplete="street-address"></label><label>City<input class="form-control" name="city" maxlength="100" autocomplete="address-level2"></label><label>Province<input class="form-control" name="province" maxlength="100" autocomplete="address-level1"></label><label>Postal code<input class="form-control" name="postal_code" maxlength="30" autocomplete="postal-code"></label><label>Country<input class="form-control" name="country" maxlength="100" value="Canada" autocomplete="country-name"></label></div>
<div class="pos-customer-grid"><label>Payment method<select class="form-control" name="payment_method"><option value="cash">Cash</option><option value="card">Card · external terminal</option></select></label><label>Payment status<select class="form-control" name="payment_status"><option value="paid">Paid · payment received</option><option value="unpaid">Unpaid · collect on delivery</option></select></label></div>
<p class="pos-help">Select Paid only after you receive the payment. Card payments must be taken on your separate card terminal.</p>
<label>Notes (optional)<textarea class="form-control" name="notes" rows="2" maxlength="2000"></textarea></label>
<p id="checkout-error" class="pos-error" role="alert" hidden></p>
<button class="btn btn-primary pos-checkout" id="complete-button" type="submit">Complete sale</button>
<button class="pos-link" id="refresh-quote" type="button" hidden>Refresh prices and review again</button>
</div></form>
</dialog>
<dialog id="pos-success" aria-labelledby="success-heading"><div class="pos-success-body"><span class="pos-success-icon" aria-hidden="true">✓</span><span class="pos-eyebrow">ALL DONE</span><h2 id="success-heading">Sale completed</h2><p id="success-number"></p><strong id="success-total"></strong><p>The order has been saved and tracked stock updated.</p><a class="btn btn-outline-primary" id="receipt-link" target="_blank" rel="noopener">Print receipt / PDF</a><button class="btn btn-primary" id="new-sale" type="button">Start next sale</button></div></dialog>
</div>
<script src="{{ asset('admin-assets/scanner-libs/zxing-browser-0.1.5.min.js') }}"></script>
<script src="{{ asset('admin-assets/pos.js') }}?v={{ filemtime(public_path('admin-assets/pos.js')) }}"></script>
@endsection