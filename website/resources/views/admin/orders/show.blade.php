@extends('admin.layout')
@section('title','Order '.$order->number)
@section('content')
<p><a class="btn btn-outline-primary" href="{{ route('admin.orders.index') }}">← Back to Orders</a></p>
<div class="panel"><div class="panel-container show"><div class="panel-content"><p>Placed {{ $order->created_at->format('d M Y H:i') }} · {{ ucfirst($order->status) }}</p><h2>Customer & Delivery</h2><p>{{ $order->first_name }} {{ $order->last_name }}<br>{{ $order->email }}<br>{{ $order->phone }}</p><p>{{ $order->address }}<br>{{ $order->city }}, {{ $order->province }} {{ $order->postal_code }}<br>{{ $order->country }}</p>@if($order->notes)<h3>Order notes</h3><p style="white-space:pre-wrap">{{ $order->notes }}</p>@endif<h2>Products</h2>@include('partials.order-lines')</div></div></div>
@endsection
