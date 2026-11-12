<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Delivery Form {{ $doc['number'] }} - {{ $doc['brand']['name'] }}</title>
@include('documents.style')
<style>
.doc-delivery-form .doc-lines th:nth-child(1){width:9%}.doc-delivery-form .doc-lines th:nth-child(2){width:17%}.doc-delivery-form .doc-lines th:nth-child(3){width:34%}.doc-delivery-form .doc-lines th:nth-child(4){width:31%}.doc-delivery-form .doc-lines th:nth-child(5){width:9%}
.doc-delivery-form .doc-lines td{padding:7px 5px}.delivery-name{font-size:15px;font-weight:bold}.delivery-line{white-space:pre-line;word-wrap:break-word}.delivery-meta{margin:10px 0;color:#645346}.delivery-quantity{text-align:right!important;font-weight:bold}.delivery-signatures{width:100%;border-collapse:collapse;table-layout:fixed;margin-top:28px;page-break-inside:avoid}.delivery-signatures td{width:50%;padding:0 22px 0 0;vertical-align:bottom}.delivery-signatures td:last-child{padding:0 0 0 22px}.delivery-signature-line{height:32px;border-bottom:1px solid #9a835f;margin-bottom:6px}.doc-delivery-form .doc-footer{margin-top:20px}.delivery-schedule{font-weight:bold;margin-top:7px}.doc-delivery-form .doc-info td{word-wrap:break-word}
.doc-delivery-form .doc-lines{font-size:10px;line-height:1.3}.doc-delivery-form .doc-lines td div{line-height:12px}.doc-delivery-form .doc-lines td{padding:5px}.doc-delivery-form .doc-product-image{width:32px;height:32px}.doc-delivery-form .doc-product-no-image{width:32px;min-height:32px;font-size:7px;padding-top:9px}
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
<table class="doc-lines"><thead><tr><th>Photo</th><th>Item code</th><th>Product details</th><th>Size / pack</th><th class="delivery-quantity">Qty</th></tr></thead><tbody>
@foreach($doc['items'] as $item)<tr><td>@if($item['image'])<img class="doc-product-image" src="{{ $item['image'] }}" alt="{{ $item['name'] }}">@else<span class="doc-product-no-image">No image</span>@endif</td><td>{{ $item['code'] ?: '-' }}</td><td>{{ $item['name'] }}</td><td>@forelse($item['size'] as $size)<div>{{ $size }}</div>@empty<span>-</span>@endforelse</td><td class="delivery-quantity">{{ $item['quantity'] }}</td></tr>@endforeach
</tbody></table>
@if($doc['notes'])<div class="doc-box"><h2>Order instructions</h2><p class="delivery-line">{{ $doc['notes'] }}</p></div>@endif
<table class="delivery-signatures"><tr><td><div class="delivery-signature-line"></div><strong>Receiver name</strong></td><td><div class="delivery-signature-line"></div><strong>Receiver signature</strong></td></tr></table>
<footer class="doc-footer"><strong>Thank you for choosing {{ $doc['brand']['name'] }}.</strong><p>Please quote {{ $doc['number'] }} with any delivery enquiries.</p>@include('documents.registration')</footer>
</main></body></html>
