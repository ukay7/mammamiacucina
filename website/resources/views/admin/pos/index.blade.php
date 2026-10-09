@extends('admin.layout')
@section('title','Quick Sale / POS')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/pos.css') }}?v={{ filemtime(public_path('admin-assets/pos.css')) }}">
<div id="pos-app" data-customer-store="{{ route('admin.pos.customer') }}" data-matrix="{{ \App\Models\GeneralSetting::findOrFail(1)->matrix_delivery_enabled?'1':'0' }}" data-delivery-options="{{ route('admin.pos.delivery-options') }}" data-products="{{ route('admin.pos.products') }}" data-quote="{{ route('admin.pos.quote') }}" data-store="{{ route('admin.pos.store') }}" data-csrf="{{ csrf_token() }}">
<div class="pos-intro"><div><span class="pos-eyebrow">THE COUNTER</span><p>Scan. Add. Serve with a smile.</p></div><span class="pos-badge">Prices in CAD</span></div>
<div id="pos-feedback" class="pos-feedback" role="status" aria-live="polite" hidden></div>
<section class="pos-panel pos-customer-step"><div class="pos-panel-heading"><h2>1 · Customer</h2><a class="btn btn-outline-primary" href="{{ route('admin.pos.orders') }}">My POS orders</a></div><form id="pos-customer-form" class="pos-customer-body"><label>Find customer<input class="form-control" id="pos-customer-search" placeholder="Search customer ID, name, email or phone" type="search"></label><label>Customer<select class="form-control" name="existing_customer_id" id="pos-customer"><option value="">Choose a customer…</option><option value="new">+ Add new customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" data-pending="{{ $customer->user->businessApprovalPending()?'1':'0' }}" data-profile="{{ json_encode($customer->only(['name','email','phone','address','city','province','postal_code','country','account_type'])) }}">#{{ $customer->id }} · {{ $customer->name }} — {{ $customer->email }} {{ $customer->phone }}</option>@endforeach</select></label>
<div id="pos-new-customer" hidden><label id="pos-account-type">Account type<select class="form-control" name="account_type"><option value="individual">Individual</option><option value="business">Business owner</option></select></label>
@include('partials.business-fields',['businessDynamic'=>true])

<div class="pos-customer-grid"><label>First name<input class="form-control" name="first_name" maxlength="100" autocomplete="given-name"></label><label>Last name<input class="form-control" name="last_name" maxlength="100" autocomplete="family-name"></label><label>Phone<input class="form-control" name="phone" maxlength="40" type="tel" autocomplete="tel"></label><label>Email (required for new customers)<input class="form-control" name="email" required maxlength="255" type="email" autocomplete="email"></label></div>
<details><summary>Address (optional)</summary><div class="pos-customer-grid">@foreach(['address'=>'Street address','city'=>'City','province'=>'Province','postal_code'=>'Postal code','country'=>'Country'] as $key=>$label)<label>{{ $label }}<input class="form-control" name="{{ $key }}" maxlength="255" value="{{ $key==='country'?'Canada':'' }}"></label>@endforeach</div></details></div><div class="pos-customer-actions"><button type="submit" class="btn btn-primary" id="confirm-customer">Use selected customer</button><span id="pos-customer-summary" role="status">Select a customer or create one to start.</span></div></form></section>
<div class="pos-layout">
<section class="pos-panel pos-discovery" aria-labelledby="scan-heading">
<div class="pos-panel-heading"><h2 id="scan-heading">Find a product</h2><button type="button" id="camera-start" class="btn btn-outline-primary"><i class="fas fa-camera" aria-hidden="true"></i> Scan with camera</button></div>
<form id="pos-scan-form" class="pos-search"><label for="scan-code">QR / barcode scanner</label><div><input id="scan-code" type="text" class="form-control" autocomplete="off" placeholder="Scan or enter a code, then press Enter"><button class="btn btn-primary" type="submit">Add</button></div></form>
<p class="pos-help">Connected scanners work in the code field. Each scan adds one item.</p>
<div id="camera-area" hidden><video id="pos-video" muted playsinline></video><div class="pos-camera-tools"><span>Hold the code steady, then move it away to scan again.</span><button id="camera-stop" type="button" class="btn btn-outline-primary">Stop camera</button></div></div>
<div class="pos-search"><label for="product-search">Search by name, product code, barcode or ID</label><input id="product-search" type="search" class="form-control" placeholder="Start typing to find products…" autocomplete="off"></div>
<div id="pos-results" class="pos-results" aria-live="polite"><p class="pos-empty">Your next sale starts with a scan.<br><small>You can also search products above.</small></p></div>
</section>
<section class="pos-panel pos-basket" aria-labelledby="sale-heading">
<div class="pos-panel-heading"><h2 id="sale-heading">Current sale <span id="pos-item-count">0</span></h2><button type="button" id="clear-sale" class="pos-link">Clear sale</button></div>
<div id="pos-lines"><p class="pos-empty">No products added yet.</p></div>
<div class="pos-basket-footer"><label for="fulfillment">How will the customer receive it?</label><select class="form-control" id="fulfillment"><option value="pickup">Pick up</option><option value="delivery">Delivery · postal code & service</option></select>
<div class="pos-subtotal"><span>Product subtotal</span><strong id="pos-subtotal">$0.00</strong></div><p class="pos-help">Tax and any delivery charge appear in the checkout review. Stock is checked when you complete the sale.</p>
<button id="review-sale" class="btn btn-primary pos-checkout" type="button" disabled>Review & Checkout <span aria-hidden="true">→</span></button>
</div></section>
</div>
<dialog id="pos-checkout" aria-labelledby="checkout-heading">
<form id="complete-sale"><header class="pos-panel-heading"><div><span class="pos-eyebrow">FINAL CHECK</span><h2 id="checkout-heading">Review & place order</h2></div><button type="button" id="close-checkout" class="pos-link">Close ×</button></header>
<div class="pos-checkout-body"><div id="quote-lines"></div><div class="pos-totals" id="quote-totals"></div>
<p id="customer-help" class="pos-help"></p><input type="hidden" name="customer_id"><input type="hidden" name="first_name"><input type="hidden" name="last_name"><input type="hidden" name="email"><label>Customer phone<input class="form-control" name="phone" maxlength="40" type="tel"></label>
<div id="delivery-fields" class="pos-customer-grid" hidden><label class="pos-wide">Delivery address<input class="form-control" name="address" maxlength="255" autocomplete="street-address"></label><label>City<input class="form-control" name="city" maxlength="100" autocomplete="address-level2"></label><label>Province<input class="form-control" name="province" maxlength="100" autocomplete="address-level1"></label><label>Postal code<input class="form-control" name="postal_code" maxlength="30" autocomplete="postal-code"></label><label>Country<input class="form-control" name="country" maxlength="100" value="Canada" autocomplete="country-name"></label></div>
<div id="pos-delivery-choice" hidden><label>Delivery service<select class="form-control" name="delivery_service" id="pos-delivery-service"><option value="">Enter postal code first</option></select></label><p id="pos-delivery-description" class="pos-help" role="status"></p></div>
<div class="pos-customer-grid"><label>Payment method<select class="form-control" name="payment_method"><option value="cash">Cash on pickup</option><option value="etransfer">E-transfer</option><option value="card" disabled>Card — temporarily unavailable</option><option value="paypal" disabled>PayPal — temporarily unavailable</option></select></label><label>Payment status<select class="form-control" name="payment_status"><option value="paid">Paid · payment received</option><option value="unpaid">Unpaid · collect on delivery</option></select></label></div>
<p class="pos-help">For cash pickup, confirm payment received and items handed over. E-transfer stays unpaid until admin verifies it.</p>
<div id="pos-transfer-upload" hidden><label>E-transfer receipt (optional)<input class="form-control" type="file" id="pos-transfer-file" accept="image/jpeg,image/png,image/webp,application/pdf"></label><p class="pos-help">Upload now or later from My POS Orders. JPG, PNG, WebP or PDF, maximum 5 MB. Admin verifies payment before warehouse processing.</p></div>
<label>Notes (optional)<textarea class="form-control" name="notes" rows="2" maxlength="2000"></textarea></label>
<p id="checkout-error" class="pos-error" role="alert" hidden></p>
<button class="btn btn-primary pos-checkout" id="complete-button" type="submit">Complete sale</button>
<button class="pos-link" id="refresh-quote" type="button" hidden>Refresh prices and review again</button>
</div></form>
</dialog>
<dialog id="pos-success" aria-labelledby="success-heading"><div class="pos-success-body"><span class="pos-success-icon" aria-hidden="true">✓</span><span class="pos-eyebrow">ALL DONE</span><h2 id="success-heading">Sale completed</h2><p id="success-number"></p><strong id="success-total"></strong><p id="success-status"></p><a class="btn btn-primary" id="saved-order-link">View order / upload receipt</a><a class="btn btn-outline-primary" id="receipt-link" target="_blank" rel="noopener">Print receipt / PDF</a><button class="btn btn-primary" id="new-sale" type="button">Start next sale</button></div></dialog>
</div>
<script src="{{ asset('admin-assets/scanner-libs/zxing-browser-0.1.5.min.js') }}"></script>
<script src="{{ asset('admin-assets/pos.js') }}?v={{ filemtime(public_path('admin-assets/pos.js')) }}"></script>
<script src="{{ asset('assets/js/business-fields.js') }}"></script>
@endsection
