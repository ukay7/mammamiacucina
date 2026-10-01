@extends('layouts.mmc-page', ['pageTitle'=>'Checkout'])
@section('page-content')
<nav class="mmc-shopping-steps"><a href="{{ route('theme.cart') }}">01 · Cart</a><strong>02 · Checkout</strong><span>03 · Confirmation</span></nav>
@if($errors->any())<div class="mmc-panel mmc-error-alert" role="alert"><p>Please check the following:</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><a href="{{ route('theme.cart') }}">Review your cart</a></div>@endif
<form class="mmc-cart-layout mmc-form" method="post" action="{{ route('checkout.store') }}" data-place-order>
@csrf<input type="hidden" name="checkout_token" value="{{ $token }}">
<section class="mmc-panel"><h2>Customer & Delivery Details</h2><div class="mmc-form-grid">
@foreach(['first_name'=>['First name','given-name',100],'last_name'=>['Last name','family-name',100],'email'=>['Email address','email',255],'phone'=>['Phone number','tel',40],'address'=>['Street address','street-address',255],'city'=>['City','address-level2',100],'province'=>['Province / State','address-level1',100],'postal_code'=>['Postal code','postal-code',30],'country'=>['Country','country-name',100]] as $key=>$field)
<div><label for="checkout-{{ $key }}">{{ $field[0] }}</label><input class="form-control" id="checkout-{{ $key }}" name="{{ $key }}" type="{{ $key==='email'?'email':($key==='phone'?'tel':'text') }}" autocomplete="{{ $field[1] }}" maxlength="{{ $field[2] }}" value="{{ old($key,$profile[$key]??($key==='country'?'Canada':'')) }}" required @readonly($key==='email') @error($key) aria-invalid="true" @enderror></div>
@endforeach</div>
@if($settings->matrix_delivery_enabled)
<label for="delivery-service">Delivery service</label><select class="form-control" id="delivery-service" name="delivery_service" required><option value="">Choose a service</option>@foreach(\Illuminate\Support\Facades\DB::table('delivery_services')->get() as $service)<option value="{{ $service->code }}" @selected(old('delivery_service')===$service->code)>{{ $service->name }}{{ trim($service->description ?? '') !== '' ? ' ('.trim($service->description).')' : '' }}</option>@endforeach</select><p id="delivery-quote-feedback" role="status">Enter your postal code and select a service.</p>
@endif
<label for="order-notes">Order notes (optional)</label><textarea class="form-control" id="order-notes" name="notes" maxlength="2000" rows="3">{{ old('notes') }}</textarea></section>
<aside class="mmc-panel mmc-checkout-summary"><p class="mmc-eyebrow">ORDER SUMMARY</p><h2>Your Order</h2><div class="mmc-checkout-items">@foreach($cart['items'] as $item)<div class="mmc-checkout-item"><span>{{ $item['product']->premium_marketing_name }}<small>Quantity: {{ $item['quantity'] }}</small></span><strong>${{ number_format($item['line_cents']/100,2) }}</strong></div>@endforeach</div>
<div class="mmc-checkout-charges"><div class="mmc-checkout-subtotal"><strong>Product subtotal <small>(CAD)</small></strong><strong>${{ number_format($cart['total']/100,2) }}</strong></div><div><span>Delivery</span><span id="checkout-delivery-amount">{{ $settings->matrix_delivery_enabled?'Select a service':'$'.number_format($charges['delivery']/100,2) }}</span></div><div><span>Tax ({{ rtrim(rtrim(number_format($charges['rate']/100,2),'0'),'.') }}%)</span><span>${{ number_format($charges['tax']/100,2) }}</span></div></div>
<div class="mmc-checkout-total"><div><strong>Total amount</strong><small>CAD · Includes delivery and tax</small></div><strong id="checkout-grand-total">{{ $settings->matrix_delivery_enabled?'Awaiting delivery quote':'$'.number_format(($cart['total']+$charges['delivery']+$charges['tax'])/100,2) }}</strong></div>
<fieldset class="mmc-payment-options"><legend>How would you like to pay?</legend>
@foreach(['card'=>['Pay by card','Secure checkout with Stripe','stripe'],'paypal'=>['Pay with PayPal','Continue to PayPal to approve your payment','paypal'],'cash'=>['Cash on delivery','Pay in cash when you receive your order',null]] as $method=>$option)
@php($available=$method==='cash'||app(\App\Services\PaymentGateway::class)->ready($option[2]))
<label class="mmc-payment-choice {{ $available?'':'is-unavailable' }}"><input type="radio" name="payment_method" value="{{ $method }}" @checked(old('payment_method','cash')===$method) @disabled(!$available)><span><strong>{{ $option[0] }}</strong><small>{{ $available?$option[1]:'Temporarily unavailable' }}</small></span></label>
@endforeach
<p class="mmc-note">Online payments are one-time payments. We do not store your card details.</p></fieldset>
<style>.mmc-payment-options{border:0;padding:20px 0}.mmc-payment-options legend{font:23px Georgia,serif}.mmc-payment-choice{display:flex!important;align-items:center;gap:12px;padding:13px!important;border:1px solid #dac6a1;border-radius:9px;margin:8px 0;cursor:pointer}.mmc-payment-choice input{width:18px!important;height:18px!important;flex:none;accent-color:#98091e}.mmc-payment-choice strong,.mmc-payment-choice small{display:block}.mmc-payment-choice small{font-size:12px;margin-top:4px}.mmc-payment-choice:has(input:checked){background:#f1e5d0;border-color:#aa813d}.mmc-payment-choice.is-unavailable{opacity:.55;cursor:default}</style><button class="mmc-button" type="submit">Place Order</button></aside></form>
@if($settings->matrix_delivery_enabled)
<script>window.deliveryOptionsUrl=@json(route('checkout.delivery-options'));window.deliveryQuoteUrl=@json(route('checkout.delivery-quote'));</script><script src="{{ asset('assets/js/delivery-quote.js') }}?v={{ filemtime(public_path('assets/js/delivery-quote.js')) }}"></script>
@endif
@endsection
