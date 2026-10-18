@extends('admin.layout')
@section('title','Pack Order '.$order->number)
@section('hide-page-heading','1')
@section('page-class','warehouse-order-page warehouse-detail-page')
@section('content')
@include('admin.orders.detail-header')
@include('admin.orders.packing')
@endsection