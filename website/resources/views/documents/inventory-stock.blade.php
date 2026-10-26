<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Inventory Stock</title>
@include('documents.style')
<style>
@page{size:A4 landscape;margin:10mm}body{background:#fff;font-size:10px}.document{max-width:277mm;margin:18px auto;padding:0}.stock-section{page-break-before:always}.stock-section:first-child{page-break-before:auto}.stock-lines{font-size:9px}.stock-lines th:nth-child(n){width:auto}.stock-lines th.stock-name{width:24%}.stock-lines th.stock-photo{width:60px}.stock-lines td,.stock-lines th{padding:6px 5px;word-wrap:break-word}.stock-total{page-break-before:avoid;font-weight:bold;background:#f7f0e6}.stock-scope{font-size:10px}.stock-scope p{overflow-wrap:anywhere}.doc-info td{padding:9px}.doc-meta{width:40%}.doc-footer{font-size:9px;page-break-before:avoid}.doc-toolbar{max-width:277mm}@media print{.document{max-width:none;margin:0;padding:0}}
</style></head><body>
@if(!$pdf)<div class="doc-toolbar"><button type="button" onclick="window.print()">Print / Save as PDF</button><span>All matching products and selected columns are included.</span></div>@endif
<main class="document">
@foreach($groups as $headers)
<section class="stock-section">
@include('documents.header')
<table class="doc-info"><tr><td class="stock-scope"><h2>Stock scope</h2><p>{{ $doc['number'] }} · {{ $summary['records'] }} matching products</p>
<p>Product status: {{ isset($filters['active']) ? ((string)$filters['active']==='1'?'Active only':'Inactive only') : 'Active and inactive' }}.</p>
<p>Quantity setup: {{ ['set'=>'Quantity set (including zero)','unset'=>'Not set only'][$filters['quantity_setup']??''] ?? (['tracked'=>'Quantity set (including zero)','unset'=>'Not set only'][$filters['stock']??''] ?? 'All quantities') }}.</p>
@if(filled($filters['stock']??null))<p>Stock condition: {{ ['tracked'=>'Tracked stock','unset'=>'Opening stock not set','healthy'=>'Healthy','low'=>'Low stock','out'=>'Out of stock','shortage'=>'Shortage'][$filters['stock']] }}.</p>@endif
@foreach(\Illuminate\Support\Arr::only($filters,['q','product','code','record_id','category','uom','quantity_min','quantity_max','threshold_min','threshold_max']) as $key=>$value)@if($value!==null && $value!=='')<p>{{ str_replace('_',' ',ucfirst($key)) }}: {{ $value }}</p>@endif @endforeach
@if(count($groups)>1)<p>Column section {{ $loop->iteration }} of {{ count($groups) }}. A selected identifier and current stock repeat for reference.</p>@endif
</td><td><h2>Filtered stock totals</h2><p>Total stock quantity: <strong>{{ $summary['quantity'] }}</strong></p><p>Quantity × 5: <strong>{{ $summary['quantity_times_five'] }}</strong></p><p>{{ $summary['unset_quantities'] }} quantities not set. Totals cover all matching products across all pages.</p></td></tr></table>
@php($widths=$columns->printWidths($headers))
<table class="doc-lines stock-lines"><thead><tr>@foreach($headers as $key=>$label)<th style="width:{{ $widths[$key] }}%" @class(['money'=>$columns->numeric($key),'stock-name'=>$key==='premium_marketing_name','stock-photo'=>$key==='image'])>{{ $label }}</th>@endforeach</tr></thead><tbody>
@forelse($rows as $row)<tr>@foreach($headers as $key=>$label)<td @class(['money'=>$columns->numeric($key)])>@if($key==='image')@if($row[$key])<img class="doc-product-image" src="{{ $row[$key] }}" width="44" height="44" alt="{{ $row['premium_marketing_name'] }}">@else<span class="doc-product-no-image">No image</span>@endif @else{{ $columns->format($row[$key],$key) }}@endif</td>@endforeach</tr>
@empty<tr><td colspan="{{ count($headers) }}">No products match these filters.</td></tr>@endforelse
<tr class="stock-total"><td colspan="{{ count($headers)-1 }}">Total stock quantity · all matching products</td><td class="money">{{ $summary['quantity'] }}</td></tr>
</tbody></table>
<footer class="doc-footer"><strong>Thank you for choosing {{ $doc['brand']['name'] }}.</strong><p>Current stock as of {{ app(\App\Services\DocumentTheme::class)->dateLabel($doc['date']) }}. Quantities not set contribute 0 to totals; saved zero and negative quantities retain their values.</p></footer>
</section>
@endforeach
</main></body></html>
