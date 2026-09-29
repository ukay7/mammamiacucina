@extends('layouts.mmc-page',['pageTitle'=>$register?'Create an account':'Customer login'])
@section('page-content')
<div class="mmc-panel mmc-form" style="max-width:600px;margin:auto">
<p>{{ $register?'Create your account to continue checkout.':'Log in to continue checkout or view your orders.' }}</p>
@if($errors->any())<div class="mmc-error-alert" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif <form method="post" action="{{ route($register?'customer.register.store':'customer.login.store') }}">@csrf
@if($register)
<label for="name">Full name</label><input class="form-control" id="name" name="name" autocomplete="name" maxlength="100" value="{{ old('name') }}" required>
@endif
<label for="email">Email address</label><input class="form-control" type="email" id="email" name="email" autocomplete="email" maxlength="255" value="{{ old('email') }}" required>
@if($register)
<label for="phone">Phone number</label><input class="form-control" type="tel" id="phone" name="phone" autocomplete="tel" maxlength="40" value="{{ old('phone') }}" required>
<label for="account_type">Account type</label><select class="form-control" id="account_type" name="account_type"><option value="individual" @selected(old('account_type')==='individual')>Individual</option><option value="business" @selected(old('account_type')==='business')>Business owner</option></select>
@endif
<label for="password">Password{{ $register?' (at least 10 characters)':'' }}</label><input class="form-control" type="password" id="password" name="password" autocomplete="{{ $register?'new-password':'current-password' }}" required>
@if($register)<label for="password_confirmation">Confirm password</label><input class="form-control" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>@endif
<button class="mmc-button" type="submit">{{ $register?'Create account':'Log in' }}</button>
</form>@if(!$register)<p><a href="{{ route('customer.password.forgot') }}">Forgot password?</a></p>@endif
<p><a href="{{ route($register?'customer.login':'customer.register') }}">{{ $register?'Already have an account? Log in':'New customer? Create an account' }}</a></p>
</div>
@endsection
