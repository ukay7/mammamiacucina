@extends('admin.layout')
@section('title', 'Invoice Payments')
@include('customer.account-style')
@section('content')
<section class="panel panel-content">
    <form class="account-filters" method="get" action="{{ route('admin.invoice-payments.index') }}">
        <label>Status<select name="status" class="form-control"><option value="">All payments</option>@foreach(\App\Models\InvoicePayment::STATUSES as $key => $label)<option value="{{ $key }}" @selected($filter === $key)>{{ $label }}</option>@endforeach</select></label>
        <button class="btn btn-primary" type="submit">Filter</button>
    </form>
    <div class="table-responsive"><table class="table account-table"><thead><tr><th>Customer</th><th>Created</th><th>Amount</th><th>Status</th><th>Transaction</th><th>Action</th></tr></thead>
        <tbody>@forelse($payments as $payment)
            <tr><td>{{ $payment->customer->business_name ?: $payment->customer->name }}<small class="d-block text-muted">Customer #{{ $payment->customer_id }}</small></td>
                <td>{{ \App\Support\BusinessTime::format($payment->created_at) }}</td><td>CAD ${{ number_format($payment->amount_cents / 100, 2) }}</td>
                <td>{{ \App\Models\InvoicePayment::STATUSES[$payment->status] }}@if($payment->attention && $payment->status === 'paid')<small class="d-block text-danger">Review flagged</small>@endif</td>
                <td>{{ $payment->transaction_id ?: '—' }}</td><td><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.invoice-payments.show', $payment->reference) }}">View record</a></td></tr>
        @empty<tr><td colspan="6">No invoice payments match this filter.</td></tr>@endforelse</tbody>
    </table></div>{{ $payments->links() }}
</section>
@endsection
