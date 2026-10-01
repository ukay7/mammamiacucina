@extends('layouts.mmc-page',['pageTitle'=>$reset?'Reset password':'Forgot password'])
@section('page-content')
<div class="mmc-panel mmc-form" style="max-width:600px;margin:auto">
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="mmc-error-alert" role="alert">{{ $errors->first() }}</div>@endif
<p>{{ $reset?'Choose a new password for your account.':'Enter your account email and we will send you a password reset link.' }}</p>
<form method="post" action="{{ route($reset?'customer.password.update':'customer.password.send') }}">@csrf
@if($reset)<input type="hidden" name="token" value="{{ $token }}">@endif
<label for="reset-email">Email address</label><input class="form-control" id="reset-email" type="email" name="email" value="{{ old('email',$email??'') }}" required autocomplete="email">
@if($reset)<label for="new-password">New password (at least 10 characters)</label><input class="form-control" id="new-password" type="password" name="password" minlength="10" required autocomplete="new-password"><label for="confirm-password">Confirm password</label><input class="form-control" id="confirm-password" type="password" name="password_confirmation" required autocomplete="new-password">@endif
<button class="mmc-button" type="submit">{{ $reset?'Save password':'Send reset link' }}</button>
</form><p><a href="{{ route('customer.login') }}">Back to login</a></p></div>
@endsection
