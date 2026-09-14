@extends('layouts.theme')
@section('title', 'Bakery | Pages Listing')
@section('body-class', 'page-init')
@section('content')

    <!--include ../partials/module_header-->
    <div id="back2top"><i class="fa fa-angle-up"></i></div>
    <div class="loader"></div>
    <div class="page-wrap">
      <section class="ps-section">
        <div class="container">
          <div class="jumbotron">
            <div class="text-center"><img src="{{ asset('assets/images/logo-1.png') }}" alt="" style="max-width: 100px">
              <h3 class="mt-30">Pages Listing</h3>
            </div>
            <ol style="list-style: decimal;">
              <li><a href="{{ route('theme.404') }}" target="_blank">404 Page</a></li>
              <li><a href="{{ route('theme.about') }}" target="_blank">About</a></li>
              <li><a href="{{ route('theme.blog-detail') }}" target="_blank">Blog Detail</a></li>
              <li><a href="{{ route('theme.blog-listing') }}" target="_blank">Blog Listing</a></li>
              <li><a href="{{ route('theme.cart') }}" target="_blank">Cart</a></li>
              <li><a href="{{ route('theme.checkout') }}" target="_blank">Checkout</a></li>
              <li><a href="{{ route('theme.contact') }}" target="_blank">Contact</a></li>
              <li><a href="{{ route('theme.contact-2') }}" target="_blank">Contact 2</a></li>
              <li><a href="{{ route('theme.index') }}" target="_blank">Homepage</a></li>
              <li><a href="{{ route('theme.homepage-2') }}" target="_blank">Homepage 2</a></li>
              <li><a href="{{ route('theme.homepage-3') }}" target="_blank">Homepage 3</a></li>
              <li><a href="{{ route('theme.product-detail') }}" target="_blank">Product Detail</a></li>
              <li><a href="{{ route('theme.product-grid') }}" target="_blank">Product Grid</a></li>
              <li><a href="{{ route('theme.product-listing') }}" target="_blank">Product List</a></li>
              <li><a href="{{ route('theme.order-form') }}" target="_blank">Order Form</a></li>
              <li><a href="{{ route('theme.menu-1') }}" target="_blank">Menu 1</a></li>
              <li><a href="{{ route('theme.menu-2') }}" target="_blank">Menu 2</a></li>
            </ol>
            <h4>Additional theme pages</h4>
            <ul>
              <li><a href="{{ route('theme.action-mobile') }}">Mobile Action</a></li>
              <li><a href="{{ route('theme.compare') }}">Compare</a></li>
              <li><a href="{{ route('theme.whist-list') }}">Wishlist</a></li>
            </ul>
          </div>
        </div>
      </section>
    </div>

@endsection
