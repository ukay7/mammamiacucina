@extends('layouts.mmc-page',['pageTitle'=>'Verify your email'])
@section('page-content')
<div class="mmc-panel" style="max-width:650px;margin:auto">
<h2>Check your email</h2><p>Open the verification link sent to {{ auth()->user()->email }}. After verification, you can continue checkout with your saved cart.</p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="mmc-error-alert" role="alert">{{ $errors->first() }}</p>@endif
<form method="post" action="{{ route('customer.verify.resend') }}">@csrf<button class="mmc-button">Resend verification email</button></form>
<form method="post" action="{{ route('customer.logout') }}">@csrf<button class="mmc-button">Sign out</button></form>
</div>
@endsection
