@extends('layouts.mmc-page', ['pageTitle'=>'Checkout'])
@section('page-content')
<nav class="mmc-shopping-steps"><a href="{{ route('theme.cart') }}">01 · Cart</a><strong>02 · Checkout</strong><span>03 · Confirmation</span></nav>
@if($errors->any())<div class="mmc-panel mmc-error-alert" role="alert"><p>Please check the following:</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><a href="{{ route('theme.cart') }}">Review your cart</a></div>@endif
<form class="mmc-cart-layout mmc-form" method="post" action="{{ route('checkout.store') }}" data-place-order data-base-total="{{ $cart['total']+$charges['tax'] }}" data-flat-delivery="{{ $charges['delivery'] }}">
@csrf<input type="hidden" name="checkout_token" value="{{ $token }}">
<section class="mmc-panel"><h2>Customer & Delivery Details</h2><div class="mmc-form-grid">
@foreach(['first_name'=>['First name','given-name',100],'last_name'=>['Last name','family-name',100],'email'=>['Email address','email',255],'phone'=>['Phone number','tel',40],'address'=>['Street address','street-address',255],'city'=>['City','address-level2',100],'province'=>['Province / State','address-level1',100],'postal_code'=>['Postal code','postal-code',30],'country'=>['Country','country-name',100]] as $key=>$field)
@if($key==='address')
<div style="grid-column:1/-1"><label for="checkout-fulfillment">Order type</label><select class="form-control" id="checkout-fulfillment" name="fulfillment"><option value="delivery" @selected(old('fulfillment','delivery')==='delivery')>Delivery</option><option value="pickup" @selected(old('fulfillment')==='pickup') @disabled(!trim($settings->pickup_address ?? ''))>Pick up{{ trim($settings->pickup_address ?? '') ? '' : ' — currently unavailable' }}</option></select></div>
<div id="checkout-pickup-address" style="grid-column:1/-1;white-space:pre-line" hidden><strong>Pickup address</strong><br>{{ $settings->pickup_address }}</div>
@endif
<div @if(in_array($key,['address','city','province','postal_code','country'])) data-delivery-field @endif><label for="checkout-{{ $key }}">{{ $field[0] }}</label><input class="form-control" id="checkout-{{ $key }}" name="{{ $key }}" type="{{ $key==='email'?'email':($key==='phone'?'tel':'text') }}" autocomplete="{{ $field[1] }}" maxlength="{{ $field[2] }}" value="{{ old($key,$profile[$key]??($key==='country'?'Canada':'')) }}" required @readonly($key==='email') @error($key) aria-invalid="true" @enderror></div>
@endforeach</div>
@if($settings->matrix_delivery_enabled)
<div data-delivery-field><label for="delivery-service">Delivery service</label><select class="form-control" id="delivery-service" name="delivery_service" required><option value="">Choose a service</option>@foreach(\Illuminate\Support\Facades\DB::table('delivery_services')->where('is_active',true)->get() as $service)<option data-notes="{{ $service->notes }}" value="{{ $service->code }}" @selected(old('delivery_service')===$service->code)>{{ $service->name }}{{ trim($service->description ?? '') !== '' ? ' ('.trim($service->description).')' : '' }}</option>@endforeach</select><p id="delivery-quote-feedback" role="status">Enter your postal code and select a service.</p></div>
@endif
<label for="order-notes">Order notes (optional)</label><textarea class="form-control" id="order-notes" name="notes" maxlength="2000" rows="3">{{ old('notes') }}</textarea></section>
<aside class="mmc-panel mmc-checkout-summary"><p class="mmc-eyebrow">ORDER SUMMARY</p><h2>Your Order</h2><div class="mmc-checkout-items">@foreach($cart['items'] as $item)<div class="mmc-checkout-item"><span>{{ $item['product']->premium_marketing_name }}<small>Quantity: {{ $item['quantity'] }}</small></span><strong>${{ number_format($item['line_cents']/100,2) }}</strong></div>@endforeach</div>
<div class="mmc-checkout-charges"><div class="mmc-checkout-subtotal"><strong>Product subtotal <small>(CAD)</small></strong><strong>${{ number_format($cart['total']/100,2) }}</strong></div><div><span id="checkout-delivery-label">Delivery</span><span id="checkout-delivery-amount">{{ $settings->matrix_delivery_enabled?'Select a service':'$'.number_format($charges['delivery']/100,2) }}</span></div><div><span>Tax ({{ rtrim(rtrim(number_format($charges['rate']/100,2),'0'),'.') }}%)</span><span>${{ number_format($charges['tax']/100,2) }}</span></div></div>
<div class="mmc-checkout-total"><div><strong>Total amount</strong><small>CAD · Includes delivery and tax</small></div><strong id="checkout-grand-total">{{ $settings->matrix_delivery_enabled?'Awaiting delivery quote':'$'.number_format(($cart['total']+$charges['delivery']+$charges['tax'])/100,2) }}</strong></div>
<div class="mmc-payment-options"><label for="checkout-payment">How would you like to pay?</label>
<select id="checkout-payment" class="form-control" name="payment_method" required>
@php($cardReady = app(\App\Services\PaymentGateway::class)->ready('helcim'))
<option value="card" @disabled(!$cardReady) @selected(old('payment_method')==='card')>Pay by card{{ $cardReady ? ' · Helcim' : ' — temporarily unavailable' }}</option>
<option value="paypal" disabled>Pay with PayPal — temporarily unavailable</option>
<option value="cash" @selected(old('payment_method','cash')==='cash')>Cash on delivery</option>
<option value="etransfer" @selected(old('payment_method')==='etransfer')>E-transfer</option>
</select><p id="checkout-transfer-note" hidden>Place your order, then upload your e-transfer receipt for admin verification before warehouse processing.</p></div>
<button class="mmc-button" type="submit">Place Order</button></aside></form>
<script>window.deliveryOptionsUrl=@json(route('checkout.delivery-options'));window.deliveryQuoteUrl=@json(route('checkout.delivery-quote'));</script><script src="{{ asset('assets/js/delivery-quote.js') }}?v={{ filemtime(public_path('assets/js/delivery-quote.js')) }}"></script>
@endsection
