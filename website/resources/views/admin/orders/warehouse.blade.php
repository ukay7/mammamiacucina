@extends('admin.layout')
@section('title','Pack Order '.$order->number)
@section('hide-page-heading','1')
@section('page-class','warehouse-order-page warehouse-detail-page')
@section('content')
@include('admin.orders.detail-header')
@if($order->awaitingWarehouseRelease())
    <p class="alert alert-warning">This order has been received. Wait for it to be released to packing.</p>
@endif
@include('admin.orders.packing')
@endsection
