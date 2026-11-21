<article class="quote-document">
@php
$doc=['brand'=>app(\App\Services\DocumentTheme::class)->brand(),'title'=>'PRICE QUOTATION','number'=>$quotation->number,'date'=>\App\Support\BusinessTime::format($quotation->created_at,'d M Y')];
@endphp
@include('documents.header')
<div class="quote-body">
<section class="quote-client"><div><b>PREPARED FOR</b><h2>{{ ($quotation->customer_snapshot['business_name'] ?? '') ?:$quotation->customer_snapshot['name'] }} <x-business-customer-icon :customer="$quotation->customer_snapshot" :embedded="true" /></h2><p>{{ implode(', ',array_filter(Arr::only($quotation->customer_snapshot,['address','city','province','postal_code','country']))) }}<br>{{ $quotation->customer_snapshot['phone'] }} &nbsp;|&nbsp; {{ $quotation->customer_snapshot['email'] }}</p>@if($quotation->customer_snapshot['website']??null)<p>{{ $quotation->customer_snapshot['website'] }}</p>@endif</div><div><p>QUOTE DATE &nbsp;|&nbsp; {{ \App\Support\BusinessTime::format($quotation->created_at,'l, d M Y') }}<br>VALID UNTIL &nbsp;|&nbsp; {{ $quotation->due_date->format('l, d M Y') }}<br>CAD &nbsp;|&nbsp; {{ ucfirst($quotation->pricing_tier) }} pricing<br>@if($quotation->delivery_terms){{ $quotation->delivery_terms }}<br>@endif
REFERENCE &nbsp;|&nbsp; {{ $quotation->number }}</p></div></section>
@php
$quoteImages=app(\App\Services\DocumentProductImages::class)->forItems($quotation->items ?? []);
$quoteGroups=collect($quotation->items)->map(fn($item,$index)=>array_merge($item,['image'=>$quoteImages[$index]??null]))->groupBy(fn($item)=>$item['category']??'Uncategorized')->sortKeys();
$quoteMoney=fn($value)=>'$'.\App\Support\NumberDisplay::decimal($value);
$retailMultiplier=1.2;
@endphp
<div class="quote-lines-scroll"><table class="quote-lines"><thead><tr>
    <th style="width:5.5%">PHOTO</th>
    <th style="width:8%">CODE</th>
    <th style="width:23%">PRODUCT</th>
    <th style="width:8%">CARTON<br>PACK</th>
    <th style="width:7.5%">DISCOUNT</th>
    <th style="width:10%" aria-label="Quote / Sale Unit">QUOTE /<br>SALE UNIT</th>
    <th style="width:10%">QUOTE /<br>CARTON</th>
    <th style="width:14%">SUGGESTED RETAIL<br>/ CARTON (+20%)</th>
    <th style="width:14%" aria-label="Suggested retail / sale unit (+20%)">SUGGESTED RETAIL<br>/ SALE UNIT (+20%)</th>
</tr></thead><tbody>
@foreach($quoteGroups as $category=>$items)
<tr class="quote-category"><th colspan="9">{{ $category }}</th></tr>
@foreach($items as $item)
@php
$carton=(float)($item['unit_price']??($item['unit_cents']/100));
$factor=isset($item['discount_basis_points'])?1-$item['discount_basis_points']/10000:1-($item['discount_cents']/max(1,round($carton*100)*$item['quantity']));
$netCarton=$carton*$factor;
$pack=$item['pieces_per_carton']??null;
$unit=$pack?$netCarton/$pack:(isset($item['sale_unit_price'])?(float)$item['sale_unit_price']*$factor:null);
$suggestedCarton=$netCarton*$retailMultiplier;
$suggestedUnit=$unit!==null?$unit*$retailMultiplier:null;
@endphp
<tr><td>@if($item['image'])<img class="quote-product-image" src="{{ $item['image'] }}" alt="{{ $item['name'] }}" width="26" height="26">@else<span class="quote-no-image" aria-label="No product photo">—</span>@endif</td><td class="quote-code">{{ $item['code'] }}</td><td>{{ $item['name'] }}@if($item['quantity']>1)<small> · {{ $item['quantity'] }} cartons</small>@endif</td><td>{{ $pack?$pack.' '.($pack===1?'pc':'pcs'):'—' }}</td><td>{{ \App\Support\NumberDisplay::decimal((1-$factor)*100) }}%</td><td>{{ $unit!==null?$quoteMoney($unit):'—' }}</td><td>{{ $quoteMoney($netCarton) }}</td><td>{{ $quoteMoney($suggestedCarton) }}</td><td class="quote-retail-unit">{{ $suggestedUnit!==null?$quoteMoney($suggestedUnit):'—' }}</td></tr>
@endforeach
@endforeach
</tbody></table></div>
<section class="quote-bottom"><div class="quote-terms">@if($quotation->payment_terms)<h3>Payment instructions</h3><p class="quote-payment-instructions">{{ $quotation->payment_terms }}</p>@endif<h3>Notes & terms</h3>@if($quotation->notes)<p style="white-space:pre-wrap">{{ $quotation->notes }}</p>@endif<p>Listed quote prices include the line discount. Overall adjustments and tax are shown separately. Suggested retail is indicative, before tax.</p></div><div class="quote-summary">@foreach(['subtotal_cents'=>'Product subtotal','line_discount_cents'=>'Line discounts','discount_cents'=>'Overall discount','charge_cents'=>'Additional charge','tax_cents'=>'Tax ('.number_format($quotation->tax_basis_points/100,2).'%)','total_cents'=>'TOTAL CAD'] as $field=>$label)<div class="{{ $field==='total_cents'?'quote-grand':'' }}"><span>{{ $label }}</span><strong>{{ in_array($field,['discount_cents','line_discount_cents'])?'−':'' }}${{ number_format($quotation->$field/100,2) }}</strong></div>@endforeach</div></section>
<footer>{{ $quotation->number }} &nbsp;|&nbsp; Valid until {{ $quotation->due_date->format('l, d M Y') }}<br>This quotation is not an invoice. Delivery is not included. Availability is subject to confirmation.@include('documents.registration')</footer>
</div></article>
<style>
.quote-document{box-sizing:border-box;background:white;color:#3d241b;max-width:210mm;margin:0 auto;font:10px Arial,sans-serif;border:1px solid #d1c9be;line-height:1.35}.quote-document *{box-sizing:border-box}.quote-brand{display:flex;align-items:center;gap:18px;background:#f5f1e7;padding:20px 30px}.quote-brand img{width:70px;height:66px;object-fit:contain}.quote-brand>div{flex:1}.quote-brand h1{font:bold 25px Georgia,serif;margin:0 0 4px;color:#3d241b}.quote-brand p{font-size:9px;margin:0;color:#796959}.quote-rule{height:2px;background:#b18c52;margin-top:10px}.quote-body{padding:14px 32px 22px}.quote-client{display:grid;grid-template-columns:1.15fr 1fr;gap:20px;border:1px solid #e1d1b9;border-radius:7px;padding:10px;margin-bottom:10px}.quote-client b{font-size:10px}.quote-client h2{font:bold 12px Arial,sans-serif;margin:0 0 2px}.quote-client p{font-size:9px;color:#786657;line-height:1.5;margin:0;overflow-wrap:anywhere}.quote-lines{width:100%;border-collapse:collapse;table-layout:fixed;font-size:8.5px}.quote-lines thead th{background:#f7f0e6;color:#30271e;font-size:7.5px;padding:6px 5px;text-align:left;line-height:1.3}.quote-lines thead th:nth-child(n+5){text-align:right}.quote-lines td{padding:2px 5px;border:0;overflow-wrap:anywhere}.quote-lines td:nth-child(n+5){text-align:right;font-variant-numeric:tabular-nums}.quote-lines tbody tr:nth-child(odd){background:#f5f1e6}.quote-lines .quote-category th{background:#ebe3d4;font-size:9px;padding:3px 5px;text-align:left;text-transform:uppercase}.quote-code{font-weight:bold}.quote-lines small{font-size:8px}.quote-lines tr{break-inside:avoid}.quote-category{break-after:avoid}.quote-bottom{display:grid;grid-template-columns:1fr 230px;gap:25px;margin-top:22px;break-inside:avoid}.quote-terms h3{font-size:10px;margin:0 0 4px}.quote-terms p{font-size:9px;color:#796959}.quote-summary>div{display:flex;justify-content:space-between;padding:4px 6px;gap:12px;font-size:10px}.quote-summary .quote-grand{background:#f7f0e6;color:#30271e;padding:8px 6px;margin-top:5px}.quote-document footer{font-size:8px;color:#796959;margin-top:35px;border-top:1px solid #dfcfb7;padding-top:8px}.quote-document thead{display:table-header-group}@media screen and (max-width:700px){.quote-body{padding:12px}.quote-brand{padding:18px}.quote-client,.quote-bottom{grid-template-columns:1fr;gap:12px}.quote-lines{font-size:7px}.quote-lines td,.quote-lines th{padding-left:2px;padding-right:2px}}@media print{@page{size:A4;margin:10mm}body{margin:0}.no-print{display:none!important}.quote-document{border:0;max-width:none;font-size:10px;print-color-adjust:exact;-webkit-print-color-adjust:exact}.quote-body{padding:14px 20px}.quote-brand{padding:16px 20px}.quote-lines{font-size:8.5px}.quote-client{grid-template-columns:1.15fr 1fr}.quote-bottom{grid-template-columns:1fr 230px}}
.quote-document .doc-header{width:calc(100% - 40px);margin:16px 20px}.quote-client{background:#f7f0e6;border:0}.quote-document footer:before{content:'Thank you for choosing Mamma Mia Cucina.';display:block;font-weight:bold;margin-bottom:5px}
.quote-payment-instructions{white-space:pre-line;word-wrap:break-word;margin:0 0 12px}
.quote-lines td{vertical-align:middle}.quote-lines tr{page-break-inside:avoid}
.quote-category{page-break-after:avoid}
.quote-lines thead th:nth-child(5),.quote-lines td:nth-child(5){text-align:left}
.quote-product-image{display:block;width:26px;height:26px;object-fit:contain;border:1px solid #e1d1b9;border-radius:3px;background:white}
.quote-no-image{display:block;width:26px;text-align:center;color:#a0907b}
@media screen and (max-width:700px){.quote-document{width:100%;min-width:0}.quote-lines-scroll{overflow-x:auto}.quote-lines{min-width:690px;font-size:8.5px}}
</style>
@include('documents.header-style')
