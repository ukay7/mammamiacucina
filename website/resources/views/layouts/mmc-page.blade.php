@extends('layouts.theme')
@section('title', $pageTitle.' | Mamma Mia Cucina')
@section('body-class', 'mmc-home mmc-inner')
@section('content')
<div class="mmc-inner-header">@include('partials.mmc-header')</div>
<section class="mmc-page-banner"><div><p>MAMMA MIA CUCINA</p><h1>{{ $pageTitle }}</h1><nav aria-label="Breadcrumb"><a href="{{ route('theme.index') }}">Home</a><span aria-hidden="true"> / </span><span>{{ $pageTitle }}</span></nav></div></section>
<main class="mmc-page-content">@yield('page-content')</main>
@include('partials.mmc-footer')
<div id="back2top"><i class="fa fa-angle-up"></i></div>
@endsection
@push('styles')
@foreach (['mmc-home','mmc-about-video','mmc-pages'] as $sheet)
<link rel="stylesheet" href="{{ asset('assets/css/'.$sheet.'.css') }}?v={{ filemtime(public_path('assets/css/'.$sheet.'.css')) }}">
@endforeach
@endpush
@push('scripts')
<script src="{{ asset('assets/js/mmc-menu.js') }}?v={{ filemtime(public_path('assets/js/mmc-menu.js')) }}"></script>
@include('partials.mmc-shop-data')
@endpush
