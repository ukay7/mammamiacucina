<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Supplier Order {{ $order->number }}</title>
@include('documents.style')
<style>
@page{size:A4 {{ $landscape?'landscape':'portrait' }}}
body{background:white}.document{margin:0;padding:0;max-width:none}
.supplier-section+.supplier-section{page-break-before:always}
.supplier-lines{font-size:10px;line-height:1.35}.supplier-lines th{font-size:9px}
.supplier-lines th:nth-child(n){width:auto}.supplier-lines td,.supplier-lines th{padding:4px;word-wrap:break-word}
.supplier-notes{white-space:pre-line;word-wrap:break-word}.supplier-total{font-weight:bold}
.supplier-section .doc-footer{page-break-inside:avoid}
</style></head><body><main class="document">
@foreach($groups as $columns)<section class="supplier-section">
@include('documents.header')
<table class="doc-info"><tr><td><h2>Supplier</h2><div class="doc-billing-name">{{ $order->supplier_snapshot['name'] ?? '—' }}</div>
@foreach(['contact_name','address','phone','email'] as $key)@if(filled($order->supplier_snapshot[$key]??null))<div class="supplier-notes">{{ $order->supplier_snapshot[$key] }}</div>@endif @endforeach
</td><td><h2>Order details</h2><div>Status: {{ \App\Models\SupplierOrder::STATUS_LABELS[$order->status] }}</div><div>{{ count($rows) }} ordered {{ \Illuminate\Support\Str::plural('product',count($rows)) }}</div><div>Stock view when ordered: {{ $order->warehouse_name }}</div>
@if(count($groups)>1)<div>Column section {{ $loop->iteration }} of {{ count($groups) }}. Selected identifiers repeat to match the rows.</div>@endif
</td></tr></table>
@if($order->status==='deleted')<p class="doc-notice"><strong>DELETED ORDER - historical reference only.</strong> {{ $order->deletion_reason }}</p>@endif
@php($weights=['serial'=>6,'product_id'=>8,'product_code'=>12,'barcode'=>12,'name'=>24,'original_description'=>20,'manufacturer'=>20,'allocated_to'=>18,'supplier'=>22])
@php($totalWeight=array_sum(array_map(fn($key)=>$weights[$key]??12,$columns)))
<table class="doc-lines supplier-lines"><thead><tr>@foreach($columns as $key)<th style="width:{{ 100*($weights[$key]??12)/$totalWeight }}%">{{ $labels[$key] }}</th>@endforeach</tr></thead><tbody>
@forelse($rows as $row)<tr>@foreach($columns as $key)<td>{{ $row[$key] }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($columns) }}">No ordered products.</td></tr>@endforelse
</tbody></table>
@if(in_array('line_total',$columns,true))<table class="doc-totals">@foreach($order->totals as $currency=>$amount)<tr class="supplier-total"><td>Order total ({{ $currency }})</td><td class="money">{{ $currency }} {{ $amount }}</td></tr>@endforeach</table>@endif
@if(filled($order->notes))<section class="doc-box"><h2>Order notes</h2><p class="supplier-notes">{{ $order->notes }}</p></section>@endif
<footer class="doc-footer"><strong>Thank you from {{ $doc['brand']['name'] }}.</strong>@foreach($notes as $note)<p>{{ $note }}</p>@endforeach<p>EUR and CAD are shown separately; no currency conversion is applied. Receiving and inventory updates are recorded separately.</p></footer>
</section>@endforeach
</main></body></html>
