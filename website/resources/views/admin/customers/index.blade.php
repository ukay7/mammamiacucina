@extends('admin.layout')
@section('title','View Customer')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/customer-columns.css') }}?v={{ filemtime(public_path('admin-assets/customer-columns.css')) }}">
<div class="customer-directory" data-customer-directory>
@if(auth()->user()->hasAdminPermission('customers.manage'))<a class="btn btn-primary mb-3" href="{{ route('admin.customers.create') }}">Add Customer</a>@endif
@include('admin.customers.filters', ['approvalScreen'=>false])
<div class="customer-column-toolbar"><button class="btn btn-outline-primary" type="button" data-customer-column-toggle aria-expanded="false" aria-controls="customer-column-picker">☷ Choose columns</button><span class="text-muted"><span data-customer-column-count>{{ count($selectedColumns) }}</span> selected columns · Actions are always available</span></div>
<section class="customer-column-picker" id="customer-column-picker" hidden data-storage="mmc-customer-columns-{{ auth()->id() }}" aria-label="Customer columns">
    <div class="customer-column-presets"><button class="btn btn-sm btn-outline-primary" type="button" data-customer-column-preset="compact">Compact</button><button class="btn btn-sm btn-outline-primary" type="button" data-customer-column-preset="all">All columns</button><button class="btn btn-sm btn-outline-primary" type="button" data-customer-column-preset="clear">Clear selection</button></div>
    <div class="customer-column-options">@foreach($columnLabels as $key=>$label)<label><input type="checkbox" data-customer-column-check value="{{ $key }}" @checked(in_array($key,$selectedColumns,true))>{{ $label }}</label>@endforeach</div>
</section>
<div class="panel panel-content"><div class="table-responsive"><table class="table"><thead><tr>
@foreach($columnLabels as $key=>$label)
<th data-customer-column="{{ $key }}" @if(!in_array($key,$selectedColumns,true)) hidden @endif>
    @if($key==='id')@include('admin.customers.sort-link',['column'=>'id','label'=>'Customer ID'])
    @elseif($key==='identity')Business / @include('admin.customers.sort-link',['column'=>'name','label'=>'Name']) / @include('admin.customers.sort-link',['column'=>'email','label'=>'Email'])
    @elseif($key==='created_at')@include('admin.customers.sort-link',['column'=>'created_at','label'=>'Created at'])
    @else {{ $label }} @endif
</th>
@endforeach
<th>Action</th></tr></thead><tbody>
@forelse($customers as $customer)
<tr @class(['customer-needs-review' => !$customer->user->email_verified_at || ($customer->account_type === 'business' && !$customer->user->business_approved_at)])>
    @foreach($columnLabels as $key=>$label)<td data-customer-column="{{ $key }}" @if(!in_array($key,$selectedColumns,true)) hidden @endif>@include('admin.customers.column-cell')</td>@endforeach
    <td class="text-nowrap"><a class="btn btn-outline-primary" href="{{ route('admin.customers.show',$customer) }}" title="View Detail" aria-label="View Detail"><i class="fas fa-eye" aria-hidden="true"></i></a>
    @if(auth()->user()->hasAdminPermission('customers.manage')) <a class="btn btn-outline-primary" href="{{ route('admin.customers.edit',$customer) }}" title="Edit Customer" aria-label="Edit Customer"><i class="fas fa-edit" aria-hidden="true"></i></a>
        @if(!$customer->user->email_verified_at && $customer->user->is_active)<form class="d-inline-block m-0" method="post" action="{{ route('admin.customers.resend',$customer) }}">@csrf @include('partials.verification-resend-button',['verificationUser'=>$customer->user,'iconOnly'=>true])</form>@endif
    @endif</td>
</tr>
@empty<tr><td data-customer-empty colspan="{{ count($selectedColumns)+1 }}">No customers found.</td></tr>@endforelse
</tbody></table></div><div data-customer-pagination>@include('admin.customers.pagination',['records'=>$customers])</div></div>
</div>
<script defer src="{{ asset('admin-assets/customer-columns.js') }}?v={{ filemtime(public_path('admin-assets/customer-columns.js')) }}"></script>
@endsection
