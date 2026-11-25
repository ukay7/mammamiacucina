@extends('admin.layout')
@section('title', $title)
@include('customer.account-style')
@section('content')
<p><a href="{{ route('admin.accounting.index') }}">← Accounting Reports</a></p>
<section class="panel panel-content">
    <p>{{ $description }}</p>
    <form class="account-filters" method="get" action="{{ route('admin.accounting.report', $kind) }}">
        @foreach($active as $key)
            @php
                $label = match ($key) {
                    'to' => in_array('from', $active) ? 'To' : 'As of', 'customer' => 'Customer #', 'product' => 'Product ID',
                    'method' => 'Payment method', 'document_type' => 'Document type', 'invoice_status' => 'Invoice status',
                    'payment_status' => 'Payment status', 'transaction' => 'Transaction type', default => ucfirst($key),
                };
                $choices = match ($key) {
                    'supplier' => $suppliers, 'category' => $categories,
                    'method' => ['cash' => 'Cash', 'card' => 'Card', 'paypal' => 'PayPal', 'etransfer' => 'E-transfer', 'cheque' => 'Cheque', 'credit' => 'Customer credit'],
                    'document_type' => \App\Services\AccountingDocuments::TYPES,
                    'transaction' => ['payment' => 'Payment', 'refund' => 'Refund', 'reversal' => 'Reversal', 'bounced_cheque' => 'Bounced cheque', 'correction_debit' => 'Method correction debit', 'correction_credit' => 'Method correction credit'],
                    'payment_status' => ['unpaid' => 'Unpaid', 'pending' => 'Pending', 'paid' => 'Paid', 'partially_refunded' => 'Partially refunded', 'refunded' => 'Refunded', 'failed' => 'Failed', 'cancelled' => 'Cancelled', 'review' => 'Under review'],
                    'invoice_status' => ['outstanding' => 'Outstanding', 'settled' => 'No balance due'] + ($kind === 'sales' ? ['issued' => 'Issued', 'not_issued' => 'Not issued'] : []),
                    default => null,
                };
            @endphp
            <label>{{ $label }}
                @if($choices !== null)
                    <select class="form-control" name="{{ $key }}"><option value="">All</option>
                        @foreach($choices as $value => $name)<option value="{{ $value }}" @selected((string) ($filters[$key] ?? '') === (string) $value)>{{ $name }}</option>@endforeach
                    </select>
                @else
                    <input class="form-control" name="{{ $key }}" type="{{ in_array($key, ['from', 'to']) ? 'date' : 'number' }}"
                        @if(in_array($key, ['customer', 'product'])) min="1" @endif value="{{ $filters[$key] ?? '' }}">
                @endif
            </label>
        @endforeach
        <button class="btn btn-primary" type="submit">Run report</button>
        <a class="btn btn-outline-primary" href="{{ route('admin.accounting.report', [$kind, ...$filters, 'format' => 'csv']) }}">Export CSV</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.accounting.report', [$kind, ...$filters, 'format' => 'print']) }}" target="_blank" rel="noopener">Print / Save PDF</a>
    </form>
    @include('admin.accounting.table', ['rows' => $paginator])
    {{ $paginator->links() }}
</section>
@endsection
