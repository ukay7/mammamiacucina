@extends('admin.layout')
@section('title','Pack Order '.$order->number)
@section('content')
@include('partials.order-delivery')
<link rel="stylesheet" href="{{ asset('admin-assets/warehouse.css') }}?v={{ filemtime(public_path('admin-assets/warehouse.css')) }}">
<p><a class="btn btn-outline-primary" href="{{ route('admin.orders.index') }}">Back to Orders</a> <a class="btn btn-outline-primary" href="{{ route('admin.orders.print',$order) }}" target="_blank" rel="noopener">Print Packing Slip</a></p>
<div class="panel"><div class="panel-content warehouse-customer"><div><h3>Customer</h3><strong>{{ $order->first_name }} {{ $order->last_name }}</strong><p>{{ $order->phone }}<br>{{ $order->email }}</p></div><div><h3>{{ $order->fulfillment==='pickup'?'Pick up':'Delivery address' }}</h3>@if($order->fulfillment==='pickup')<p>Pick up in store</p>@else<p>{{ $order->address }}<br>{{ $order->city }}, {{ $order->province }} {{ $order->postal_code }}<br>{{ $order->country }}</p>@endif</div><div><h3>Order details</h3><p>{{ $order->number }}<br>{{ $order->created_at->format('d M Y, H:i') }}<br>{{ $order->status_label }}</p></div>@if($order->notes)<div class="warehouse-customer-notes"><h3>Customer instructions</h3>{{ $order->notes }}</div>@endif</div></div>
@include('admin.orders.packing')
@endsection
