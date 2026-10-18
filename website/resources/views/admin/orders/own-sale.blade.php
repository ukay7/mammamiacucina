@extends('admin.layout')
@section('title','Order '.$order->number)
@section('hide-page-heading','1')
@section('page-class','warehouse-order-page warehouse-detail-page')
@section('content')
@include('admin.orders.detail-header',['receiptView'=>true])
<p class="wh-readonly-note">Created by you · Quick Sale / POS · {{ $order->status_label }}</p>
@include('admin.orders.packing')
<details class="wh-route-detail"><summary>Receipt totals</summary><div class="wh-receipt-totals">@include('partials.order-lines')</div></details>
@include('partials.transfer-receipt')
@endsection