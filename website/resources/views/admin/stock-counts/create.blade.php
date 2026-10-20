@extends('admin.layout')
@section('title','Assign a Stock Count')
@section('page-class','stock-count-create-page')
@section('hide-page-heading','1')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/stock-counts.css') }}?v={{ filemtime(public_path('admin-assets/stock-counts.css')) }}">
<form class="sc-panel sc-form sc-create-form" method="post" action="{{ route('admin.stock-counts.store') }}" id="count-task-form">@csrf
<header class="sc-create-header"><div><span>NEW COUNT TASK</span><h1>Assign a stock count</h1><p>Choose the location, schedule and products. The assigned Warehouse User will see the task immediately.</p></div><div class="sc-header-note"><i class="fas fa-clipboard-check" aria-hidden="true"></i><span><strong>Physical inventory</strong><small>Saved counts remain separate until the task is submitted.</small></span></div></header>
<div class="sc-create-layout">
<section class="sc-setup-card" aria-labelledby="task-setup-heading"><div class="sc-section-heading"><span>01</span><div><h2 id="task-setup-heading">Task setup</h2><p>Define where, who and when.</p></div></div>
<div class="sc-form-grid">
<label class="wide">Warehouse / location<select class="form-control" name="warehouse_id" required><option value="">Select warehouse</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(old('warehouse_id')==$warehouse->id)>{{ $warehouse->name }} · {{ ucfirst($warehouse->storage_type) }}</option>@endforeach</select></label>
<label class="wide">Warehouse user<select class="form-control" name="assigned_to" required><option value="">Select user</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(old('assigned_to')==$user->id)>{{ $user->name }} · {{ $user->email }}</option>@endforeach</select></label>
<label>Start date and time<input class="form-control" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}"></label><label>Due date and time<input class="form-control" type="datetime-local" name="due_at" value="{{ old('due_at') }}"></label>
<label class="wide">Instructions <small>Optional</small><textarea class="form-control" name="instructions" rows="4" maxlength="4000" placeholder="Add handling or counting instructions">{{ old('instructions') }}</textarea></label>
</div></section>
<section class="sc-products" aria-labelledby="product-heading"><div class="sc-section-heading"><span>02</span><div><h2 id="product-heading">Products to count</h2><p id="product-help">All active products are selected. Uncheck anything that should be excluded.</p></div><strong class="sc-selection-count"><b id="selected-count">0</b> of {{ $products->count() }} selected</strong></div>
<div class="sc-product-toolbar"><label>Count scope<select class="form-control" name="scope" id="scope"><option value="full" @selected(old('scope','full')==='full')>Full catalogue</option><option value="specific" @selected(old('scope')==='specific')>Specific products</option></select></label><label class="sc-product-search"><span class="sr-only">Search products</span><i class="fas fa-search" aria-hidden="true"></i><input class="form-control" id="product-search" placeholder="Search product, code or barcode"></label><div class="sc-bulk-actions"><button type="button" class="btn btn-light btn-sm" id="select-visible">Select visible</button><button type="button" class="btn btn-light btn-sm" id="clear-visible">Clear visible</button></div></div>
<div class="sc-product-list" id="stock-count-product-list">@foreach($products as $product)<label data-product="{{ Str::lower($product->premium_marketing_name.' '.$product->product_code.' '.$product->qr_code) }}"><input class="product-check" type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked(old('scope','full')==='full'||in_array($product->id,old('product_ids',[])))>@if($image=$product->media->first())<span class="sc-product-symbol"><img class="mmc-lazy-image" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" data-src="{{ route('catalogue.media',[$product,$image]) }}" alt="" width="34" height="34" decoding="async"></span>@else<span class="sc-product-symbol"><i class="fas fa-box"></i></span>@endif<b>{{ $product->premium_marketing_name }}<small>{{ $product->product_code ?: 'No code' }} · {{ $product->qr_code ?: 'No barcode' }}</small></b></label>@endforeach</div></section>
</div>
<div class="sc-form-actions"><p><i class="fas fa-info-circle" aria-hidden="true"></i> The task can be reviewed live while the warehouse user saves progress.</p><a class="btn btn-light" href="{{ route('admin.stock-counts.index') }}">Cancel</a><button class="btn btn-primary"><i class="fas fa-check" aria-hidden="true"></i> Assign count task</button></div></form>
<script>
(()=>{const scope=document.getElementById('scope'),checks=[...document.querySelectorAll('.product-check')],help=document.getElementById('product-help'),count=document.getElementById('selected-count'),rows=[...document.querySelectorAll('[data-product]')];
const updateCount=()=>count.textContent=checks.filter(c=>c.checked).length;
const applyScope=()=>{const full=scope.value==='full';help.textContent=full?'All active products are selected. Uncheck anything that should be excluded.':'Search and select every product the user must count.';checks.forEach(c=>c.checked=full);updateCount()};
scope.addEventListener('change',applyScope);checks.forEach(c=>c.addEventListener('change',updateCount));
document.getElementById('product-search').addEventListener('input',e=>{const q=e.target.value.toLowerCase().trim();rows.forEach(row=>row.hidden=!row.dataset.product.includes(q))});
document.getElementById('select-visible').addEventListener('click',()=>{rows.filter(row=>!row.hidden).forEach(row=>row.querySelector('.product-check').checked=true);updateCount()});
document.getElementById('clear-visible').addEventListener('click',()=>{rows.filter(row=>!row.hidden).forEach(row=>row.querySelector('.product-check').checked=false);updateCount()});
updateCount();
})();
</script>
@endsection
