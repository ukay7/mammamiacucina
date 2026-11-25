@extends('admin.layout')
@section('title', 'Invoices')
@include('customer.account-style')
@section('content')
@php($money = fn ($cents) => 'CAD $'.number_format($cents / 100, 2))
<section class="panel panel-content account-hero">
    <div><p class="account-eyebrow">Total outstanding</p><h2>{{ $money($outstanding) }}</h2></div>
    <div class="account-actions">
    <a class="btn btn-outline-primary" href="{{ route('customer.statement') }}">Download statement</a>
    @if($active)
        <a class="btn btn-primary" href="{{ route('customer.invoice-payments.show', $active->reference) }}">View current payment</a>
    @elseif($payable)
        <button type="button" class="btn btn-primary" data-pay-invoices>Pay Outstanding Balance</button>
    @elseif($outstanding)
        <div><button type="button" class="btn btn-primary" disabled>Pay Outstanding Balance</button>
            <small class="d-block mt-2">{{ $canPay ? 'These invoices have a payment in progress.' : 'Online card payment is currently unavailable for your account.' }}</small></div>
    @endif
    </div>
</section>
<section class="panel panel-content">
    <div class="table-responsive"><table class="table account-table">
        <thead><tr><th scope="col">Invoice / order</th><th scope="col">Issued / due</th><th scope="col">Age</th><th scope="col" class="text-right">Invoice total</th><th scope="col" class="text-right">Current balance</th><th scope="col">Actions</th></tr></thead>
        <tbody>@forelse($invoices as $invoice)
            @php($row = $rows[$invoice->id])
            <tr><td><strong>{{ $invoice->label }}</strong><a class="d-block" href="{{ route('customer.order', $invoice->order) }}">{{ mb_strtoupper($invoice->order->number) }}</a></td>
                <td>{{ \App\Support\BusinessTime::format($invoice->issued_at, 'd M Y') }}@if($row['due'])<small class="d-block text-muted">Due {{ \Carbon\CarbonImmutable::parse($row['due'])->format('d M Y') }}</small>@endif</td>
                <td>@if($row['balance']){{ $row['outstanding_days'] }} {{ \Illuminate\Support\Str::plural('day', $row['outstanding_days']) }} outstanding
                    @if($row['overdue_days'])<small class="d-block text-danger">{{ $row['overdue_days'] }} {{ \Illuminate\Support\Str::plural('day', $row['overdue_days']) }} overdue</small>@endif
                    @else — @endif</td>
                <td class="text-right">{{ $money($invoice->total_cents) }}</td><td class="text-right"><strong>{{ $money($row['balance']) }}</strong></td>
                <td><div class="account-actions">
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('customer.documents.file', ['invoice', $invoice->id]) }}">View invoice</a>
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('customer.documents.file', ['invoice', $invoice->id, 'download' => 1]) }}" aria-label="Download {{ $invoice->label }} PDF">Download PDF</a>
                    @if(! $active && $row['payable'])<button class="btn btn-primary btn-sm" type="button" data-pay-invoices="{{ $invoice->id }}" aria-label="Pay {{ $invoice->label }}" @disabled(! $canPay)>Pay invoice</button>@endif
                </div></td></tr>
        @empty<tr><td colspan="6">No invoices have been issued for your account.</td></tr>@endforelse</tbody>
    </table></div>{{ $invoices->links() }}
</section>
@if($recentPayments->isNotEmpty())
<section class="panel panel-content">
    <div class="account-section-heading"><h2>Recent card payments</h2><a href="{{ route('customer.receipts') }}">View receipts</a></div>
    <div class="table-responsive"><table class="table account-table"><thead><tr><th>Date</th><th>Invoices</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>@foreach($recentPayments as $payment)<tr><td>{{ \App\Support\BusinessTime::format($payment->created_at, 'd M Y H:i T') }}</td>
            <td>{{ $payment->allocations->pluck('invoice')->implode(', ') }}</td><td>{{ $money($payment->amount_cents) }}</td>
            <td>{{ \App\Models\InvoicePayment::STATUSES[$payment->status] }}</td><td><a href="{{ route('customer.invoice-payments.show', $payment->reference) }}">View payment</a></td></tr>@endforeach</tbody>
    </table></div>
</section>
@endif
@if($payable)
<dialog class="account-dialog" id="invoice-selection" aria-labelledby="invoice-selection-title">
    <header><h2 id="invoice-selection-title">Pay outstanding invoices</h2><button class="btn btn-outline-primary btn-sm" type="button" data-close-invoices>Close</button></header>
    <form method="post" action="{{ route('customer.invoice-payments.start') }}" data-invoice-pay-form>
        @csrf<input type="hidden" name="expected_total" value="0">
        <p>Select the invoices you want to pay in full by card.</p>
        <fieldset class="account-invoice-list"><legend class="sr-only">Outstanding invoices</legend>
            @foreach($payable as $invoice)
                <label><input type="checkbox" name="invoice_ids[]" value="{{ $invoice['id'] }}" data-amount="{{ $invoice['amount'] }}">
                    <span><strong>{{ $invoice['label'] }}</strong><small>{{ mb_strtoupper($invoice['order']) }}</small></span><strong>{{ $money($invoice['amount']) }}</strong></label>
            @endforeach
        </fieldset>
        <div class="account-dialog-total"><span>Selected total</span><strong data-invoice-total aria-live="polite">CAD $0.00</strong></div>
        <button class="btn btn-primary" type="submit" disabled>Continue to card payment</button>
    </form>
</dialog>
<script src="{{ asset('admin-assets/customer-invoices.js') }}?v={{ filemtime(public_path('admin-assets/customer-invoices.js')) }}" defer></script>
@endif
@endsection
