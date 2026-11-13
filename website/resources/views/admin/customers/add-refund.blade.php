@extends('admin.layout')
@section('title','Add Customer Refund')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/customer-receipts.css') }}">
<link rel="stylesheet" href="{{ asset('admin-assets/vendor/select2/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('admin-assets/customer-refunds.css') }}?v={{ filemtime(public_path('admin-assets/customer-refunds.css')) }}">
<section class="panel panel-content">
<div class="receipt-toolbar"><div><h2>{{ $customer->business_name ?: $customer->name }}</h2><p>Customer #{{ $customer->id }} · {{ $customer->name }} · {{ $customer->email }}</p></div><a class="btn btn-outline-primary" href="{{ route('admin.customers.show',$customer) }}#customer-refunds">Back to Refunds</a></div>
<p>Record money returned for a delivered or completed order. A matching credit note keeps the order balance unchanged. The original invoice and delivered items remain in the history.</p>
</section>
<form class="panel panel-content" data-refund-form action="{{ route('admin.customers.refunds.store',$customer) }}" data-preview-url="{{ route('admin.customers.refunds.preview',$customer) }}" data-today="{{ \App\Support\BusinessTime::today()->toDateString() }}" method="post" enctype="multipart/form-data">
@csrf <input type="hidden" name="request_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<label for="refund-order">Order number <span class="text-danger">*</span></label>
<select class="form-control" id="refund-order" name="order_id" required data-search-url="{{ route('admin.customers.refunds.orders',$customer) }}"><option value="">Search delivered / completed order number</option>@if($order)<option value="{{ $order->id }}" selected>{{ mb_strtoupper($order->number) }}</option>@endif</select>
<script type="application/json" id="refund-initial-account">@json($account)</script>
<div class="receipt-metrics" data-refund-account aria-live="polite"></div>
<label for="refund-reason">Reason for refund <span class="text-danger">*</span></label><textarea id="refund-reason" name="reason" class="form-control mb-4" maxlength="2000" rows="2" required placeholder="Explain what was agreed with the customer"></textarea>
<div class="receipt-toolbar"><h2>Refund methods</h2><button type="button" class="btn btn-outline-primary" data-refund-add>Add Refund Method</button></div>
<p>This records an external payout; it does not send money. Cheques reserve the amount and remain <strong>Awaiting clearance</strong> until bank clearance is confirmed.</p>
<div data-refund-lines></div>
<p class="h4">Total entered: <span data-refund-total>CAD 0.00</span></p>
<p class="text-muted">Included GST/HST is suggested proportionally from the original order. Check it against the items being refunded. Amounts include this tax.</p>
<div class="alert alert-danger" data-refund-error role="alert" hidden></div>
<button type="button" class="btn btn-primary" data-refund-preview>Review Refund</button>
<noscript><p>Enable JavaScript to search orders and review the refund before recording it.</p></noscript>
</form>
<template id="refund-line-template"><fieldset class="receipt-line">
<div class="receipt-toolbar"><legend>Refund method</legend><button class="btn btn-outline-danger btn-sm" type="button" data-refund-remove>Remove</button></div><input type="hidden" data-field="key">
<div class="receipt-fields">
<label>Method<select class="form-control" data-field="method">@foreach(\App\Models\CustomerRefundLine::METHODS as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
<label>Refund amount, including tax (CAD)<input class="form-control" type="number" min="0.01" max="9999999.99" step="0.01" data-field="amount" required></label>
<label>Included GST/HST (CAD)<input class="form-control" type="number" min="0" step="0.01" value="0.00" data-field="tax" required></label>
<label>Refund / cheque issued date (Toronto)<input class="form-control" type="date" data-field="refund_date" required></label>
<label data-reference-label>Transaction reference / cheque number<input class="form-control" data-field="reference" maxlength="150"></label>
<label data-cheque>Cheque date<input class="form-control" type="date" data-field="cheque_date"></label>
<label data-cheque>Bank (optional)<input class="form-control" data-field="bank" maxlength="150"></label>
<label>Supporting image / PDF <small data-proof-hint></small><input class="form-control" type="file" data-field="proof" accept="image/jpeg,image/png,image/webp,application/pdf"><small>JPG, PNG, WebP or PDF; up to 5 MB each, 20 MB total.</small></label>
<label class="receipt-wide">Notes (optional)<textarea class="form-control" data-field="note" maxlength="2000" rows="2"></textarea></label>
</div>
<label class="d-block mt-3" data-verified-label><input type="checkbox" data-field="verified" value="1"> I confirm this money has actually been returned to the customer.</label>
<p data-cheque class="receipt-pending">This cheque is pending. No refund or credit posts until bank clearance is confirmed.</p>
</fieldset></template>
@include('admin.customers.refund-review')
<script src="{{ asset('admin-assets/vendor/select2/select2.full.min.js') }}" defer></script>
<script src="{{ asset('admin-assets/customer-refunds.js') }}?v={{ filemtime(public_path('admin-assets/customer-refunds.js')) }}" defer></script>
@endsection
