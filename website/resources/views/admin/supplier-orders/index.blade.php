@extends('admin.layout')
@section('title','Supplier Orders')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/supplier-orders.css') }}?v={{ filemtime(public_path('admin-assets/supplier-orders.css')) }}">
<div class="d-flex justify-content-between align-items-center mb-3"><p class="mb-0">Purchase orders to suppliers. Inventory is updated separately.</p>@if(auth()->user()->hasAdminPermission('purchases.manage'))<a class="btn btn-primary" href="{{ route('admin.supplier-orders.create') }}">Create Supplier Order</a>@endif</div>
<form class="panel panel-content">
    <div class="purchase-filter-grid">
        <div class="form-group"><label for="purchase-search">Search</label><input class="form-control" id="purchase-search" name="q" value="{{ request('q') }}" placeholder="Order number or supplier name"></div>
        <div class="form-group"><label for="purchase-status">Status</label><select class="form-control" id="purchase-status" name="status">@foreach([...\App\Models\SupplierOrder::STATUS_LABELS,'all'=>'All statuses'] as $key=>$label)<option value="{{ $key }}" @selected($status===$key)>{{ $label }}</option>@endforeach</select></div>
        <div class="form-group purchase-filter-actions"><button class="btn btn-primary" type="submit">Filter</button></div>
    </div>
</form>
<section class="panel panel-content"><div class="table-responsive"><table class="table"><thead><tr><th>Order</th><th>Supplier</th><th>Status</th><th>Products</th><th>Total</th><th>Created</th><th>Action</th></tr></thead><tbody>@forelse($orders as $order)<tr><td>{{ $order->number }}</td><td>{{ $order->supplier_snapshot['name'] }}</td><td>{{ \App\Models\SupplierOrder::STATUS_LABELS[$order->status] }}</td><td>{{ $order->items_count }}</td><td>@foreach($order->totals as $currency=>$amount)<div>{{ $currency }} {{ $amount }}</div>@endforeach</td><td>{{ $order->created_at->format('d M Y H:i') }}<br><small>{{ $order->author?->name }}</small></td><td><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.supplier-orders.show',$order) }}">View</a></td></tr>@empty<tr><td colspan="7">No supplier orders match these filters.</td></tr>@endforelse</tbody></table></div>{{ $orders->links() }}</section>
@endsection
