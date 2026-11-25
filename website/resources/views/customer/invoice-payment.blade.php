@extends('admin.layout')
@section('title', $staff ? 'Invoice Payment' : 'Pay Invoices')
@include('customer.account-style')
@section('content')
@php
    $money = fn ($cents) => 'CAD $'.number_format($cents / 100, 2);
    $event = $payment->receipt?->events->first();
    $checkUrl = route($staff ? 'admin.invoice-payments.check' : 'customer.invoice-payments.check', $payment->reference);
@endphp
<section class="panel panel-content account-hero">
    <div><p class="account-eyebrow">{{ \App\Models\InvoicePayment::STATUSES[$payment->status] }}</p><h2>{{ $money($payment->amount_cents) }}</h2>
        @if($staff)<p>{{ $payment->customer->business_name ?: $payment->customer->name }} · Customer #{{ $payment->customer_id }}</p>@endif
    </div>
    <a class="btn btn-outline-primary" href="{{ route($staff ? 'admin.invoice-payments.index' : 'customer.invoices') }}">{{ $staff ? 'All invoice payments' : 'Back to invoices' }}</a>
</section>
<div class="account-two-col">
<section class="panel panel-content">
    <div class="account-section-heading"><h2>Selected invoices</h2></div>
    <div class="table-responsive"><table class="table account-table"><thead><tr><th>Invoice</th><th>Order</th><th class="text-right">Amount</th></tr></thead><tbody>
        @foreach($payment->allocations as $line)
        <tr><td>@if(! $staff)<a href="{{ route('customer.documents.file', ['invoice', $line->customer_invoice_id]) }}">{{ $line->invoice }}</a>@else{{ $line->invoice }}@endif</td>
            <td>{{ mb_strtoupper($line->number) }}</td><td class="text-right">{{ $money($line->amount_cents) }}</td></tr>
        @endforeach
    </tbody></table></div>
    @if($event)
        <div class="account-actions">
        @if($staff && auth()->user()->isSuper())
            <a class="btn btn-outline-primary" href="{{ route('admin.customers.receipts.document', [$payment->customer, $payment->receipt, 'version' => $event->id]) }}">View receipt {{ $payment->receipt->number }}</a>
        @elseif(! $staff)
            <a class="btn btn-primary" href="{{ route('customer.documents.file', ['account-receipt', $event->id]) }}">View receipt {{ $payment->receipt->number }}</a>
            <a class="btn btn-outline-primary" href="{{ route('customer.documents.file', ['account-receipt', $event->id, 'download' => 1]) }}">Download PDF</a>
        @else
            <span>Receipt {{ $payment->receipt->number }}</span>
        @endif
        </div>
    @endif
</section>
<section class="panel panel-content">
    <div class="account-section-heading"><h2>{{ $staff ? 'Gateway record' : 'Payment status' }}</h2></div>
    @if($staff)<dl class="account-key-values"><dt>Payment reference</dt><dd>{{ $payment->reference }}</dd><dt>Helcim invoice</dt><dd>{{ $payment->provider_invoice }}</dd><dt>Helcim transaction</dt><dd>{{ $payment->transaction_id ?: '—' }}</dd>
        @if($payment->processor_amount_cents !== null)<dt>Processor amount</dt><dd>{{ $payment->processor_currency }} ${{ number_format($payment->processor_amount_cents / 100, 2) }}</dd>@endif
        <dt>Last checked</dt><dd>{{ \App\Support\BusinessTime::format($payment->last_checked_at) ?: '—' }}</dd></dl>@endif
    @if($payment->attention)<p class="alert alert-warning">{{ $staff || $payment->status === 'init_failed' ? $payment->attention : 'This payment needs review. Please contact Customer Service before paying again.' }}</p>@endif
    @if(! $staff && $canPay && $payment->status === 'pending' && $payment->expires_at->isFuture() && $payment->checkout_token)
        <p id="card-payment-message" role="status">Enter your card details in Helcim’s secure payment window.</p>
        <div id="helcim-checkout" data-checkout-token="{{ $payment->checkout_token }}" data-confirm-url="{{ route('customer.invoice-payments.confirm', $payment->reference) }}" data-csrf="{{ csrf_token() }}">
            <button class="btn btn-primary" id="helcim-pay-button" type="button">Pay {{ $money($payment->amount_cents) }} by card</button>
        </div>
        <script src="https://secure.helcim.app/helcim-pay/services/start.js" defer></script>
        <script src="{{ asset('assets/js/helcim-checkout.js') }}?v={{ filemtime(public_path('assets/js/helcim-checkout.js')) }}" defer></script>
    @elseif(! $staff && $payment->status === 'paid')
        <p>Payment confirmed and applied to the selected invoices.</p>
    @elseif(! $staff && $payment->status === 'expired')
        <p>This payment window expired. Go back to invoices to start a new payment.</p>
    @elseif(! $staff && $payment->status === 'refunded')
        <p>Helcim refunded this charge before any invoice balance changed.</p>
    @elseif(! $staff && ! $payment->attention)
        <p>Check payment status before trying again.</p>
    @endif
    @if(! in_array($payment->status, ['init_failed', 'refunded'], true) && ($staff || $payment->status !== 'paid'))
        <form class="mt-3" method="post" action="{{ $checkUrl }}">@csrf<button class="btn btn-outline-primary" type="submit">Check payment status</button></form>
    @endif
</section>
</div>
@if($staff)
<section class="panel panel-content"><div class="account-section-heading"><h2>Transaction log</h2></div>
    <ol class="account-events">@foreach($payment->events as $entry)<li><time>{{ \App\Support\BusinessTime::format($entry->created_at) }}</time><p>{{ $entry->detail }}</p></li>@endforeach</ol>
</section>
@endif
@endsection
