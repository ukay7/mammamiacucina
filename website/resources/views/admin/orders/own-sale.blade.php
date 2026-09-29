@extends('admin.layout')
@section('title','Order '.$order->number)
@section('content')
<p><a class="btn btn-outline-primary" href="{{ route('admin.orders.completed') }}">Back to Orders</a> <a class="btn btn-primary" href="{{ route('admin.orders.print',$order) }}" target="_blank" rel="noopener">Print Receipt</a></p>
<div class="panel"><div class="panel-content"><p>Created by you · Quick Sale / POS · {{ $order->status_label }}</p><p>{{ $order->first_name }} {{ $order->last_name }} · {{ $order->fulfillment==='pickup'?'Pickup':'Delivery' }}</p>@if($order->fulfillment==='delivery')<p>{{ $order->address }}, {{ $order->city }}, {{ $order->province }} {{ $order->postal_code }}</p>@endif
@include('partials.order-lines')
</div></div>
@endsection
