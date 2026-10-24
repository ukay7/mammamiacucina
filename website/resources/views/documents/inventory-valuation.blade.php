<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Inventory Valuation</title>
@include('documents.style')
<style>
@page{size:A4 landscape;margin:10mm}body{background:#fff;font-size:9px}.document{max-width:none;margin:0;padding:0}.valuation-section{page-break-before:always}.valuation-section:first-child{page-break-before:auto}.doc-lines{width:100%;table-layout:fixed;font-size:8px}.doc-lines th,.doc-lines td{padding:6px 4px;word-wrap:break-word;white-space:normal}.doc-lines th:nth-child(n){width:auto;font-size:8px}.doc-lines th:first-child{width:5%}.doc-lines th:nth-child(2){width:10%}.doc-lines th:nth-child(3){width:19%}.doc-lines th:nth-child(4),.doc-lines th:nth-child(5),.doc-lines th:nth-child(6){width:10%}.doc-lines .missing-price{background:#fde2e5;color:#a00020}.doc-lines small{font-size:7px}.valuation-total{font-weight:bold;background:#f7f0e6}.doc-header td{padding-bottom:8px}.doc-header{margin-bottom:10px}.doc-info td{padding:7px}.doc-footer{margin-top:12px;font-size:8px}
</style></head><body><main class="document">
@foreach($groups as $group)
<section class="valuation-section">
@include('documents.header')
<table class="doc-info"><tr>@foreach($summary['currencies'] as $currency=>$currencyGroup)<td><h2>Grand total ({{ $currency }})</h2><strong>{{ $currency }} {{ $currencyGroup['total'] }}</strong><div>{{ $currencyGroup['products'] }} {{ \Illuminate\Support\Str::plural('product',$currencyGroup['products']) }} · {{ $currencyGroup['needs_setup'] }} excluded (needs setup)</div></td>@endforeach</tr>
<tr><td><h2>Stock scope</h2><div>{{ $warehouse }}</div><div>{{ $summary['records'] }} matching products · {{ $summary['missing_prices'] }} missing prices · {{ $summary['unset_quantities'] }} inventory not set · {{ $summary['needs_setup'] }} need setup</div>@if(count($groups)>1)<div>Column section {{ $loop->iteration }} of {{ count($groups) }}. Identity and valuation columns repeat for reference.</div>@endif</td><td><h2>Valuation basis</h2><div>Piece: discounted quoted price × pieces per carton.</div><div>Carton: discounted quoted price × 1.</div><div>Inventory value = cost per carton × cartons in stock.</div><div>Missing prices contribute 0 (red); Needs setup is excluded.</div></td></tr></table>
@if(array_filter(\Illuminate\Support\Arr::except($filters,['per_page','warehouse'])))<p class="doc-notice">Filters: @foreach(array_filter(\Illuminate\Support\Arr::except($filters,['per_page','warehouse']),fn($value)=>$value!==null && $value!=='') as $key=>$value){{ str_replace('_',' ',ucfirst($key)) }}: {{ $value }}{{ !$loop->last?' · ':'' }}@endforeach</p>@endif
<h2>{{ $group['title'] }}</h2>
@php($sectionHeaders=array_replace(array_flip($group['columns']),array_intersect_key($headers,array_flip($group['columns']))))
@php($widths=['record_id'=>4,'product_code'=>8,'premium_marketing_name'=>18,'currency'=>7,'valuation_status'=>20])
<table class="doc-lines"><thead><tr>@foreach($sectionHeaders as $key=>$label)<th style="width:{{ $widths[$key]??10 }}%" @class(['money'=>$report->numeric($key)])>{{ $label }}</th>@endforeach</tr></thead><tbody>
@forelse($rows as $row)<tr>@foreach($sectionHeaders as $key=>$label)<td @class(['money'=>$report->numeric($key),'missing-price'=>in_array($key,['purchase_price','discounted_price','carton_cost']) && $row['missing']])>
@if($key==='image' && $row['values'][$key])<a href="{{ $row['values'][$key] }}">View image</a>@else{{ $report->format($row['values'][$key],$key) }}@endif
@if($key==='premium_marketing_name' && !isset($sectionHeaders['valuation_status']) && str_starts_with($row['values']['valuation_status'],'Needs setup'))<br><small>{{ $row['values']['valuation_status'] }}</small>@endif
@if($key==='pieces_per_carton')<br><small>Cost multiplier: {{ $row['values']['purchase_basis']==='Carton'?'1':($row['values']['purchase_basis']==='Piece'?$report->format($row['values'][$key],$key):'—') }}</small>@endif
@if($key==='carton_cost' && $row['missing'])<br><small>Missing price</small>@endif</td>@endforeach</tr>@empty<tr><td colspan="{{ count($sectionHeaders) }}">No products match these filters.</td></tr>@endforelse
@if(isset($sectionHeaders['inventory_value']))
@foreach($summary['currencies'] as $currency=>$totals)
<tr class="valuation-total">
    <td colspan="6">Grand total ({{ $currency }}) · {{ $totals['products'] }} matching {{ \Illuminate\Support\Str::plural('product',$totals['products']) }}</td>
    <td class="money">{{ $totals['total'] }}</td><td></td>
    @foreach(array_slice($group['columns'],8) as $key)<td></td>@endforeach
</tr>
@endforeach
@endif
</tbody></table>
<footer class="doc-footer"><strong>Thank you for choosing {{ $doc['brand']['name'] }}.</strong><p>Inventory valuation as of {{ $doc['date'] }}. Line values are rounded to two decimals before totaling. Products marked Needs setup are excluded from totals. Unconfigured quantities contribute 0; negative quantities retain their sign. EUR and CAD stay separate; no currency conversion is applied.</p></footer>
</section>@endforeach
</main></body></html>
