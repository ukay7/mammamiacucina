@extends('admin.layout')
@section('title','Duplicate '.$quotation->number)
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/vendor/select2/select2.min.css') }}">
<script src="{{ asset('admin-assets/vendor/select2/select2.full.min.js') }}" defer></script>
<form method="post" action="{{ route('admin.quotations.duplicate.store',$quotation) }}" class="panel p-4" style="max-width:860px">
    @csrf
    <input type="hidden" name="revision" value="{{ old('revision',$quotation->revision) }}">
    <h2 class="h4">Same quotation, another customer</h2>
    <p>Choose who this copy is for. The saved products, quantities, prices, discounts and terms will be copied exactly. You can edit the new quotation before sharing it.</p>
    <div class="p-3 mb-4" style="background:#f7f0e6;border-left:3px solid #b98c45;border-radius:6px">
        <strong>{{ $quotation->number }}</strong> · {{ ($quotation->customer_snapshot['business_name'] ?? '') ?: $quotation->customer_snapshot['name'] }}
        <div class="mt-1">{{ count($quotation->items ?? []) }} products · {{ ucfirst($quotation->pricing_tier) }} pricing · <strong>CAD {{ number_format($quotation->total_cents/100,2) }}</strong></div>
    </div>
    <div class="row">
        <div class="form-group col-md-8">
            <label for="duplicate-customer">Customer / company for the new quotation</label>
            <select id="duplicate-customer" name="customer_id" class="form-control" data-business-customer-select required>
                <option value="">Select a customer or company</option>
                @foreach($customers as $c)
                <option value="{{ $c['id'] }}" data-account-type="{{ $c['account_type'] }}" data-label="#{{ $c['id'] }} — {{ $c['business_name'] ? $c['business_name'].' · ' : '' }}{{ $c['name'] }}" data-email="{{ $c['email'] }}" @selected(old('customer_id')==$c['id'])>#{{ $c['id'] }} — {{ $c['business_name'] ? $c['business_name'].' · ' : '' }}{{ $c['name'] }} ({{ $c['email'] }})</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-4">
            <label for="duplicate-due-date">Valid until</label>
            <input type="date" id="duplicate-due-date" name="due_date" class="form-control" value="{{ old('due_date',$dueDate) }}" required>
        </div>
    </div>
    <p class="text-muted">The copy gets a new quotation number and today's issue date. The original stays unchanged. Creating a copy does not send an email.</p>
    <button type="submit" class="btn btn-primary"><i class="fas fa-copy mr-1" aria-hidden="true"></i>Create duplicate &amp; edit</button>
    <a class="btn btn-outline-primary" href="{{ route('admin.quotations.show',$quotation) }}">Cancel</a>
</form>
@endsection
