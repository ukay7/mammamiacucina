@extends('admin.layout')
@section('title','Edit Customer #'.$customer->id)
@section('content')
<a class="btn btn-outline-primary mb-4" href="{{ route('admin.customers.show',$customer) }}">Back to Customer</a>
@include('admin.customers.account-editor')
<div class="panel panel-content mt-4" id="customer-documents">@include('partials.customer-documents')</div>
@endsection
