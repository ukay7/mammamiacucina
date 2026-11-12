@extends('admin.layout')
@section('title','My Documents')
@section('content')
<p><a class="btn btn-outline-primary" href="{{ route('customer.orders') }}">My Orders</a> <a class="btn btn-outline-primary" href="{{ route('customer.profile') }}">My Profile</a></p>
<div class="panel panel-content" id="customer-documents">@include('partials.customer-documents')</div>
@endsection
