@extends('admin.layout')
@section('title','Inventory Valuation')
@section('page-class','inventory-valuation-page')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/inventory-valuation.css') }}?v={{ filemtime(public_path('admin-assets/inventory-valuation.css')) }}">
<div class="valuation-toolbar"><p>Discounted supplier cost per carton × cartons in stock. EUR and CAD are valued separately.</p><a class="btn btn-outline-primary" href="{{ route('admin.inventory.index') }}">Manage inventory</a></div>
<div class="valuation-summary">
    @foreach($summary['currencies'] as $currency=>$group)
    <div class="valuation-currency-card"><span>Grand total ({{ $currency }})</span><strong>{{ $currency==='EUR'?'€':'C$' }}{{ number_format((float)$group['total'],2) }}</strong><small>{{ $group['products'] }} {{ \Illuminate\Support\Str::plural('product',$group['products']) }} @if($group['needs_setup']) · {{ $group['needs_setup'] }} excluded (needs setup) @endif</small></div>
    @endforeach
    <aside class="valuation-health" aria-label="Report data checks">
        <div><span>Matching products</span><strong>{{ $summary['records'] }}</strong></div>
        <div><span>Missing supplier price</span><strong>{{ $summary['missing_prices'] }}</strong></div>
        <div><span>Inventory not set</span><strong>{{ $summary['unset_quantities'] }}</strong></div>
        <div><span>Needs setup · excluded</span><strong>{{ $summary['needs_setup'] }}</strong></div>
    </aside>
</div>
<form id="valuation-filters" class="valuation-filters" method="get" data-valuation-filters>
    <input type="hidden" name="columns" value="{{ implode(',',$selected) }}">
    <label>Search products<input class="form-control" name="q" value="{{ request('q') }}" placeholder="Name, product code, barcode, ID or supplier"></label>
    <label>Category<select class="form-control" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category')==$category->id)>{{ $category->name }}</option>@endforeach</select></label>
    <label>Warehouse<select class="form-control" name="warehouse"><option value="">All warehouses combined</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(request('warehouse')==$warehouse->id)>{{ $warehouse->name }}{{ !$warehouse->is_active ? ' (inactive)' : '' }}</option>@endforeach</select></label>
    <label>Product status<select class="form-control" name="active"><option value="">Active and inactive</option><option value="1" @selected(request('active')==='1')>Active</option><option value="0" @selected(request('active')==='0')>Inactive</option></select></label>
    <label>Purchase currency<select class="form-control" name="currency"><option value="">All currencies</option>@foreach(['EUR'=>'EUR','CAD'=>'CAD','unknown'=>'Missing / unsupported'] as $key=>$label)<option value="{{ $key }}" @selected(request('currency')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label>Valuation setup<select class="form-control" name="setup"><option value="">All products</option><option value="ready" @selected(request('setup')==='ready')>Setup complete</option><option value="needs_setup" @selected(request('setup')==='needs_setup')>Needs setup (excluded)</option></select></label>
    <label>Stock condition<select class="form-control" name="stock"><option value="">All inventory</option>@foreach(['tracked'=>'Tracked stock','unset'=>'Opening stock not set','healthy'=>'Healthy','low'=>'Low stock','out'=>'Out of stock','shortage'=>'Shortage / oversold'] as $key=>$label)<option value="{{ $key }}" @selected(request('stock')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label>Supplier price<select class="form-control" name="pricing"><option value="">All prices</option><option value="missing" @selected(request('pricing')==='missing')>Missing / zero price</option><option value="priced" @selected(request('pricing')==='priced')>Has a price</option></select></label>
    <label>Rows<select class="form-control" name="per_page">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',20)===$size)>{{ $size }}</option>@endforeach</select></label>
    <div class="valuation-actions"><button class="btn btn-primary" type="submit"><i class="fas fa-filter" aria-hidden="true"></i> Filter</button><a class="btn btn-outline-primary" href="{{ route('admin.reports.inventory-valuation') }}">Reset</a></div>
</form>
<div class="valuation-toolbar valuation-exports">
    <div class="valuation-actions"><button type="button" class="btn btn-outline-primary" data-valuation-toggle aria-expanded="false" aria-controls="valuation-column-picker">☷ Choose columns</button>
    @foreach(['xlsx'=>'Excel','csv'=>'CSV','pdf'=>'PDF'] as $format=>$label)<a class="btn btn-outline-primary" data-valuation-export href="{{ route('admin.reports.inventory-valuation.export',array_merge(request()->except(['page','format']),['format'=>$format,'columns'=>implode(',',$selected)])) }}">{{ $label }}</a>@endforeach</div>
    <small>Exports include all filtered records, selected columns and separate EUR / CAD totals.</small>
</div>
<div id="valuation-column-picker" class="valuation-column-picker" hidden data-base-count="{{ count(\App\Services\InventoryValuation::BASE_COLUMNS) }}" data-storage="mmc-valuation-columns-{{ auth()->id() }}">
    <div class="valuation-toolbar"><p>The calculation columns stay visible. Choose extra product fields to append.</p><div class="valuation-actions"><button type="button" class="btn btn-sm btn-outline-primary" data-valuation-select="all">All columns</button><button type="button" class="btn btn-sm btn-outline-primary" data-valuation-select="clear">Clear extras</button></div></div>
    <div class="valuation-column-options">@foreach($extraLabels as $key=>$label)<label><input type="checkbox" value="{{ $key }}" data-valuation-check @checked(in_array($key,$selected))> {{ $label }}</label>@endforeach</div>
    <small>Extra calculated prices use saved calculator settings. Image columns export image links. PDF splits large column selections into readable sections.</small>
</div>
<div class="valuation-calculation" aria-label="How inventory value is calculated"><span><b>1</b> Quoted price − supplier discount</span><span><b>2</b> × pieces per carton <small>(Piece) or × 1 (Carton)</small></span><span><b>3</b> × inventory quantity = total value</span></div>
<p class="valuation-scroll-hint">Scroll across the table to follow the full calculation. All prices use the product’s purchase currency.</p>
<section class="valuation-table-card">
    <div class="valuation-table-scroll"><table class="table table-hover table-bordered valuation-table {{ $selected?'':'valuation-base-only' }}">
    <thead><tr>@foreach(\App\Services\InventoryValuation::BASE_COLUMNS as $key=>$label)<th scope="col" data-valuation-field="{{ $key }}">{{ $label }}</th>@endforeach
    @foreach($extraLabels as $key=>$label)<th scope="col" data-valuation-column="{{ $key }}" @if(!in_array($key,$selected)) hidden @endif>{{ $label }}</th>@endforeach</tr>
    <tr class="valuation-header-filters">
        <th><input class="form-control form-control-sm" form="valuation-filters" type="number" min="1" name="record_id" value="{{ request('record_id') }}" aria-label="Filter record ID" placeholder="ID"></th>
        <th><input class="form-control form-control-sm" form="valuation-filters" name="code" value="{{ request('code') }}" aria-label="Filter product code" placeholder="Code"></th>
        <th><input class="form-control form-control-sm" form="valuation-filters" name="product" value="{{ request('product') }}" aria-label="Filter product name" placeholder="Product name"></th>
        @foreach(['currency','purchase_basis','purchase_price','discount','discounted_price','pieces_per_carton'] as $key)<th></th>@endforeach
        @foreach(['price'=>'cost per carton','quantity'=>'inventory quantity','value'=>'total value'] as $field=>$label)<th><div class="valuation-range"><input class="form-control form-control-sm" form="valuation-filters" type="number" step="any" name="{{ $field }}_min" value="{{ request($field.'_min') }}" aria-label="Minimum {{ $label }}" placeholder="Min"><input class="form-control form-control-sm" form="valuation-filters" type="number" step="any" name="{{ $field }}_max" value="{{ request($field.'_max') }}" aria-label="Maximum {{ $label }}" placeholder="Max"></div></th>@endforeach
        <th></th>
        @foreach($extraLabels as $key=>$label)<th data-valuation-column="{{ $key }}" @if(!in_array($key,$selected)) hidden @endif></th>@endforeach
    </tr></thead>
    <tbody>@forelse($products as $product)
    @php($values=$report->values($product))
    <tr>
        @foreach(\App\Services\InventoryValuation::BASE_COLUMNS as $key=>$label)
        <td data-valuation-field="{{ $key }}" @class(['valuation-number'=>$report->numeric($key),'valuation-missing-price'=>in_array($key,['purchase_price','discounted_price','carton_cost']) && $report->missingPrice($product)])>
        @if($key==='valuation_status')<span @class(['valuation-state','valuation-needs-setup'=>!empty($product->valuation['issues'])])>{{ $product->valuation['valuation_status'] }}</span>@if($product->valuation['issues'])<small class="valuation-setup-reason">{{ implode(' ',$product->valuation['issues']) }}</small>@endif
        @else{{ $report->format($values[$key],$key) }}@endif
        @if($key==='pieces_per_carton')<small class="valuation-multiplier">Cost multiplier: × {{ $values['purchase_basis']==='Carton'?'1':($values['purchase_basis']==='Piece'?$report->format($values[$key],$key):'—') }}</small>@endif
        @if($key==='carton_cost' && $report->missingPrice($product))<small>Missing price</small>@endif</td>
        @endforeach
        @foreach($extraLabels as $key=>$label)<td data-valuation-column="{{ $key }}" @if(!in_array($key,$selected)) hidden @endif>
        @if($key==='image' && $values[$key])<img class="mmc-lazy-image" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" data-src="{{ $values[$key] }}" width="40" height="40" alt="{{ $product->premium_marketing_name }}" decoding="async">@else{{ $report->format($values[$key],$key) }}@endif
        </td>@endforeach
    </tr>
    @empty<tr><td colspan="{{ count(\App\Services\InventoryValuation::BASE_COLUMNS) }}">No products match these filters.</td>@foreach($extraLabels as $key=>$label)<td data-valuation-column="{{ $key }}" @if(!in_array($key,$selected)) hidden @endif></td>@endforeach</tr>@endforelse</tbody>
    <tfoot>@foreach($summary['currencies'] as $currency=>$group)<tr><th colspan="{{ count(\App\Services\InventoryValuation::BASE_COLUMNS)-2 }}">Grand total ({{ $currency }}) · {{ $group['products'] }} matching {{ \Illuminate\Support\Str::plural('product',$group['products']) }}</th><th class="valuation-number" data-valuation-grand-total="{{ $currency }}">{{ $group['total'] }}</th><td></td>@foreach($extraLabels as $key=>$label)<td data-valuation-column="{{ $key }}" @if(!in_array($key,$selected)) hidden @endif></td>@endforeach</tr>@endforeach</tfoot>
    </table></div>
    <footer><span>Showing {{ $products->firstItem()??0 }}–{{ $products->lastItem()??0 }} of {{ $products->total() }} products · <span data-valuation-column-count>{{ count(\App\Services\InventoryValuation::BASE_COLUMNS)+count($selected) }}</span> visible columns</span>{{ $products->links('pagination::bootstrap-4') }}</footer>
</section>
<p class="valuation-note">Piece basis: discounted quoted price × pieces per carton. Carton basis: discounted quoted price × 1. Each carton cost is multiplied by inventory quantity; line values are rounded to two decimals before totaling. Missing prices contribute 0 and are highlighted red. Missing currency, unsupported price bases or incomplete carton setup are marked “Needs setup” and excluded. Unconfigured inventory contributes 0; negative quantities retain their sign. No currency conversion is applied.</p>
<script src="{{ asset('admin-assets/inventory-valuation.js') }}?v={{ filemtime(public_path('admin-assets/inventory-valuation.js')) }}"></script>
@endsection
