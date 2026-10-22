@extends('admin.layout')
@section('title','Inventory Valuation')
@section('page-class','inventory-valuation-page')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/inventory-valuation.css') }}?v={{ filemtime(public_path('admin-assets/inventory-valuation.css')) }}">
<div class="valuation-toolbar"><p>Current on-hand quantity × supplier unit price. All valuation amounts are in EUR.</p><a class="btn btn-outline-primary" href="{{ route('admin.inventory.index') }}">Manage inventory</a></div>
<div class="valuation-summary">
    <div><span>Grand total · all matching records</span><strong>€{{ number_format((float)$summary['total'],2) }}</strong></div>
    <div><span>Matching products</span><strong>{{ $summary['records'] }}</strong></div>
    <div><span>Missing supplier price</span><strong>{{ $summary['missing_prices'] }}</strong><small>Shown as 0; price cells highlighted red.</small></div>
</div>
<form id="valuation-filters" class="valuation-filters" method="get" data-valuation-filters>
    <input type="hidden" name="columns" value="{{ implode(',',$selected) }}">
    <label>Search products<input class="form-control" name="q" value="{{ request('q') }}" placeholder="Name, product code, barcode, ID or supplier"></label>
    <label>Category<select class="form-control" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category')==$category->id)>{{ $category->name }}</option>@endforeach</select></label>
    <label>Warehouse<select class="form-control" name="warehouse"><option value="">All warehouses combined</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(request('warehouse')==$warehouse->id)>{{ $warehouse->name }}{{ !$warehouse->is_active ? ' (inactive)' : '' }}</option>@endforeach</select></label>
    <label>Product status<select class="form-control" name="active"><option value="">Active and inactive</option><option value="1" @selected(request('active')==='1')>Active</option><option value="0" @selected(request('active')==='0')>Inactive</option></select></label>
    <label>Stock condition<select class="form-control" name="stock"><option value="">All inventory</option>@foreach(['tracked'=>'Tracked stock','unset'=>'Opening stock not set','healthy'=>'Healthy','low'=>'Low stock','out'=>'Out of stock','shortage'=>'Shortage / oversold'] as $key=>$label)<option value="{{ $key }}" @selected(request('stock')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label>Supplier price<select class="form-control" name="pricing"><option value="">All prices</option><option value="missing" @selected(request('pricing')==='missing')>Missing / zero price</option><option value="priced" @selected(request('pricing')==='priced')>Has a price</option></select></label>
    <label>Rows<select class="form-control" name="per_page">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',20)===$size)>{{ $size }}</option>@endforeach</select></label>
    <div class="valuation-actions"><button class="btn btn-primary" type="submit"><i class="fas fa-filter" aria-hidden="true"></i> Filter</button><a class="btn btn-outline-primary" href="{{ route('admin.reports.inventory-valuation') }}">Reset</a></div>
</form>
<div class="valuation-toolbar valuation-exports">
    <div class="valuation-actions"><button type="button" class="btn btn-outline-primary" data-valuation-toggle aria-expanded="false" aria-controls="valuation-column-picker">☷ Choose columns</button>
    @foreach(['xlsx'=>'Excel','csv'=>'CSV','pdf'=>'PDF'] as $format=>$label)<a class="btn btn-outline-primary" data-valuation-export href="{{ route('admin.reports.inventory-valuation.export',array_merge(request()->except(['page','format']),['format'=>$format,'columns'=>implode(',',$selected)])) }}">{{ $label }}</a>@endforeach</div>
    <small>Exports include all filtered records, selected columns and the grand total.</small>
</div>
<div id="valuation-column-picker" class="valuation-column-picker" hidden data-storage="mmc-valuation-columns-{{ auth()->id() }}">
    <div class="valuation-toolbar"><p>The six report columns stay visible. Choose extra product fields to append.</p><div class="valuation-actions"><button type="button" class="btn btn-sm btn-outline-primary" data-valuation-select="all">All columns</button><button type="button" class="btn btn-sm btn-outline-primary" data-valuation-select="clear">Clear extras</button></div></div>
    <div class="valuation-column-options">@foreach($extraLabels as $key=>$label)<label><input type="checkbox" value="{{ $key }}" data-valuation-check @checked(in_array($key,$selected))> {{ $label }}</label>@endforeach</div>
    <small>Extra calculated prices use saved calculator settings. Image columns export image links. PDF splits large column selections into readable sections.</small>
</div>
<section class="valuation-table-card">
    <div class="valuation-table-scroll"><table class="table table-hover table-bordered valuation-table">
    <thead><tr>@foreach(\App\Services\InventoryValuation::BASE_COLUMNS as $key=>$label)<th scope="col">{{ $label }}</th>@endforeach
    @foreach($extraLabels as $key=>$label)<th scope="col" data-valuation-column="{{ $key }}" @if(!in_array($key,$selected)) hidden @endif>{{ $label }}</th>@endforeach</tr>
    <tr class="valuation-header-filters">
        <th><input class="form-control form-control-sm" form="valuation-filters" type="number" min="1" name="record_id" value="{{ request('record_id') }}" aria-label="Filter record ID" placeholder="ID"></th>
        <th><input class="form-control form-control-sm" form="valuation-filters" name="code" value="{{ request('code') }}" aria-label="Filter product code" placeholder="Product code"></th>
        <th><input class="form-control form-control-sm" form="valuation-filters" name="product" value="{{ request('product') }}" aria-label="Filter product name" placeholder="Product name"></th>
        @foreach(['price'=>'supplier price','quantity'=>'inventory quantity','value'=>'total value'] as $field=>$label)<th><div class="valuation-range"><input class="form-control form-control-sm" form="valuation-filters" type="number" step="any" name="{{ $field }}_min" value="{{ request($field.'_min') }}" aria-label="Minimum {{ $label }}" placeholder="Min"><input class="form-control form-control-sm" form="valuation-filters" type="number" step="any" name="{{ $field }}_max" value="{{ request($field.'_max') }}" aria-label="Maximum {{ $label }}" placeholder="Max"></div></th>@endforeach
        @foreach($extraLabels as $key=>$label)<th data-valuation-column="{{ $key }}" @if(!in_array($key,$selected)) hidden @endif></th>@endforeach
    </tr></thead>
    <tbody>@forelse($products as $product)
    @php($values=$report->values($product))
    <tr>
        @foreach(\App\Services\InventoryValuation::BASE_COLUMNS as $key=>$label)
        <td @class(['valuation-number'=>$report->numeric($key),'valuation-missing-price'=>$key==='supplier_unit_price_eur' && $report->missingPrice($product)])>{{ $report->format($values[$key],$key) }}@if($key==='supplier_unit_price_eur' && $report->missingPrice($product))<small>Missing price</small>@endif</td>
        @endforeach
        @foreach($extraLabels as $key=>$label)<td data-valuation-column="{{ $key }}" @if(!in_array($key,$selected)) hidden @endif>
        @if($key==='image' && $values[$key])<img class="mmc-lazy-image" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" data-src="{{ $values[$key] }}" width="40" height="40" alt="{{ $product->premium_marketing_name }}" decoding="async">@else{{ $report->format($values[$key],$key) }}@endif
        </td>@endforeach
    </tr>
    @empty<tr><td colspan="6">No products match these filters.</td>@foreach($extraLabels as $key=>$label)<td data-valuation-column="{{ $key }}" @if(!in_array($key,$selected)) hidden @endif></td>@endforeach</tr>@endforelse</tbody>
    <tfoot><tr><th colspan="5">Grand total (EUR) · {{ $summary['records'] }} matching products</th><th class="valuation-number" data-valuation-grand-total>{{ $summary['total'] }}</th>@foreach($extraLabels as $key=>$label)<td data-valuation-column="{{ $key }}" @if(!in_array($key,$selected)) hidden @endif></td>@endforeach</tr></tfoot>
    </table></div>
    <footer><span>Showing {{ $products->firstItem()??0 }}–{{ $products->lastItem()??0 }} of {{ $products->total() }} products · <span data-valuation-column-count>{{ 6+count($selected) }}</span> visible columns</span>{{ $products->links('pagination::bootstrap-4') }}</footer>
</section>
<p class="valuation-note">Current stock valuation, captured when this page loads. Unconfigured quantities show “Not set” and contribute 0. Negative quantities retain their sign. No currency conversion is applied.</p>
<script src="{{ asset('admin-assets/inventory-valuation.js') }}?v={{ filemtime(public_path('admin-assets/inventory-valuation.js')) }}"></script>
@endsection
