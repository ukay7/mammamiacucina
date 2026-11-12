@extends('admin.layout')
@section('title','Pricing Lists')
@section('content')
<div class="d-flex flex-wrap justify-content-between mb-3"><p>Create customer-specific price lists without changing catalogue prices.</p>
@if(auth()->user()->hasAdminPermission('pricing-lists.manage'))<a class="btn btn-primary" href="{{ route('admin.pricing-lists.create') }}">+ New pricing list</a>@endif</div>
<form class="panel p-3 mb-3"><div class="row">
<div class="col-md-4"><label for="search">List number, title or recipient</label><input id="search" name="search" class="form-control" value="{{ $filters['search']??'' }}"></div>
<div class="col-md-2"><label for="tier">Pricing</label><select id="tier" name="tier" class="form-control"><option value="">All pricing</option>@foreach(['individual','business'] as $tier)<option value="{{ $tier }}" @selected(($filters['tier']??'')===$tier)>{{ ucfirst($tier) }}</option>@endforeach</select></div>
<div class="col-md-2"><label for="from">Effective from</label><input id="from" type="date" name="from" class="form-control" value="{{ $filters['from']??'' }}"></div>
<div class="col-md-2"><label for="to">Through</label><input id="to" type="date" name="to" class="form-control" value="{{ $filters['to']??'' }}"></div>
<div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary mr-2">Filter</button><a class="btn btn-outline-primary" href="{{ route('admin.pricing-lists.index') }}">Clear</a></div>
</div></form>
<div class="panel p-3 table-responsive"><table class="table"><thead><tr><th>List</th><th>Prepared for</th><th>Pricing</th><th>Effective / valid through</th><th>Products</th><th>Actions</th></tr></thead><tbody>
@forelse($lists as $list)<tr><td><a href="{{ route('admin.pricing-lists.show',$list) }}"><strong>{{ $list->number }}</strong></a><br>{{ $list->title }}</td><td>{{ $list->recipient_name ?: 'General price list' }}</td><td>{{ ucfirst($list->pricing_tier) }}</td><td>{{ $list->effective_date->format('M j, Y') }}@if($list->valid_until)<br><small>Until {{ $list->valid_until->format('M j, Y') }}</small>@endif</td><td>{{ $list->product_count }}</td><td><a href="{{ route('admin.pricing-lists.show',$list) }}">View / PDF</a>@if(auth()->user()->hasAdminPermission('pricing-lists.manage')) · <a href="{{ route('admin.pricing-lists.edit',$list) }}">Edit</a>@endif</td></tr>
@empty<tr><td colspan="6" class="text-center py-5">No pricing lists found. Create a list, choose products, and set your prices.</td></tr>@endforelse
</tbody></table>{{ $lists->links() }}</div>
@endsection
