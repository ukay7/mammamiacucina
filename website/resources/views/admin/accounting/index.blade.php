@extends('admin.layout')
@section('title', 'Accounting Reports')
@include('customer.account-style')
@section('content')
<section class="panel panel-content account-hero">
    <p>Review operating records and the imported CAD ledger.</p>
    @php($reconciliation = \App\Services\AdminNavigation::nodes()['access.admin.payment-reconciliation.index'])
    @if(\App\Services\AdminNavigation::allowed(auth()->user(), $reconciliation))
        <a class="btn btn-primary" href="{{ route('admin.payment-reconciliation.index') }}">Payment Reconciliation</a>
    @endif
</section>
<div class="account-grid">
    @foreach(\App\Services\AccountingReports::REPORTS as $kind => $label)
        <a href="{{ route('admin.accounting.report', $kind) }}" class="panel account-tile">
            <strong>{{ $label }}</strong><span aria-hidden="true">→</span>
        </a>
    @endforeach
</div>
@if(auth()->user()->isSuper())
    <section class="panel panel-content">
        <div class="account-section-heading"><h2>Import accounting journals</h2><a class="btn btn-outline-primary" href="{{ route('admin.accounting.template') }}">Download CSV template</a></div>
        <p>Import the accountant’s balanced CAD journals using a unique reference per journal. Use debit-positive amounts and credit-negative amounts. Post a separate reversing or adjusting journal to correct an earlier entry.</p>
        <details class="mb-3">
            <summary>CSV field guide</summary>
            <ul class="mt-2">
                <li><strong>reference, posted_on, classification, account, amount_cad</strong> are required. Dates use YYYY-MM-DD. All lines in a journal share one reference and date and sum to zero.</li>
                <li><strong>classification</strong> accepts {{ implode(', ', array_keys(\App\Services\AccountingLedger::LABELS)) }}.</li>
                <li>For <strong>cash</strong> lines, supply <strong>cash_flow</strong> as operating, investing, financing, transfer, or opening.</li>
                <li>For <strong>payable</strong> lines, supply <strong>supplier_id</strong> and the supplier invoice reference in <strong>document</strong>. Use the same reference when applying a payment and enter the bill’s <strong>due_on</strong> if available.</li>
                <li>Use <strong>gst_itc</strong> for input tax credits approved by the accountant, with the source document identified. Review invoices and supplier records before filing.</li>
            </ul>
        </details>
        <form class="account-filters" method="post" action="{{ route('admin.accounting.import') }}" enctype="multipart/form-data" data-confirm="Post these journals to the accounting ledger?">
            @csrf
            <label>Journal file <input class="form-control" name="ledger" type="file" accept=".csv,text/csv" required></label>
            <button type="submit" class="btn btn-primary">Post balanced journals</button>
        </form>
    </section>
@endif
@endsection
