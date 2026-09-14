@extends('layouts.theme')
@section('title', 'Bakery | 404 Pages')
@section('body-class', 'page-init')
@section('content')

    <div id="back2top"><i class="fa fa-angle-up"></i></div>
    <div class="loader"></div>
    <div class="page-wrap">
      <div class="ps-error bg--cover" data-background="{{ asset('assets/images/background/img-404.jpg') }}">
        <div class="ps-error__content text-center">
          <h1>404</h1>
          <h3>PAGES NOT FOUND</h3>
          <p>OOPS ! Seems you're go out range our Bakery ! :(</p><a class="ps-btn" href="{{ route('theme.index') }}">Back to home<i class="fa fa-angle-right"></i></a>
        </div>
      </div>
    </div>

@endsection
