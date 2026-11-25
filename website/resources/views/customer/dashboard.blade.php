@extends('admin.layout')
@section('title', 'My Account')
@include('customer.account-style')
@section('content')
@php($money = fn ($cents) => 'CAD $'.number_format($cents / 100, 2))
<section class="panel panel-content account-hero">
    <div><p class="account-eyebrow">Customer #{{ $customer->id }}</p><h2>{{ $customer->business_name ?: $customer->name }}</h2>
        @if($customer->business_name)<p>{{ $customer->name }}</p>@endif
    </div>
    <div class="account-actions">
        <a class="btn btn-outline-primary" href="{{ route('customer.statement') }}">Download statement</a>
        <a class="btn btn-primary" href="{{ route('customer.catalogue') }}">Browse Product Catalogue</a>
    </div>
</section>
<div class="account-grid account-metrics">
    <a class="panel account-tile" href="{{ route('customer.invoices') }}"><span>Outstanding invoices</span><strong>{{ $money($outstanding) }}</strong><small>View invoices and make a payment</small></a>
    <a class="panel account-tile" href="{{ route('customer.credit') }}"><span>Available credit</span><strong>{{ $money($credit['available']) }}</strong><small>View your credit and discount</small></a>
    <a class="panel account-tile" href="{{ route('customer.receipts') }}"><span>Payment records</span><strong>Receipts</strong><small>View and download your receipts</small></a>
    <a class="panel account-tile" href="{{ route('customer.support.index') }}"><span>Need help?</span><strong>Customer Service</strong><small>Start or view a conversation</small></a>
</div>
<section class="panel panel-content">
    <div class="account-section-heading"><h2>Recent orders</h2><a href="{{ route('customer.orders') }}">View all orders</a></div>
    <div class="table-responsive"><table class="table account-table">
        <thead><tr><th scope="col">Order</th><th scope="col">Date</th><th scope="col">Status</th><th scope="col" class="text-right">Total</th></tr></thead>
        <tbody>@forelse($orders as $order)
            <tr><td><a href="{{ route('customer.order', $order) }}">{{ mb_strtoupper($order->number) }}</a></td><td>{{ \App\Support\BusinessTime::format($order->ordered_at, 'd M Y') }}</td>
                <td>{{ $order->status_label }}</td><td class="text-right">{{ $order->final_total_cents !== null ? $money($order->final_total_cents) : 'Pending' }}</td></tr>
        @empty<tr><td colspan="4">Your orders will appear here after you place one.</td></tr>@endforelse</tbody>
    </table></div>
</section>
@endsection
