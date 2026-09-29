@extends('admin.layout')
@section('title','Edit Customer #'.$customer->id)
@section('content')
<a class="btn btn-outline-primary mb-4" href="{{ route('admin.customers.show',$customer) }}">Back to Customer</a>
<div class="panel panel-content" style="max-width:1000px">
<h2>Customer details</h2>
<p>{{ $customer->email }} · {{ $customer->account_type==='business'?'Business owner':'Individual' }} · {{ $customer->user->email_verified_at?'Verified':'Not verified' }}</p>
<form method="post" action="{{ route('admin.customers.update',$customer) }}">@csrf @method('PUT')
<div class="row">
@foreach(['name'=>['Full name',100],'phone'=>['Phone',40],'address'=>['Street address',255],'city'=>['City',100],'province'=>['Province / State',100],'postal_code'=>['Postal code',30],'country'=>['Country',100]] as $field=>$details)
<div class="col-md-6 mb-3"><label for="edit-{{ $field }}">{{ $details[0] }}</label><input class="form-control" id="edit-{{ $field }}" name="{{ $field }}" value="{{ old($field,$customer->$field) }}" maxlength="{{ $details[1] }}" @required($field==='name')></div>
@endforeach
</div><button class="btn btn-primary">Save Customer</button></form></div>
<div class="panel panel-content" style="max-width:1000px"><h2>Account access</h2>
<p>Account is <strong>{{ $customer->user->is_active?'Active':'Inactive' }}</strong>.</p>
<form class="mb-3" method="post" action="{{ route('admin.customers.active',$customer) }}" data-confirm="{{ $customer->user->is_active?'Deactivate this customer and block account access?':'Reactivate this customer account?' }}">@csrf<input type="hidden" name="is_active" value="{{ $customer->user->is_active?0:1 }}"><button class="btn btn-outline-primary">{{ $customer->user->is_active?'Deactivate Customer':'Activate Customer' }}</button></form>
@if(!$customer->user->email_verified_at && $customer->user->is_active)<form method="post" action="{{ route('admin.customers.resend',$customer) }}">@csrf<button class="btn btn-outline-primary">Resend Verification Email</button></form>@endif
</div>
<div class="panel panel-content" style="max-width:1000px"><h2>Change customer password</h2><p>Changing a password does not mark an unverified email as verified.</p>
<form method="post" action="{{ route('admin.customers.password',$customer) }}" data-confirm="Set a new password for this customer?">@csrf
<div class="row"><div class="col-md-6 mb-3"><label for="customer-password">New password (at least 10 characters)</label><input class="form-control" type="password" id="customer-password" name="password" required minlength="10" autocomplete="new-password"></div><div class="col-md-6 mb-3"><label for="customer-password-confirm">Confirm password</label><input class="form-control" type="password" id="customer-password-confirm" name="password_confirmation" required autocomplete="new-password"></div></div><button class="btn btn-primary">Change Password</button>
</form></div>
@endsection
