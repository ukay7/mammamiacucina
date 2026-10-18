@extends('admin.layout')
@section('title',$archived?'Archived Orders':($completed?'View Completed Orders':'View Pending Orders'))
@if($warehouseOnly)
@section('hide-page-heading','1')
@section('page-class','warehouse-order-page')
@endif
@section('content')
@if($warehouseOnly)
@include('admin.orders.warehouse-list')
@else
@if($warehouseOnly)<p>{{ $completed?'Completed orders created by you.':'Orders currently parked at the warehouse for packing.' }}</p>@endif
<style>.warehouse-issue-row{background:#fff2c9;border-left:5px solid #bc8a16}.warehouse-issue-row .mmc-status-pill{color:#805200;font-weight:700}</style>
<form action="{{ route($listRoute) }}" method="get" class="mmc-filters mmc-order-filters"><label>Search orders<input class="form-control" name="q" value="{{ $search }}" placeholder="Order number, customer or email"></label><label>From date<input class="form-control" type="date" name="from" value="{{ $from }}" required></label><label>To date<input class="form-control" type="date" name="to" value="{{ $to }}" required></label><label>Status<select class="form-control" name="status"><option value="">All statuses</option>@foreach(\App\Models\Order::STATUSES as $key=>$label)
@if($archived || ($key==='completed')===$completed)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endif
@endforeach</select></label><label>Rows<select class="form-control" name="per_page">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected((int)request("per_page",20)===$size)>{{ $size }}</option>@endforeach</select></label><button class="btn btn-primary">Filter</button><a href="{{ route($listRoute) }}">Reset</a></form>
<div class="mb-3 d-flex flex-wrap" style="gap:10px">
<a class="btn btn-outline-primary" href="{{ route('admin.orders.export',array_merge(request()->except('page'),['format'=>'xlsx','view'=>$archived?'archived':($completed?'completed':'pending')])) }}">Excel</a>
<a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('admin.orders.export',array_merge(request()->except('page'),['format'=>'pdf','view'=>$archived?'archived':($completed?'completed':'pending')])) }}">PDF</a>
<a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('admin.orders.export',array_merge(request()->except('page'),['format'=>'print','view'=>$archived?'archived':($completed?'completed':'pending')])) }}">Print</a>
<small>Export all matching orders. PDF: choose Save as PDF in the print dialog.</small>
</div>

@include('admin.orders.list-table')
@endif
@endsection
