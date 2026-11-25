@extends('admin.layout')
@section('title', 'Credit & Discounts')
@include('customer.account-style')
@section('content')
@php($money = fn ($cents) => 'CAD $'.number_format($cents / 100, 2))
<div class="account-grid account-metrics">
    <section class="panel account-tile"><span>Approved credit limit</span><strong>{{ $money($customer->credit_limit_cents) }}</strong>
        @if($customer->overage_cents)<small>Additional allowance {{ $money($customer->overage_cents) }}</small>@endif
    </section>
    <section class="panel account-tile"><span>Credit used</span><strong>{{ $money($credit['used']) }}</strong><small>Outstanding orders placed on credit</small></section>
    <section class="panel account-tile"><span>Available credit</span><strong>{{ $money($credit['available']) }}</strong></section>
    <section class="panel account-tile"><span>General order discount</span><strong>{{ $customer->order_discount_bps / 100 }}%</strong><small>Applied to eligible product subtotals</small></section>
</div>
@if($customer->credit_limit_cents > 0 && $credit['allowance'] > 0)
    @php($utilization = $credit['used'] * 100 / $credit['allowance'])
    @php($approaching = $utilization >= 80)
    <section class="panel panel-content account-credit-utilization">
        <div class="account-section-heading"><h2 id="credit-utilization-title">Credit utilization</h2><strong>{{ number_format(floor($utilization * 10) / 10, 1) }}% used</strong></div>
        <progress class="account-credit-progress{{ $approaching ? ' is-near-limit' : '' }}" aria-labelledby="credit-utilization-title"
                  aria-valuetext="{{ $money($credit['used']) }} used out of {{ $money($credit['allowance']) }}"
                  value="{{ min($credit['used'], $credit['allowance']) }}" max="{{ $credit['allowance'] }}"></progress>
        <p class="mb-0">{{ $money($credit['used']) }} used out of {{ $money($credit['allowance']) }}.</p>
        @if($approaching)
            <p class="account-profile-notice mb-0 mt-3">{{ $utilization >= 100 ? 'You have reached your credit limit.' : 'You are approaching your credit limit.' }}</p>
        @endif
    </section>
@endif
<section class="panel panel-content">
    <div class="account-section-heading">
        <div><h2>Credit applications</h2><p class="mb-0">{{ $application ? 'Latest application '.$application->reference.' · '.$application->status_label : 'Apply for credit and review the personal guarantee.' }}</p></div>
        <a class="btn btn-primary" href="{{ route('customer.credit-applications.index') }}">{{ $application ? 'Manage applications' : 'Apply for credit' }}</a>
    </div>
</section>
<div class="account-actions">
    <a class="btn btn-primary" href="{{ route('customer.invoices') }}">View invoices</a>
    <a class="btn btn-outline-primary" href="{{ route('customer.statement') }}">Download statement</a>
    <a class="btn btn-outline-primary" href="{{ route('customer.support.index') }}">Contact Customer Service</a>
</div>
@endsection
