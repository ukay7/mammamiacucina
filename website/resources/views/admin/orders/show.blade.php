@extends('admin.layout')
@section('title','Order '.$order->number)
@section('content')
@include('partials.transfer-receipt')
@include('admin.orders.payment')
@include('admin.orders.packing')
{{-- Payment adjustments panel hidden at the user's request. --}}

<p><a class="btn btn-outline-primary" href="{{ route('admin.orders.index') }}">← Back to Orders</a> <a class="btn btn-primary" href="{{ route('admin.orders.print',$order) }}" target="_blank" rel="noopener">Print / Save as PDF</a></p>
<div class="panel"><div class="panel-container show"><div class="panel-content"><p>Placed {{ $order->created_at->format('d M Y H:i') }} · {{ $order->status_label }}</p><h2>{{ $order->fulfillment === 'pickup' ? 'Customer · Pick up' : 'Customer & Delivery' }}</h2><p>{{ $order->first_name }} {{ $order->last_name }}<br>{{ $order->email }}<br>{{ $order->phone }}</p>@if($order->fulfillment !== 'pickup')<p>{{ $order->address }}<br>{{ $order->city }}, {{ $order->province }} {{ $order->postal_code }}<br>{{ $order->country }}</p>@endif @if($order->notes)<h3>Order notes</h3><p style="white-space:pre-wrap">{{ $order->notes }}</p>@endif
@if(auth()->user()->hasAdminPermission('orders.manage') && !in_array($order->status,['cancelled','completed','delivered','out_for_delivery','awaiting_payment','payment_review']))
@if($order->fulfillment === 'pickup' && $order->pickup_address)<p style="white-space:pre-line"><strong>Pickup address</strong><br>{{ $order->pickup_address }}</p>@endif
@include('partials.order-delivery')
@endif
<h2>Products</h2>@include('admin.orders.amend')</div></div></div>
@if(auth()->user()->hasAdminPermission('orders.manage'))
<div class="panel"><div class="panel-container show"><div class="panel-content"><h2>Manage Order</h2><form method="post" action="{{ route('admin.orders.update',$order) }}">@csrf @method('PATCH')@if($order->payment)<input type="hidden" name="payment_status" value="{{ $order->payment_status }}">@endif<input type="hidden" name="revision" value="{{ $order->revision }}">
<div class="row"><div class="col-md-6"><label>Status</label><select class="form-control" name="status">@foreach(($order->status_options + (!$order->payment && !in_array($order->status,['completed','delivered','out_for_delivery','cancelled']) ? ['cancelled'=>'Cancelled'] : [])) as $key=>$label)<option value="{{ $key }}" @selected(old('status',$order->status)===$key)>{{ $label }}</option>@endforeach</select></div><div class="col-md-6"><label>Payment status</label><select class="form-control" name="payment_status" @if($order->payment) disabled @endif>@foreach(($order->payment?['pending'=>'Pending gateway confirmation','partially_refunded'=>'Partially refunded']:[])+['unpaid'=>'Unpaid','paid'=>'Paid — payment received','refunded'=>'Refunded — payment returned (cancellation)'] as $key=>$label)<option value="{{ $key }}" @selected(old('payment_status',$order->payment_status)===$key)>{{ $label }}</option>@endforeach</select></div></div>
<div class="row mt-3"><div class="col-md-6"><label>Delivery charge (CAD)</label><input class="form-control" type="number" min="0" max="9999999.99" step="0.01" name="delivery" @readonly($order->payment || $order->delivery_service) value="{{ old('delivery',$order->delivery_cents===null?'':number_format($order->delivery_cents/100,2,'.','')) }}"></div><div class="col-md-6"><label>Tax amount (CAD)</label><input class="form-control" type="number" min="0" max="9999999.99" step="0.01" name="tax" @readonly($order->payment) value="{{ old('tax',$order->tax_cents===null?'':number_format($order->tax_cents/100,2,'.','')) }}"></div></div><p class="mt-2">Leave charges blank until confirmed; enter 0 for no charge. Mark Paid only after payment is received. Cancellation restores deducted stock once. Return collected payment before cancelling a paid order.</p><label>Internal change note (optional)</label><textarea class="form-control" name="reason" maxlength="1000">{{ old('reason') }}</textarea><button class="btn btn-primary mt-3" type="submit">Save Order</button></form></div></div></div>
@endif
<div class="panel"><div class="panel-container show"><div class="panel-content"><h2>Order History</h2>@forelse($order->events as $event)<p><strong>{{ $event->created_at->format('d M Y H:i') }} · {{ $event->author?->name ?? 'System' }}</strong><br>{{ $event->description }}</p>@empty<p>No changes recorded yet.</p>@endforelse</div></div></div>
@endsection
