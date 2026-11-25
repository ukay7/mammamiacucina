<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $doc['title'] }}</title>
    @include('documents.style')
    <style>
        body{background:#fff}.document{margin:0;padding:0;max-width:none}
        .doc-statement .doc-lines th{width:19%}.doc-statement .doc-lines th:last-child{width:24%}
        .doc-statement .doc-lines td,.doc-statement .doc-lines th{padding:6px}
        .doc-statement .statement-total{font-size:19px;font-weight:bold;color:#67471f}
    </style>
</head>
<body>
@php($money = fn ($cents) => 'CAD '.number_format($cents / 100, 2))
<main class="document doc-statement">
    @include('documents.header')
    <table class="doc-info" role="presentation"><tr>
        <td><h2>Customer</h2><p class="doc-billing-name">{{ $customer->business_name ?: $customer->name }}</p>
            @if($customer->business_name)<p>{{ $customer->name }}</p>@endif
            <p>Account #{{ $customer->id }}</p><p>{{ $customer->email }}</p>
        </td>
        <td><h2>Current balance owing</h2><p class="statement-total">{{ $money($total) }}</p><p>Open invoice balances as of the statement date.</p></td>
    </tr></table>
    <table class="doc-lines">
        <thead><tr>
            <th scope="col">Invoice</th><th scope="col">Order</th><th scope="col">Issued</th><th scope="col">Due</th><th scope="col" class="money">Balance owing</th>
        </tr></thead>
        <tbody>@forelse($rows as $row)
            <tr><td>{{ $row['invoice'] }}</td><td>{{ mb_strtoupper($row['order']) }}</td>
                <td>{{ $row['issued'] }}</td><td>{{ $row['due'] ? \Carbon\CarbonImmutable::parse($row['due'])->format('d M Y') : '—' }}</td>
                <td class="money">{{ $money($row['balance']) }}</td></tr>
        @empty<tr><td colspan="5">There are no outstanding invoices.</td></tr>@endforelse</tbody>
    </table>
    <table class="doc-totals"><tr><td>Total amount owing</td><td class="money">{{ $money($total) }}</td></tr></table>
    @if($total > 0)@include('documents.invoice-payment-methods')@endif
    <footer class="doc-footer">
        <strong>Thank you for choosing {{ rtrim($doc['brand']['name'], '.') }}.</strong>
        @if($total > 0)<p>Please include the invoice numbers with your payment.</p>@endif
        @include('documents.registration')
    </footer>
</main>
</body>
</html>
