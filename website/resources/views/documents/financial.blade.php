<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $doc['title'] }} {{ mb_strtoupper((string)$doc['number']) }} - Mamma Mia Cucina</title>@include('documents.style')@if(!empty($pdf))<style>body{background:#fff}.document{margin:0;padding:0;max-width:none}.doc-toolbar{display:none}</style>@endif</head><body>
@if(empty($pdf))<div class="doc-toolbar">@isset($backUrl)<a href="{{ $backUrl }}">Back</a>@endisset<span>{{ $doc['title'] }}</span><button onclick="window.print()">Print / Save as PDF</button>@isset($downloadUrl)<a href="{{ $downloadUrl }}">Download PDF</a>@endisset</div>@endif
<main @class(['document', 'doc-financial', 'doc-delivery-changed' => isset($doc['current_delivery'])])>@include('documents.header')
@php($isInvoice = isset($doc['invoice_payment_methods']))
<table class="doc-info"><tr><td><h2>Bill to</h2>@foreach($doc['billing'] as $billingIndex=>$line)<div @class(['doc-billing-line','doc-billing-name'=>$billingIndex===0,'doc-billing-account'=>$billingIndex===1 && str_starts_with((string)$line,'Customer account #')])>{{ $line }}@if($billingIndex===0)<x-business-customer-icon :business="$doc['business_customer'] ?? false" :embedded="true" />@endif</div>@endforeach</td>
<td><h2>{{ $isInvoice ? 'Account Summary' : 'Account summary' }}</h2>
@if($isInvoice)
    <table class="doc-invoice-aging">
        @foreach($doc['summary'] as $label => $value)
            <tr><th scope="row">{{ $label }}</th><td class="money">{{ $value }}</td></tr>
        @endforeach
    </table>
@else
    @foreach($doc['summary'] as $label=>$value)<div>{{ $label }}: <strong>{{ $value }}</strong></div>@endforeach
    <small>Balances as of {{ app(\App\Services\DocumentTheme::class)->dateLabel($doc['balance_date'] ?? $doc['date']) }}.</small>
@endif
</td></tr></table>
@if($isInvoice)
    <p class="doc-notice">
        @if($doc['invoice_due_date'])<strong>Due date</strong> {{ \Carbon\CarbonImmutable::parse($doc['invoice_due_date'])->format('M j, Y') }} · @endif
        Balances as of {{ app(\App\Services\DocumentTheme::class)->dateLabel($doc['balance_date']) }}.
    </p>
@endif
<p class="doc-notice">Order: <strong class="doc-order-reference">{{ mb_strtoupper((string)$doc['order_number']) }}</strong>. {{ $doc['notice'] }}</p>
<div class="doc-line-table" role="region" aria-label="Order items" tabindex="0">
    <table class="doc-lines">
        <thead>
            <tr>
                <th scope="col">Photo</th>
                <th scope="col">Item code</th>
                <th scope="col">Description</th>
                <th scope="col" class="money">Unit Price<br> After Discount</th>
                <th scope="col" class="money">Qty</th>
                <th scope="col" class="money">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($doc['items'] as $item)
                <tr>
                    <td>
                        @if($item['image'] ?? null)
                            <img class="doc-product-image" src="{{ $item['image'] }}" alt="{{ $item['name'] }}" width="28" height="28">
                        @else
                            <span class="doc-product-no-image">No image</span>
                        @endif
                    </td>
                    <td>{{ $item['code'] }}</td>
                    <td>{{ $item['name'] }}@if($item['discount'] ?? null)<br><small>{{ $item['discount'] }}</small>@endif</td>
                    <td class="money">{{ $item['unit'] }}</td>
                    <td class="money">{{ $item['quantity'] }}</td>
                    <td class="money">{{ $item['total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@if($doc['discount']??null)<p>{{ $doc['discount'] }}</p>@endif
@component('documents.payment-summary')
@slot('instructions')
<div class="doc-box"><h2>{{ $isInvoice ? 'Payment status' : 'Payment instructions' }}</h2><p>{{ $doc['instructions'] }}</p><p><strong>Payment details:</strong> {{ $doc['payment'] }}</p>@foreach($doc['payment_references'] ?? [] as $label=>$reference)<p><strong>{{ $label }}:</strong> {{ $reference }}</p>@endforeach</div>
<div class="doc-box doc-delivery">
    @if(isset($doc['current_delivery']))
        <h2>Pickup Details at Issuance</h2>
    @elseif($isInvoice)
        <h2>{{ $doc['invoice_fulfillment'] === 'pickup' ? 'Pickup Details' : 'Delivery Details' }}</h2>
    @else
        <h2>Delivery / pickup details</h2>
    @endif
    <p style="white-space:pre-line">{{ $doc['delivery'] }}</p>
</div>
@isset($doc['current_delivery'])
    <div class="doc-box doc-delivery">
        <h2>Current Delivery Details</h2>
        <p style="white-space:pre-line">{{ $doc['current_delivery'] }}</p>
        <p><small>This order was changed from pickup to delivery after the invoice was issued.</small></p>
    </div>
@endisset
@endslot
<table class="doc-totals">@foreach($doc['totals'] as $label=>$value)<tr><td>{{ $label }}</td><td class="money">{{ $value }}</td></tr>@endforeach</table>
@endcomponent
@if($doc['notes'])<div class="doc-box"><h2>Notes</h2><p style="white-space:pre-line">{{ $doc['notes'] }}</p></div>@endif
<div class="doc-financial-ending">
@if($isInvoice) @include('documents.invoice-payment-methods', ['methods' => $doc['invoice_payment_methods']]) @endif
<footer class="doc-footer"><strong>Thank you for choosing Mamma Mia Cucina.</strong><p>Please quote <strong class="doc-order-reference">{{ mb_strtoupper((string)$doc['order_number']) }}</strong> with any enquiries. Order confirmations, invoices and payment receipts are separate records; they do not create additional charges.</p>@include('documents.registration')</footer>
</div></main></body></html>
