@extends('layouts.mmc-page',['pageTitle'=>'Your Cart'])
@section('page-content')<p role="status" data-cart-feedback>{{ session('cart_status') }}</p><div data-live-cart-content>@include('partials.live-cart')</div>@endsection
