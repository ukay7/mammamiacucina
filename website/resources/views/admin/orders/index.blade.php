@extends('admin.layout')
@section('title','Orders')
@section('content')
<form method="get" class="mmc-filters mmc-order-filters"><label>Search orders<input class="form-control" name="q" value="{{ $search }}" placeholder="Order number, customer or email"></label><label>From date<input class="form-control" type="date" name="from" value="{{ $from }}" required></label><label>To date<input class="form-control" type="date" name="to" value="{{ $to }}" required></label><button class="btn btn-primary">Filter</button><a href="{{ route('admin.orders.index') }}">Reset</a></form>
<div class="panel"><div class="panel-container show"><div class="panel-content table-responsive"><table class="table"><thead><tr><th>Order / Date</th><th>Customer</th><th>Product subtotal (CAD)</th><th>Status</th><th>Payment</th><th></th></tr></thead><tbody>@forelse($orders as $order)<tr><td>{{ $order->number }}@if($order->source === 'pos')<small class="d-block">POS · {{ $order->fulfillment === 'pickup' ? 'Collected in store' : 'Delivery' }}</small>@endif<small class="d-block">{{ $order->created_at->format('d M Y H:i') }}</small></td><td>{{ $order->first_name }} {{ $order->last_name }}<small class="d-block">{{ $order->email }}</small></td><td>${{ number_format($order->subtotal_cents/100,2) }}</td><td>
@if(auth()->user()->hasAdminPermission('orders.manage') && !in_array($order->status,['delivered','cancelled']))
@php
$steps=['placed','confirmed','preparing','out_for_delivery','delivered'];
$next=$steps[array_search($order->status,$steps,true)+1]??null;
@endphp
<form class="mmc-grid-status" data-status-autosave data-saved-status="{{ $order->status }}" method="post" action="{{ route('admin.orders.status',$order) }}">
@csrf @method('PATCH')<input type="hidden" name="revision" value="{{ $order->revision }}"><input type="hidden" name="q" value="{{ $search }}"><input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}"><input type="hidden" name="page" value="{{ $orders->currentPage() }}">
<select class="form-control mmc-status-pill" data-status="{{ $order->status }}" name="status" aria-label="Status for {{ $order->number }}">
<option value="{{ $order->status }}">{{ $order->status_label }}</option>
@if($next)<option value="{{ $next }}">{{ \App\Models\Order::STATUSES[$next] }}</option>@endif
@if($order->payment_status==='unpaid')<option value="cancelled">Cancelled</option>@endif
</select><small class="mmc-status-feedback" role="status" aria-live="polite"></small><noscript><button class="btn btn-primary btn-sm" type="submit">Update</button></noscript></form>
@else<span class="mmc-status-pill" data-status="{{ $order->status }}">{{ $order->status_label }}</span>@endif
</td><td>{{ ucfirst($order->payment_method) }} · {{ ucfirst($order->payment_status) }}</td><td><div class="mmc-order-row-actions"><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.orders.show',$order) }}">View</a><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.orders.print',$order) }}" target="_blank" rel="noopener" aria-label="Print order {{ $order->number }}"><i class="fas fa-print" aria-hidden="true"></i> Print</a></div></td></tr>@empty<tr><td colspan="6">No orders found for the selected dates and search.</td></tr>@endforelse</tbody></table>{{ $orders->links() }}</div></div></div>
@endsection
