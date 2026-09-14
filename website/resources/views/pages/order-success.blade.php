@extends('layouts.mmc-page',['pageTitle'=>'Order Successful'])
@section('page-content')
<section class="mmc-panel"><div class="mmc-success-icon" aria-hidden="true">✓</div><h2>Thank you, {{ $order->first_name }}!</h2><p>Your order has been placed.</p><p><strong>Order number: {{ $order->number }}</strong></p>
@include('partials.order-lines')
<h3>Delivery details</h3><p>{{ $order->first_name }} {{ $order->last_name }}<br>{{ $order->address }}<br>{{ $order->city }}, {{ $order->province }} {{ $order->postal_code }}<br>{{ $order->country }}</p><p>{{ $order->email }} · {{ $order->phone }}</p>
<a class="mmc-button" href="{{ route('theme.product-grid') }}">Continue Shopping</a></section>
@endsection
