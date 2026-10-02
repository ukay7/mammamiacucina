@extends('admin.layout')
@section('title','Customer Management')
@section('content')
@if(auth()->user()->hasAdminPermission('users.manage'))<a class="btn btn-primary mb-3" href="{{ route('admin.customers.create') }}">Add Customer</a>@endif
@include('admin.customers.business-requests')
<form method="get" class="mb-4"><label for="customer-search">Search customers</label><div class="d-flex" style="gap:12px;max-width:720px"><input id="customer-search" class="form-control" name="search" value="{{ $search }}" placeholder="Customer ID (e.g. 4000), name, email or phone"><button class="btn btn-primary">Search</button><a class="btn btn-outline-primary" href="{{ route('admin.customers.index') }}">Reset</a></div></form>
<div class="panel panel-content"><div class="table-responsive"><table class="table"><thead><tr><th>Customer ID</th><th>Name / Email</th><th>Phone</th><th>Account type</th><th>Verified</th><th>Status</th><th>Orders</th><th>Last order</th><th>Action</th></tr></thead><tbody>
@forelse($customers as $customer)
<tr><td>#{{ $customer->id }}</td><td>{{ $customer->name }}<br><small>{{ $customer->email }}</small></td><td>{{ $customer->phone ?: '—' }}</td><td>{{ $customer->account_type==='business'?'Business owner':'Individual' }}@if($customer->account_type==='business')<br><strong>{{ $customer->user->business_approved_at?'Approved':'Approval pending' }}</strong>@endif</td><td>{{ $customer->user->email_verified_at?'Yes':'Pending' }}</td><td>{{ $customer->user->is_active?'Active':'Inactive' }}</td><td>{{ $customer->orders_count }}</td><td>{{ $customer->orders_max_created_at ? \Carbon\Carbon::parse($customer->orders_max_created_at)->format('d M Y') : '—' }}</td><td><a class="btn btn-outline-primary" href="{{ route('admin.customers.show',$customer) }}">View customer</a>@if(auth()->user()->hasAdminPermission('users.manage')) <a class="btn btn-outline-primary" href="{{ route('admin.customers.edit',$customer) }}">Edit</a>@if(!$customer->user->email_verified_at && $customer->user->is_active)<form class="mt-2" method="post" action="{{ route('admin.customers.resend',$customer) }}">@csrf @include('partials.verification-resend-button',['verificationUser'=>$customer->user])</form>@endif @endif</td></tr>
@empty<tr><td colspan="9">No customers found.</td></tr>@endforelse
</tbody></table></div>{{ $customers->links() }}</div>
@endsection
