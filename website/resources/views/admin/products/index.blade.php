@extends('admin.layout')
@section('title','Manage Products')
@section('content')
<div class="mmc-toolbar"><p>{{ $products->total() }} products · All imported details are available under View.</p>@if(auth()->user()->hasAdminPermission('products.manage'))<a class="btn btn-primary" href="{{ route('admin.products.create') }}">Add Product</a>@endif</div>
<form class="mmc-filters" method="get" data-product-filters><input type="hidden" name="columns" value="{{ implode(',',$selectedColumns) }}"><label>Search<input class="form-control" name="q" value="{{ request('q') }}" placeholder="Product name, internal code or supplier SKU"></label><label>Supplier<input class="form-control" name="supplier" value="{{ request('supplier') }}" placeholder="Search supplier"></label><label>Category<select class="form-control" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category')==$category->id)>{{ $category->name }}</option>@endforeach</select></label><label>Status<select class="form-control" name="active"><option value="">All statuses</option><option value="1" @selected(request('active')==='1')>Active</option><option value="0" @selected(request('active')==='0')>Inactive</option></select></label><button class="btn btn-primary">Filter</button><a href="{{ route('admin.products.index') }}">Reset</a></form>
<div class="mb-3 d-flex flex-wrap" style="gap:8px"><button type="button" class="btn btn-outline-primary" data-toggle-columns aria-expanded="false" aria-controls="product-column-picker">☷ Choose columns</button>@foreach(['xlsx'=>'Excel','pdf'=>'PDF','print'=>'Print'] as $format=>$label)<a data-column-export class="btn btn-outline-primary" @if($format!=='xlsx')target="_blank" rel="noopener"@endif href="{{ route('admin.products.export',array_merge(request()->only(['q','supplier','category','active']),['format'=>$format,'columns'=>implode(',',$selectedColumns)])) }}">{{ $label }}</a>@endforeach <small class="align-self-center">Export all matching products · PDF uses Save as PDF in the print dialog.</small></div>
@include('admin.products.columns')
<form method="post" action="{{ route('admin.products.bulk-category') }}">@csrf
<div class="panel"><div class="panel-container show"><div class="panel-content table-responsive"><div class="product-grid-scroll"><table class="table" data-product-grid><thead><tr>@if(auth()->user()->hasAdminPermission('products.manage'))<th><input type="checkbox" data-select-products aria-label="Select all products on this page"></th>@endif
@foreach($columnLabels as $key=>$label)<th data-product-column="{{ $key }}" @if(!in_array($key,$selectedColumns)) hidden @endif>{{ $label }}</th>@endforeach<th>Actions</th></tr></thead><tbody>
@forelse($products as $product)@php($columnValues=app(\App\Services\ProductColumns::class)->values($product))<tr>@if(auth()->user()->hasAdminPermission('products.manage'))<td><input type="checkbox" name="product_ids[]" value="{{ $product->id }}" aria-label="Select {{ $product->premium_marketing_name }}"></td>@endif
@foreach($columnLabels as $key=>$label)<td data-product-column="{{ $key }}" @if(!in_array($key,$selectedColumns)) hidden @endif>@if($key==='image')@if($columnValues[$key])<img class="mmc-product-thumbnail" src="{{ $columnValues[$key] }}" alt="{{ $product->premium_marketing_name }}" width="64" height="64" loading="lazy">@else<span class="text-muted">No image</span>@endif @else{{ $columnValues[$key]===null||$columnValues[$key]===''?'—':$columnValues[$key] }}@endif</td>@endforeach
<td><div class="mmc-product-actions">
<a data-product-modal data-action-label="View" class="btn btn-outline-primary" href="{{ route('admin.products.show',[$product,'modal'=>1]) }}" title="View product" aria-label="View {{ $product->premium_marketing_name }}"><i class="fas fa-eye" aria-hidden="true"></i></a>
@if(auth()->user()->hasAdminPermission('products.manage'))
<a data-product-modal data-action-label="Edit" class="btn btn-outline-primary" href="{{ route('admin.products.edit',[$product,'modal'=>1]) }}" title="Edit product" aria-label="Edit {{ $product->premium_marketing_name }}"><i class="fas fa-edit" aria-hidden="true"></i></a>
@endif
<a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('admin.products.labels', ['product_ids'=>[$product->id]]) }}" title="Print barcode / PDF" aria-label="Print barcode for {{ $product->premium_marketing_name }}"><i class="fas fa-barcode" aria-hidden="true"></i></a>
</div></td></tr>@empty<tr><td colspan="{{ count($columnLabels)+2 }}">No matching products.</td></tr>@endforelse</tbody></table></div>
@if(auth()->user()->hasAdminPermission('products.manage'))<div class="mmc-filters"><label>Category for selected products<select class="form-control" name="category_id" required><option value="">Choose category</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label><label>Action<select class="form-control" name="mode"><option value="add">Add category (keep existing)</option><option value="replace">Replace all categories</option></select></label><button class="btn btn-primary">Apply Categories</button><button class="btn btn-outline-primary" type="button" data-print-barcodes="{{ route('admin.products.labels') }}">Print Selected Barcodes</button></div>@endif
{{ $products->links('pagination::bootstrap-4') }}</div></div></div></form>
<dialog id="product-dialog" aria-labelledby="product-dialog-title"><header><h2 id="product-dialog-title" class="h4 mb-0">Product</h2><div class="d-flex align-items-center" style="gap:10px"><button type="button" data-save-product class="btn btn-primary" hidden disabled>Save Product</button><button type="button" data-close-modal class="btn btn-outline-primary" aria-label="Close product modal">Close ×</button></div></header><iframe title="Product details and editor"></iframe></dialog>
<script src="{{ asset('admin-assets/product-modals.js') }}?v={{ filemtime(public_path('admin-assets/product-modals.js')) }}"></script>
<script>
document.querySelector('[data-print-barcodes]')?.addEventListener('click', function () {
    const selected = document.querySelectorAll('input[name="product_ids[]"]:checked');
    if (!selected.length) { alert('Select at least one product to print barcode labels.'); return; }
    const url = new URL(this.dataset.printBarcodes, location.href);
    selected.forEach(input => url.searchParams.append('product_ids[]', input.value));
    window.open(url.href, '_blank', 'noopener');
});
</script>
<script src="{{ asset('admin-assets/product-columns.js') }}?v={{ filemtime(public_path('admin-assets/product-columns.js')) }}"></script>
@endsection
