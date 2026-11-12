<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $list->title }} — {{ $list->number }}</title>
@include('documents.style')
<style>
.price-document{max-width:210mm;padding:12mm;font-size:10px}.price-document .doc-header{margin-bottom:8px}.price-document .doc-company{font-size:15px}.price-document .doc-meta strong{font-size:17px}.price-document .doc-reference{font-size:16px}
.price-caption{padding:9px 12px;background:#f7f0e6;border-radius:6px;margin-bottom:10px}.price-caption h1{font-size:17px;margin:0 0 3px}.price-caption span{margin-right:16px}
.price-lines th:nth-child(1){width:12%}.price-lines th:nth-child(2){width:43%}.price-lines th:nth-child(3){width:21%}.price-lines th:nth-child(4),.price-lines th:nth-child(5){width:12%}
.price-lines{width:100%;border-collapse:collapse;table-layout:fixed;line-height:1.25}.price-lines thead{display:table-header-group}.price-lines th{background:#443b35;color:white;font-size:9px;text-transform:uppercase;letter-spacing:.6px;padding:6px 4px;text-align:left}.price-lines td{padding:4px;border-bottom:1px solid #eee7db;vertical-align:middle;word-wrap:break-word}.price-lines tr{page-break-inside:avoid}.price-lines .price-category td{border-left:3px solid #b98c45;border-bottom:1px solid #dfcfb7;padding:7px 5px 5px;font-weight:bold;text-transform:uppercase;background:#faf6ef;letter-spacing:.4px}.price-category{page-break-after:avoid}.price-photo{width:22px;height:22px;vertical-align:middle;margin-right:6px}.price-product-name{vertical-align:middle}.price-box{font-weight:bold}.price-pack{color:#776b5d}.price-notes{white-space:pre-line;overflow-wrap:anywhere}.price-document .doc-footer{margin-top:12px;padding-top:8px}.price-document .doc-registration{margin-top:8px}
@media print{.price-document{padding:0;margin:0;max-width:none}}
@if($pdf ?? false) body{background:#fff}.price-document{padding:0;margin:0;max-width:none} @endif
</style></head><body>
@php($doc=['brand'=>$list->brand_snapshot,'title'=>'Price List','number'=>$list->number,'date'=>$list->effective_date->format('Y-m-d')])
@php($money=fn($cents)=>$cents===null?'—':'$'.number_format($cents/100,2))
<main class="document price-document">
@include('documents.header')
<div class="price-caption"><h1>{{ $list->title }}</h1>
@if($list->recipient_name)<p><strong>Prepared for:</strong> {{ $list->recipient_name }}@if($list->customer_id) · Customer #{{ $list->customer_id }}@endif</p>@endif
<span>{{ ucfirst($list->pricing_tier) }} pricing · CAD</span><span>Prices as of {{ $list->effective_date->format('F j, Y') }}</span>
@if($list->valid_until)<span>Valid through {{ $list->valid_until->format('F j, Y') }}</span>@endif
</div>
<table class="price-lines"><colgroup><col style="width:12%"><col style="width:43%"><col style="width:21%"><col style="width:12%"><col style="width:12%"></colgroup>
<thead><tr><th>Code</th><th>Product</th><th>Pack</th><th class="money">Each</th><th class="money">Box</th></tr></thead><tbody>
@foreach(collect($list->items)->groupBy('category') as $category=>$items)
<tr class="price-category"><td colspan="5">{{ $category }}</td></tr>
@foreach($items as $item)<tr data-product-id="{{ $item['product_id'] }}"><td>{{ $item['code'] }}</td><td>@if($list->show_images && $item['image'])<img class="price-photo" src="{{ $item['image'] }}" alt="">@endif<span class="price-product-name">{{ $item['name'] }}</span></td><td class="price-pack">{{ $item['pack'] }}</td><td class="money">{{ $money($item['each_cents']) }}</td><td class="money price-box">{{ $money($item['box_cents']) }}</td></tr>@endforeach
@endforeach
</tbody></table>
<footer class="doc-footer">
@if($list->notes)<p class="price-notes">{{ $list->notes }}</p>@endif
<p><strong>Orders &amp; current pricing</strong> · {{ $doc['brand']['phone'] }} · <!--email_off-->{{ $doc['brand']['email'] }}<!--/email_off--></p>
<p>All prices in CAD. — indicates a price not specified; please contact us. Confirm availability, applicable taxes and delivery charges when ordering.</p>
<p>Thank you for choosing {{ rtrim($doc['brand']['name'],'.') }}.</p>
@include('documents.registration')
</footer></main></body></html>
