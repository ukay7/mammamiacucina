<style>
.product-grid-scroll{max-height:65vh;overflow:auto;margin-bottom:18px;border:1px solid #e6d6bb}.product-grid-scroll .table{margin-bottom:0}[data-product-grid] th:last-child{right:0;z-index:3}[data-product-grid] td:last-child{position:sticky;right:0;background:#fffaf2;z-index:2;box-shadow:-2px 0 5px #0000000a}
.product-column-picker{border:1px solid #dfc9a4;background:#fffaf2;border-radius:10px;padding:20px;margin-bottom:14px}
.product-column-presets{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px}
.product-column-options{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px 20px}
.product-column-options label{display:flex;align-items:flex-start;gap:9px;margin:0;cursor:pointer;font-size:13px}
.product-column-options input{accent-color:#a00018;margin-top:3px;flex-shrink:0}
[data-product-column][hidden],.product-column-picker[hidden]{display:none!important}
[data-product-grid] th{min-width:140px;vertical-align:top;background:#f1e7d6;position:sticky;top:0;z-index:1}
[data-product-grid] td{min-width:140px;max-width:320px;overflow-wrap:anywhere;vertical-align:middle}
[data-product-grid] th:first-child,[data-product-grid] td:first-child{min-width:45px}
@media(max-width:1100px){.product-column-options{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:650px){.product-column-options{grid-template-columns:repeat(2,minmax(0,1fr))}.product-column-picker{padding:12px}}
</style>
<div class="product-column-picker" id="product-column-picker" hidden data-column-storage="mmc-product-columns-{{ auth()->id() }}" data-presets="{{ json_encode($columnPresets) }}">
<div class="product-column-presets">
@foreach($columnPresets as $preset=>$keys)<button type="button" class="btn btn-sm btn-outline-primary" data-column-preset="{{ $preset }}">{{ $preset }}</button>@endforeach
<button type="button" class="btn btn-sm btn-outline-primary" data-column-preset="all">All columns</button><button type="button" class="btn btn-sm btn-outline-primary" data-column-preset="clear">Clear selection</button>
</div>
<div class="product-column-options">@foreach($columnLabels as $key=>$label)<label><input type="checkbox" data-column-check value="{{ $key }}" @checked(in_array($key,$selectedColumns))>{{ $label }}</label>@endforeach</div>
</div>
<p class="text-muted"><span>{{ $products->total() }} matching records · {{ $products->count() }} on this page · </span><span data-column-count>{{ count($selectedColumns) }}</span> selected columns</p>
<p class="text-muted small">Calculated columns use saved calculator settings and may differ from the current selling price. Missing values show —. Excel exports image links; the table displays thumbnails.</p>
