@extends('admin.layout')
@section('title',$list->number.' · '.$list->title)
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/pricing-lists.css') }}?v={{ filemtime(public_path('admin-assets/pricing-lists.css')) }}">
<div class="pricing-buttons mb-3"><a class="btn btn-outline-primary" href="{{ route('admin.pricing-lists.index') }}">All pricing lists</a>
<button type="button" class="btn btn-primary" onclick="document.getElementById('saved-print-confirm').showModal()">Print / PDF</button>
@if(auth()->user()->hasAdminPermission('pricing-lists.manage'))
<a class="btn btn-outline-primary" href="{{ route('admin.pricing-lists.edit',$list) }}">Edit</a>
<form action="{{ route('admin.pricing-lists.duplicate',$list) }}" method="post">@csrf<button class="btn btn-outline-primary">Duplicate for another customer</button></form>
<form action="{{ route('admin.pricing-lists.destroy',$list) }}" method="post" data-confirm="Delete this saved pricing list? Product prices will stay unchanged.">@csrf @method('DELETE')<input type="hidden" name="revision" value="{{ $list->revision }}"><button class="btn btn-outline-danger">Delete</button></form>
@endif</div>
<p>{{ count($list->items) }} products · {{ ucfirst($list->pricing_tier) }} pricing · {{ $list->recipient_name ?: 'General price list' }} · Saved prices and product details</p>
<iframe class="pricing-saved-preview" src="{{ route('admin.pricing-lists.document',$list) }}" title="Saved pricing list" sandbox=""></iframe>
<dialog id="saved-print-confirm" class="pricing-dialog" aria-labelledby="saved-confirm-title"><div class="pricing-dialog-head"><h2 id="saved-confirm-title">Confirm PDF details</h2><button type="button" class="btn btn-outline-primary" onclick="this.closest('dialog').close()">Back</button></div>
<p>{{ count($list->items) }} products · {{ ucfirst($list->pricing_tier) }} · {{ $list->recipient_name ?: 'General price list' }} · {{ $list->effective_date->format('M j, Y') }}</p>
<iframe src="{{ route('admin.pricing-lists.document',$list) }}" title="Confirm all selected products and prices" sandbox=""></iframe>
<div class="pricing-dialog-foot"><a class="btn btn-primary" href="{{ route('admin.pricing-lists.pdf',$list) }}" target="_blank" rel="noopener" onclick="this.closest('dialog').close()">Confirm &amp; open PDF</a></div></dialog>
@endsection
