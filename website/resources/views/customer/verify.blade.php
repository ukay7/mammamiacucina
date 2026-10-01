@extends('layouts.mmc-page',['pageTitle'=>'Verify your email'])
@section('page-content')
<div class="mmc-panel" style="max-width:650px;margin:auto">
<h2>Check your email</h2><p>Open the verification link sent to {{ auth()->user()->email }}. @if(auth()->user()->businessApprovalPending()) Email verification and administrator approval are both required before you can place orders. @else After verification, you can continue shopping with your saved cart. @endif</p>
@if(auth()->user()->businessApprovalPending())<p>Your business account is under process. Kindly wait or contact system administration.</p>
@include('partials.business-approval-contact')
@endif
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="mmc-error-alert" role="alert">{{ $errors->first() }}</p>@endif
<form method="post" action="{{ route('customer.verify.resend') }}">@csrf @include('partials.verification-resend-button',['verificationUser'=>auth()->user(),'buttonClass'=>'mmc-button'])</form>
<form method="post" action="{{ route('customer.logout') }}">@csrf<button class="mmc-button">Sign out</button></form>
</div>
@endsection
