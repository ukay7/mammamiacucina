@extends('admin.layout')
@section('title','Quotations')
@section('content')
@if(auth()->user()->hasAdminPermission('quotations.manage'))<a class="btn btn-primary mb-3" href="{{ route('admin.quotations.create') }}">Add Quotation</a>@endif
<form class="mb-3"><label>Search quotation number, customer name or email</label><div class="input-group"><input class="form-control" name="search" value="{{ $search }}"><button class="btn btn-outline-primary">Search</button></div></form>
<div class="panel p-3 table-responsive"><table class="table"><thead><tr><th>Quotation</th><th>Customer</th><th>Pricing</th><th>Due date</th><th>Total (CAD)</th><th>Notes</th><th>Actions</th></tr></thead><tbody>
@forelse($quotations as $q)<tr><td>{{ $q->number }}</td><td>{{ $q->customer_snapshot['name'] }}<br><small>#{{ $q->customer_id }}</small></td><td>{{ ucfirst($q->pricing_tier) }}</td><td>{{ $q->due_date->format('d M Y') }}</td><td>${{ number_format($q->total_cents/100,2) }}</td><td>{{ Str::limit($q->notes,80) }}</td><td><a href="{{ route('admin.quotations.show',$q) }}">View</a> · <a target="_blank" href="{{ route('admin.quotations.print',$q) }}">Print</a>@if(auth()->user()->hasAdminPermission('quotations.manage')) · <a href="{{ route('admin.quotations.edit',$q) }}">Edit</a><form method="post" action="{{ route('admin.quotations.destroy',$q) }}" data-confirm="Delete this quotation?">@csrf @method('DELETE')<input type="hidden" name="revision" value="{{ $q->revision }}"><button class="btn btn-sm btn-outline-danger">Delete</button></form>@endif</td></tr>@empty<tr><td colspan="7">No quotations yet.</td></tr>@endforelse
</tbody></table>{{ $quotations->links() }}</div>
@endsection
