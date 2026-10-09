@extends('admin.layout')
@section('title','Order '.$order->number)
@section('content')
<p><a class="btn btn-outline-primary" href="{{ route('admin.pos.index') }}">Back to Quick Sale</a> <a class="btn btn-outline-primary" href="{{ route('admin.pos.receipt',$order) }}" target="_blank" rel="noopener">Print receipt / PDF</a></p>
<div class="panel panel-content"><h2>{{ $order->number }} · {{ $order->status_label }}</h2><p>{{ $order->first_name }} {{ $order->last_name }} · {{ $order->payment_label }} · {{ ucfirst($order->payment_status) }}</p>@include('partials.order-lines')</div>
@include('partials.transfer-receipt')
@if(auth()->user()->hasAdminPermission('warehouse.pack'))
@include('admin.orders.packing')
@endif
@endsection
