@extends('admin.layout')
@section('title','Add Customer')
@section('content')
<form method="post" action="{{ route('admin.customers.store') }}" class="panel p-4">@csrf
<p>Customer ID is assigned automatically. Email is the login ID. When SMTP has passed its test, a verification invitation is sent to this email.</p>
<div class="row">
@foreach(['name'=>'Full name','email'=>'Login email','phone'=>'Phone','address'=>'Street address','city'=>'City','province'=>'Province / State','postal_code'=>'Postal code','country'=>'Country','website'=>'Website (optional)'] as $field=>$label)
<div class="form-group col-md-6"><label for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" type="{{ $field==='email'?'email':($field==='website'?'url':'text') }}" value="{{ old($field,$field==='country'?'Canada':'') }}" @if(in_array($field,['name','email'])) required @endif></div>
@endforeach
<div class="form-group col-md-6"><label>Account type</label><select name="account_type" class="form-control"><option value="individual">Individual</option><option value="business" @selected(old('account_type')==='business')>Business</option></select></div>
@foreach(['password'=>'Password','password_confirmation'=>'Confirm password'] as $field=>$label)<div class="form-group col-md-6"><label>{{ $label }}</label><input name="{{ $field }}" type="password" class="form-control" minlength="10" required autocomplete="new-password"></div>@endforeach
</div>
@include('partials.business-fields',['businessDynamic'=>true])
<p>Business customers enter the existing approval process. Individual email verification follows the current account settings.</p>
<button class="btn btn-primary">Create customer</button> <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-primary">Cancel</a>
</form><script src="{{ asset('assets/js/business-fields.js') }}"></script>
@endsection
