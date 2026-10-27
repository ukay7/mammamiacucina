@extends('admin.layout')
@section('title','Suppliers')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/supplier-orders.css') }}?v={{ filemtime(public_path('admin-assets/supplier-orders.css')) }}">
<div class="d-flex justify-content-between align-items-center mb-3"><p class="mb-0">Supplier records link products and purchase orders by ID.</p>@if(auth()->user()->hasAdminPermission('suppliers.manage'))<a class="btn btn-primary" href="{{ route('admin.suppliers.create') }}">Add Supplier</a>@endif</div>
<form class="panel panel-content mb-3">
    <div class="purchase-filter-grid">
        <div class="form-group"><label for="supplier-search">Search suppliers</label><input class="form-control" id="supplier-search" name="q" value="{{ request('q') }}" placeholder="Name, ID, contact, email or phone"></div>
        <div class="form-group"><label for="supplier-active">Status</label><select class="form-control" id="supplier-active" name="active"><option value="">All suppliers</option><option value="1" @selected(request('active')==='1')>Active</option><option value="0" @selected(request('active')==='0')>Inactive</option></select></div>
        <div class="form-group purchase-filter-actions"><button class="btn btn-primary" type="submit">Filter</button><a class="btn btn-outline-primary" href="{{ route('admin.suppliers.index') }}">Reset</a></div>
    </div>
</form>
<div class="panel panel-content"><div class="table-responsive"><table class="table"><thead><tr><th>Supplier ID</th><th>Name</th><th>Contact</th><th>Status</th><th>Products</th><th>Orders</th><th>Actions</th></tr></thead><tbody>
@forelse($suppliers as $supplier)<tr><td>#{{ $supplier->id }}</td><td><a href="{{ route('admin.suppliers.show',$supplier) }}">{{ $supplier->name }}</a></td><td>{{ $supplier->contact_name }}<br>{{ $supplier->email }}<br>{{ $supplier->phone }}</td><td>{{ $supplier->is_active?'Active':'Inactive' }}</td><td>{{ $supplier->products_count }}</td><td>{{ $supplier->orders_count }}</td><td><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.suppliers.show',$supplier) }}">View</a>@if(auth()->user()->hasAdminPermission('suppliers.manage')) <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.suppliers.edit',$supplier) }}">Edit</a>@endif</td></tr>@empty<tr><td colspan="7">No suppliers found.</td></tr>@endforelse
</tbody></table></div>{{ $suppliers->links() }}</div>
@endsection
