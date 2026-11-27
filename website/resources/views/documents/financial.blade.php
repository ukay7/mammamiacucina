<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $doc['title'] }} {{ mb_strtoupper((string)$doc['number']) }} - Mamma Mia Cucina</title>@include('documents.style')@if(!empty($pdf))<style>body{background:#fff}.document{margin:0;padding:0;max-width:none}.doc-toolbar{display:none}</style>@endif</head><body>
@if(empty($pdf))<div class="doc-toolbar">@isset($backUrl)<a href="{{ $backUrl }}">Back</a>@endisset<span>{{ $doc['title'] }}</span><button onclick="window.print()">Print / Save as PDF</button>@isset($downloadUrl)<a href="{{ $downloadUrl }}">Download PDF</a>@endisset</div>@endif
@php
    $isInvoice = isset($doc['invoice_payment_methods']);
    $isPaymentReceipt = $doc['title'] === 'Payment Receipt';
    $revised = ($isInvoice || $isPaymentReceipt) && app(\App\Services\DocumentTheme::class)->usesRevisedFinancialLayout();
    $revisedReceipt = $revised && $isPaymentReceipt;
    $totals = $doc['totals'];
    if ($isPaymentReceipt && isset($doc['payment_amount'])) {
        $totals = ['This payment' => $totals['This payment'] ?? $doc['payment_amount']] + $totals;
    }
@endphp
<main @class(['document', 'doc-financial', 'doc-revised-receipt' => $revisedReceipt, 'doc-delivery-changed' => !$revised && isset($doc['current_delivery'])])>
@include('documents.header', ['taxInHeader' => $revised, 'isReceiptHeader' => $isPaymentReceipt, 'invoiceReferences' => $isPaymentReceipt && filled($doc['invoice_reference'] ?? null) ? [$doc['invoice_reference']] : []])
<table class="doc-info"><tr><td><h2>Bill to</h2>@foreach($doc['billing'] as $billingIndex=>$line)<div @class(['doc-billing-line','doc-billing-name'=>$billingIndex===0,'doc-billing-account'=>$billingIndex===1 && str_starts_with((string)$line,'Customer account #')])>{{ $line }}@if($billingIndex===0)<x-business-customer-icon :business="$doc['business_customer'] ?? false" :embedded="true" />@endif</div>@endforeach</td>
@unless($revisedReceipt)
<td><h2>{{ $isInvoice ? 'Account Summary' : 'Account summary' }}</h2>
@if($isInvoice)
    <table class="doc-invoice-aging">
        @foreach(($revised ? array_intersect_key($doc['summary'], array_flip(['Total Due', 'Current Balance Owing'])) : $doc['summary']) as $label => $value)
            <tr><th scope="row">{{ $label }}</th><td class="money">{{ $value }}</td></tr>
        @endforeach
    </table>
@else
    @foreach($doc['summary'] as $label=>$value)<div>{{ $label }}: <strong>{{ $value }}</strong></div>@endforeach
    <small>Balances as of {{ app(\App\Services\DocumentTheme::class)->dateLabel($doc['balance_date'] ?? $doc['date']) }}.</small>
@endif
</td>
@endunless
</tr></table>
@if($isInvoice && !$revised)
    <p class="doc-notice">
        @if($doc['invoice_due_date'])<strong>Due date</strong> {{ \Carbon\CarbonImmutable::parse($doc['invoice_due_date'])->format('M j, Y') }} · @endif
        Balances as of {{ app(\App\Services\DocumentTheme::class)->dateLabel($doc['balance_date']) }}.
    </p>
@endif
@unless($revised)
    <p class="doc-notice">Order: <strong class="doc-order-reference">{{ mb_strtoupper((string)$doc['order_number']) }}</strong>. {{ $doc['notice'] }}</p>
@endunless
<div class="doc-line-table" role="region" aria-label="Order items" tabindex="0">
    <table class="doc-lines">
        <thead>
            <tr>
                <th scope="col">Photo</th>
                <th scope="col">Item code</th>
                <th scope="col">Description</th>
                @unless($revisedReceipt)<th scope="col" class="money">Unit Price<br> Before Discount</th>@endunless
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
                    @unless($revisedReceipt)<td class="money">{{ $item['original'] ?? '—' }}</td>@endunless
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
@if($isPaymentReceipt && isset($doc['receipt_payment_method'], $doc['payment_amount']))
    <div class="doc-box doc-payment-breakdown">
        <h2>Payment method</h2>
        <table class="doc-payment-method-amount">
            <tr><th scope="row">{{ $doc['receipt_payment_method'] }}</th><td>{{ $doc['payment_amount'] }}</td></tr>
        </table>
    </div>
@endif
@unless($revised)
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
@endunless
@endslot
<table class="doc-totals">@foreach($totals as $label=>$value)<tr><td>{{ $revised && $label === 'Tax' ? ($doc['tax_label'] ?? 'GST/HST') : $label }}</td><td class="money">{{ $value }}</td></tr>@endforeach</table>
@endcomponent
@if(!$revisedReceipt && $doc['notes'])<div class="doc-box"><h2>Notes</h2><p style="white-space:pre-line">{{ $doc['notes'] }}</p></div>@endif
<div class="doc-financial-ending">
@if($revised && $isInvoice)
    <p class="doc-invoice-payment-reference">Please quote invoice <strong>{{ mb_strtoupper((string) $doc['number']) }}</strong> when making a payment.</p>
@endif
@if($isInvoice) @include('documents.invoice-payment-methods', ['methods' => $doc['invoice_payment_methods']]) @endif
<footer class="doc-footer"><strong>Thank you for choosing Mamma Mia Cucina.</strong>@unless($revised)<p>Please quote <strong class="doc-order-reference">{{ mb_strtoupper((string)$doc['order_number']) }}</strong> with any enquiries. Order confirmations, invoices and payment receipts are separate records; they do not create additional charges.</p>@include('documents.registration')@endunless</footer>
</div></main></body></html>
