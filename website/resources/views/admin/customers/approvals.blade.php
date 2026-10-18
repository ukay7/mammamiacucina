@extends('admin.layout')
@section('title','Business Approval Requests')
@section('content')
@include('admin.customers.filters', ['approvalScreen'=>true])
@include('admin.customers.business-requests')
@endsection
