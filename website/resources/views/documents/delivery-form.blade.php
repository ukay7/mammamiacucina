<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Delivery Form {{ $doc['number'] }} - {{ $doc['brand']['name'] }}</title>
@include('documents.style')
<style>
.doc-delivery-form .doc-lines th:nth-child(1){width:5%}.doc-delivery-form .doc-lines th:nth-child(2){width:6%}.doc-delivery-form .doc-lines th:nth-child(3){width:12%}.doc-delivery-form .doc-lines th:nth-child(4){width:29%}.doc-delivery-form .doc-lines th:nth-child(5){width:13%}.doc-delivery-form .doc-lines th:nth-child(6){width:13%}.doc-delivery-form .doc-lines th:nth-child(7){width:15%}.doc-delivery-form .doc-lines th:nth-child(8){width:7%}
.delivery-name{font-size:15px;font-weight:bold}.delivery-line{white-space:pre-line;word-wrap:break-word}.delivery-meta{margin:10px 0;color:#645346}.delivery-quantity{text-align:right!important;font-weight:bold}.doc-delivery-form .doc-footer{margin-top:16px}.delivery-schedule{font-weight:bold;margin-top:7px}.doc-delivery-form .doc-info td{word-wrap:break-word}
.doc-delivery-form .doc-lines{font-size:9px;line-height:1.35}.doc-delivery-form .doc-lines th{font-size:8px}.doc-delivery-form .doc-lines td,.doc-delivery-form .doc-lines th{padding:5px 4px;word-wrap:break-word;vertical-align:middle}.doc-delivery-form .doc-product-image{width:24px;height:24px;border-radius:2px}.doc-delivery-form .doc-product-no-image{width:24px;min-height:24px;font-size:6px;padding-top:5px;border-radius:2px}.delivery-number{text-align:center}.delivery-code{font-size:8px}
.doc-delivery-form .doc-info td{padding:10px;font-size:10px;line-height:1.4}.doc-delivery-form .doc-info{margin-bottom:10px}.doc-delivery-form .doc-box{padding:10px}.doc-delivery-form .doc-footer{margin-top:12px;padding-top:7px}
.delivery-completion{page-break-inside:avoid}
@if(!empty($pdf))body{background:#fff}.document{margin:0;padding:0;max-width:none}.doc-toolbar{display:none}@endif
</style></head><body>
@if(empty($pdf))<div class="doc-toolbar"><a href="{{ $backUrl }}">Back to order</a><button onclick="window.print()">Print</button><a href="{{ $downloadUrl }}">Download PDF</a></div>@endif
<main class="document doc-delivery-form">
@include('documents.header')
<table class="doc-info"><tr><td><h2>Customer</h2>
@foreach($doc['identity'] as $line)<div class="delivery-line {{ $loop->first?'delivery-name':'' }}">{{ $line }}</div>@endforeach
@foreach($doc['billing_address'] as $line)<div class="delivery-line">{{ $line }}</div>@endforeach
@foreach($doc['contact'] as $line)<div class="delivery-line">{{ $line }}</div>@endforeach
</td><td><h2>{{ $doc['destination_label'] }}</h2>
@forelse($doc['destination'] as $line)<div class="delivery-line">{{ $line }}</div>@empty<div>Address not recorded</div>@endforelse
@if($doc['schedule'])<p class="delivery-schedule">Delivery date &amp; time<br>{{ $doc['schedule'] }}</p>@endif
@foreach($doc['route'] as $label=>$value)<div class="delivery-line"><strong>{{ $label }}:</strong> {{ $value }}</div>@endforeach
</td></tr></table>
<p class="delivery-meta"><strong>{{ $doc['fulfillment'] }}</strong> &middot; {{ $doc['status'] }} &middot; {{ $doc['source'] }}</p>
<table class="doc-lines delivery-products"><thead><tr><th>Serial<br>No.</th><th>Photo</th><th>Item code</th><th>Premium Marketing Name</th><th class="delivery-number">Net carton<br>weight (kg)</th><th class="delivery-number">Pack<br>Weight (kg)</th><th class="delivery-number">Pieces<br>per carton</th><th class="delivery-quantity">Qty</th></tr></thead><tbody>
@foreach($doc['items'] as $item)<tr><td class="delivery-number">{{ $item['serial'] }}</td><td>@if($item['image'])<img class="doc-product-image" src="{{ $item['image'] }}" alt="{{ $item['name'] }}">@else<span class="doc-product-no-image">No image</span>@endif</td><td class="delivery-code">{{ $item['code'] ?: '-' }}</td><td>{{ $item['name'] }}</td><td class="delivery-number">{{ $item['net_carton_kg'] }}</td><td class="delivery-number">{{ $item['pack_weight_kg'] }}</td><td class="delivery-number">{{ $item['pieces_per_carton'] }}</td><td class="delivery-quantity">{{ $item['quantity'] }}</td></tr>@endforeach
</tbody></table>
@if($doc['notes'])<div class="doc-box"><h2>Order instructions</h2><p class="delivery-line">{{ $doc['notes'] }}</p></div>@endif
<div class="delivery-completion">
@include('documents.delivery-handover')
<footer class="doc-footer"><strong>Thank you for choosing {{ $doc['brand']['name'] }}.</strong><p>Please quote {{ $doc['number'] }} with any delivery enquiries.</p>@include('documents.registration')</footer>
</div>
</main></body></html>
