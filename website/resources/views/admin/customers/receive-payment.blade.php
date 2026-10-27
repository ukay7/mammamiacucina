@extends('admin.layout')
@section('title','Receive Customer Payment')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/customer-receipts.css') }}?v={{ filemtime(public_path('admin-assets/customer-receipts.css')) }}">
<section class="panel panel-content">
    <div class="receipt-toolbar"><div><h2>{{ $customer->business_name ?: $customer->name }}</h2><p>Customer #{{ $customer->id }} · {{ $customer->name }} · {{ $customer->email }}</p></div><a class="btn btn-outline-primary" href="{{ route('admin.customers.show',$customer) }}#customer-payments">Back to Payments &amp; Cheques</a></div>
    <div class="receipt-metrics">
        <div><span>Current outstanding (CAD)</span><strong>${{ number_format($account['outstanding_cents']/100,2) }}</strong></div>
        <div><span>Maximum new payment (CAD)</span><strong>${{ number_format($account['maximum_cents']/100,2) }}</strong></div>
        <div><span>Cheques awaiting clearance</span><strong>${{ number_format($account['pending_cheques_cents']/100,2) }}</strong></div>
        <div><span>Eligible unpaid orders</span><strong>{{ $account['unpaid_orders'] }}</strong><small>{{ $account['overdue_orders'] }} overdue by recorded due date</small></div>
    </div>
    <p class="text-muted">The maximum is eligible outstanding less cheques already awaiting clearance. Cancelled/deleted orders, unfinished totals and orders with online-payment records are excluded. Customer advances are not supported.</p>
</section>
<form class="panel panel-content" method="post" action="{{ route('admin.customers.receipts.store',$customer) }}" data-customer-receipt data-maximum="{{ $account['maximum_cents'] }}" data-preview-url="{{ route('admin.customers.receipts.preview',$customer) }}" data-store-url="{{ route('admin.customers.receipts.store',$customer) }}">
    @csrf
    <input type="hidden" name="request_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
    <div class="receipt-toolbar"><h2>Payment methods</h2><button class="btn btn-outline-primary" type="button" data-receipt-add>Add Payment Method</button></div>
    <p>Record actual cash or externally verified funds. Cheques are recorded as <strong>Awaiting clearance</strong> and will not reduce balances until Super Admin confirms bank clearance.</p>
    <div data-receipt-lines></div>
    <p class="h4">Total entered: <span data-receipt-total>CAD 0.00</span></p>
    <div class="alert alert-danger" data-receipt-error hidden role="alert"></div>
    <button class="btn btn-primary" type="button" data-receipt-preview>Preview Allocation</button>
    <section class="receipt-preview" data-receipt-result hidden aria-live="polite">
        <h2>Allocation preview</h2><div data-receipt-preview-summary class="receipt-metrics"></div>
        <p class="text-muted">Oldest outstanding order first. Pending cheque allocations are proposals; current balances are checked again at clearance.</p>
        <div class="table-responsive"><table class="table"><thead><tr><th>Order / invoice</th><th>Balance before</th><th>Payment allocations</th><th>Due after confirmed funds</th><th>Due if cheques clear</th></tr></thead><tbody data-receipt-preview-rows></tbody></table></div>
        <label class="d-block my-3"><input type="checkbox" name="confirmed" value="1" data-receipt-confirmed> I confirm these details and verified funds received. Cheques remain awaiting clearance.</label>
        <button class="btn btn-primary" type="submit" data-receipt-save disabled>Confirm &amp; Record Payment</button>
    </section>
</form>
<template id="customer-receipt-line-template">
    <fieldset class="receipt-line">
        <div class="receipt-toolbar"><legend data-receipt-line-title>Payment</legend><button class="btn btn-outline-danger btn-sm" type="button" data-receipt-remove>Remove</button></div>
        <input type="hidden" data-receipt-field="key">
        <div class="receipt-fields">
            <div class="form-group"><label data-receipt-label="method">Payment method</label><select class="form-control" data-receipt-field="method">@foreach(\App\Models\CustomerReceiptLine::METHODS as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
            <div class="form-group"><label data-receipt-label="amount">Amount (CAD)</label><input class="form-control" data-receipt-field="amount" inputmode="decimal" placeholder="0.00" maxlength="10" required></div>
            <div class="form-group"><label data-receipt-label="payment_date">Payment / received date</label><input class="form-control" data-receipt-field="payment_date" type="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
            <div class="form-group"><label data-receipt-label="reference">Reference / cheque number</label><input class="form-control" data-receipt-field="reference" maxlength="150"></div>
            <div class="form-group" data-cheque-only hidden><label data-receipt-label="cheque_date">Cheque date</label><input class="form-control" data-receipt-field="cheque_date" type="date"></div>
            <div class="form-group" data-cheque-only hidden><label data-receipt-label="bank">Bank (optional)</label><input class="form-control" data-receipt-field="bank" maxlength="150"></div>
            <div class="form-group receipt-wide"><label data-receipt-label="proof">Supporting image / PDF (up to 5 MB)</label><input class="form-control" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" data-receipt-field="proof"><small>E-transfer proof is required. Bank receipt or cheque photo can also be attached.</small></div>
            <div class="form-group receipt-wide"><label data-receipt-label="note">Notes (optional)</label><textarea class="form-control" data-receipt-field="note" rows="2" maxlength="2000"></textarea></div>
            <label class="receipt-wide" data-verified-only><input type="checkbox" value="1" data-receipt-field="verified"> I verified this money was actually received; an uploaded screenshot alone is not confirmation.</label>
        </div>
    </fieldset>
</template>
<script defer src="{{ asset('admin-assets/customer-receipts.js') }}?v={{ filemtime(public_path('admin-assets/customer-receipts.js')) }}"></script>
@endsection
