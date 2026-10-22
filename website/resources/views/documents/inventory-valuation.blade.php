<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Inventory Valuation</title>
@include('documents.style')
<style>
@page{size:A4 landscape;margin:10mm}body{background:#fff;font-size:9px}.document{max-width:none;margin:0;padding:0}.valuation-section{page-break-before:always}.valuation-section:first-child{page-break-before:auto}.doc-lines{width:100%;table-layout:fixed;font-size:8px}.doc-lines th,.doc-lines td{padding:6px 4px;word-wrap:break-word;white-space:normal}.doc-lines th:nth-child(n){width:auto;font-size:8px}.doc-lines th:first-child{width:5%}.doc-lines th:nth-child(2){width:10%}.doc-lines th:nth-child(3){width:19%}.doc-lines th:nth-child(4),.doc-lines th:nth-child(5),.doc-lines th:nth-child(6){width:10%}.doc-lines .missing-price{background:#fde2e5;color:#a00020}.doc-lines small{font-size:7px}.valuation-total{font-weight:bold;background:#f7f0e6}.doc-header td{padding-bottom:8px}.doc-header{margin-bottom:10px}.doc-info td{padding:7px}.doc-footer{margin-top:12px;font-size:8px}
</style></head><body><main class="document">
@foreach($groups as $group)
<section class="valuation-section">
@include('documents.header')
<table class="doc-info"><tr><td><h2>Stock scope</h2><div>{{ $warehouse }}</div><div>{{ $summary['records'] }} matching products · {{ $summary['missing_prices'] }} missing prices</div>@if(count($groups)>1)<div>Column section {{ $loop->iteration }} of {{ count($groups) }}. Identity and valuation columns repeat for reference.</div>@endif</td><td><h2>Valuation basis</h2><div>Supplier unit price (EUR) × current on-hand quantity.</div><div>Missing prices contribute 0 and are highlighted red.</div><div>Grand total (EUR): <strong>{{ $summary['total'] }}</strong></div></td></tr></table>
@if(array_filter(\Illuminate\Support\Arr::except($filters,['per_page','warehouse'])))<p class="doc-notice">Filters: @foreach(array_filter(\Illuminate\Support\Arr::except($filters,['per_page','warehouse']),fn($value)=>$value!==null && $value!=='') as $key=>$value){{ str_replace('_',' ',ucfirst($key)) }}: {{ $value }}{{ !$loop->last?' · ':'' }}@endforeach</p>@endif
@php($sectionHeaders=$report->headers($group))
@php($widths=count($group)?['record_id'=>5,'product_code'=>10,'premium_marketing_name'=>20,'supplier_unit_price_eur'=>10,'inventory_quantity'=>10,'inventory_value'=>12]:['record_id'=>6,'product_code'=>12,'premium_marketing_name'=>28,'supplier_unit_price_eur'=>18,'inventory_quantity'=>16,'inventory_value'=>20])
<table class="doc-lines"><thead><tr>@foreach($sectionHeaders as $key=>$label)<th style="width:{{ $widths[$key]??(33/max(1,count($group))) }}%" @class(['money'=>$report->numeric($key)])>{{ $label }}</th>@endforeach</tr></thead><tbody>
@forelse($rows as $row)<tr>@foreach($sectionHeaders as $key=>$label)<td @class(['money'=>$report->numeric($key),'missing-price'=>$key==='supplier_unit_price_eur' && $row['missing']])>
@if($key==='image' && $row['values'][$key])<a href="{{ $row['values'][$key] }}">View image</a>@else{{ $report->format($row['values'][$key],$key) }}@endif
@if($key==='supplier_unit_price_eur' && $row['missing'])<br><small>Missing price</small>@endif</td>@endforeach</tr>@empty<tr><td colspan="{{ count($sectionHeaders) }}">No products match these filters.</td></tr>@endforelse
<tr class="valuation-total"><td colspan="5">Grand total (EUR) · all matching products</td><td class="money">{{ $summary['total'] }}</td>@foreach($group as $key)<td></td>@endforeach</tr>
</tbody></table>
<footer class="doc-footer"><strong>Thank you for choosing {{ $doc['brand']['name'] }}.</strong><p>Inventory valuation as of {{ $doc['date'] }}. Unconfigured quantities contribute 0; negative quantities retain their sign. No currency conversion is applied. This report describes current stock value.</p></footer>
</section>@endforeach
</main></body></html>
