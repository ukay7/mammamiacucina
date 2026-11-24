@extends('layouts.mmc-page', ['pageTitle'=>'Checkout'])
@section('page-content')
<nav class="mmc-shopping-steps"><a href="{{ route('theme.cart') }}">01 · Cart</a><strong>02 · Checkout</strong><span>03 · Confirmation</span></nav>
@include('partials.checkout-form')
@endsection
