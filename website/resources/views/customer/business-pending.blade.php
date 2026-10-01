@extends('layouts.mmc-page',['pageTitle'=>'Business approval pending'])
@section('page-content')
<div class="mmc-panel" style="max-width:720px;margin:auto">
<h2>Your business account is under process</h2>
<p role="alert">Kindly wait or contact system administration. You cannot place an order until your business account is approved.</p>
<p>We have received your business details. You can continue browsing with individual pricing. Business prices and your customer dashboard will become available after admin approval and email verification.</p>
@include('partials.business-approval-contact')
<a class="mmc-button" href="{{ route('theme.contact') }}">Contact administration</a>
<a class="mmc-button" href="{{ route('theme.index') }}">Continue browsing</a>
<form method="post" action="{{ route('customer.logout') }}">@csrf <button class="mmc-button" type="submit">Sign out</button></form>
</div>
@endsection
