@extends('admin.layout')
@section('title','My Profile')
@section('content')
<div class="panel panel-content" style="max-width:900px">
<form method="post" action="{{ route('customer.profile.update') }}">
@csrf @method('PUT')
@php($customer=$user->customerRecord())
<div class="row">
<div class="col-md-6 mb-4"><label for="profile-name">Full name</label><input class="form-control" id="profile-name" name="name" value="{{ old('name',$user->name) }}" required maxlength="100" autocomplete="name"></div>
<div class="col-md-6 mb-4"><label for="profile-email">Primary email address</label><input class="form-control" id="profile-email" type="email" value="{{ $user->email }}" readonly aria-describedby="email-help"><small id="email-help" class="d-block mt-2">Your login email cannot be changed. Verification and password-reset links are sent only here.</small></div>
<div class="col-md-6 mb-4"><label for="profile-alternate-email">Alternate email address (optional)</label><input class="form-control" id="profile-alternate-email" type="email" name="alternate_email" value="{{ old('alternate_email',$customer->alternate_email) }}" maxlength="255" autocomplete="email" aria-describedby="alternate-email-help"><small id="alternate-email-help" class="d-block mt-2">Also receive order emails, invoices, receipts and other customer notifications at this address. Leave blank to stop future copies.</small></div>
<div class="col-md-6 mb-4"><label for="profile-phone">Phone number</label><input class="form-control" id="profile-phone" type="tel" name="phone" value="{{ old('phone',$user->phone) }}" required maxlength="40" autocomplete="tel"></div>
<div class="col-md-6 mb-4"><label for="profile-type">Account type</label><input class="form-control" id="profile-type" value="{{ $user->account_type === 'business' ? 'Business owner' : 'Individual' }}" readonly aria-describedby="type-help"><small id="type-help" class="d-block mt-2">Account type cannot be changed.</small></div>
</div>
<section class="mt-2 mb-4" style="border-top:1px solid #e3d4bb;padding-top:24px">
<h2 style="font-size:23px">Saved delivery address</h2>
<p class="text-muted">Used to prefill your next checkout. Changes here do not change existing orders.</p>
<div class="row">
@foreach(['address'=>['Street address',255,'street-address'],'city'=>['City',100,'address-level2'],'province'=>['Province / State',100,'address-level1'],'postal_code'=>['Postal code',30,'postal-code'],'country'=>['Country',100,'country-name']] as $field=>$details)
<div class="{{ $field==='address'?'col-12':'col-md-6' }} mb-4"><label for="profile-{{ $field }}">{{ $details[0] }}</label><input class="form-control" id="profile-{{ $field }}" name="{{ $field }}" value="{{ old($field,$customer->$field) }}" maxlength="{{ $details[1] }}" autocomplete="{{ $details[2] }}"></div>
@endforeach
</div>
</section>
@if($user->account_type==='business')@include('partials.business-fields',['businessProfile'=>$customer])@endif
<button class="btn btn-primary" type="submit">Save Profile</button>
<a class="btn btn-outline-primary ml-2" href="{{ route('customer.orders') }}">My Orders</a>
</form>
</div>
<div class="panel panel-content mt-4" id="customer-documents">@include('partials.customer-documents')</div>
@endsection
