@extends('admin.layout')
@section('title','Customer Receipt '.$receipt->number)
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/customer-receipts.css') }}?v={{ filemtime(public_path('admin-assets/customer-receipts.css')) }}">
<section class="panel panel-content">
    <div class="receipt-toolbar"><div><h2>{{ $receipt->number }}</h2><h3>{{ $receipt->customer_snapshot['business_name'] ?: $receipt->customer_snapshot['name'] }}</h3><p>Customer #{{ $customer->id }} · Recorded {{ $receipt->created_at->format('l, d M Y H:i') }}</p></div>
        <div><a class="btn btn-outline-primary" href="{{ route('admin.customers.show',$customer) }}#customer-payments">Payments &amp; Cheques</a> <a class="btn btn-primary" href="{{ route('admin.customers.receipts.create',$customer) }}">Receive Payment</a></div>
    </div>
    <div class="receipt-metrics"><div><span>Original payment lines</span><strong>${{ number_format($receipt->total_cents/100,2) }}</strong></div><div><span>Confirmed on this receipt</span><strong>${{ number_format($snapshot['confirmed_cents']/100,2) }}</strong></div><div><span>Awaiting cheque clearance</span><strong>${{ number_format($snapshot['pending_cents']/100,2) }}</strong></div><div><span>Current account outstanding</span><strong>${{ number_format($current['pending']/100,2) }}</strong></div></div>
    <p class="text-muted">Current account balances include later activity. Each saved document version preserves its recorded figures. Only allocations in the order ledger count toward collection reports.</p>
    <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('admin.customers.receipts.document',[$customer,$receipt]) }}">Print / View Receipt</a>
    <a class="btn btn-primary" href="{{ route('admin.customers.receipts.document',[$customer,$receipt,'pdf'=>1]) }}">Download PDF</a>
</section>
<section class="panel panel-content"><h2>Payment lines</h2>
    <div class="table-responsive"><table class="table receipt-lines-table"><thead><tr><th>Line / method</th><th>Amount CAD</th><th>Dates</th><th>Reference / notes</th><th>Status</th><th>Proof</th></tr></thead><tbody>
    @foreach($receipt->lines as $line)<tr><td>#{{ $line->id }} · {{ \App\Models\CustomerReceiptLine::METHODS[$line->method] }}</td><td>${{ number_format($line->amount_cents/100,2) }}</td><td>Received: {{ $line->payment_date->format('d M Y') }}@if($line->cheque_date)<br>Cheque: {{ $line->cheque_date->format('d M Y') }}@endif @if($line->cleared_on)<br>Cleared: {{ $line->cleared_on->format('d M Y') }}@endif</td><td class="receipt-history">{{ $line->reference }}@if($line->bank)<br>{{ $line->bank }}@endif @if($line->clearance_reference)<br>Bank clearance: {{ $line->clearance_reference }}@endif @if($line->note)<br>{{ $line->note }}@endif</td><td><strong>{{ $line->status_label }}</strong></td><td>@if($line->proof_path)<a target="_blank" rel="noopener" href="{{ route('admin.customers.receipts.proof',[$customer,$receipt,$line]) }}">{{ $line->proof_name ?: 'View proof' }}</a>@else No attachment @endif</td></tr>@endforeach
    </tbody></table></div>
    <p class="text-muted">A pending cheque is acknowledged, not treated as a cleared payment. Reversals record bookkeeping corrections; they do not send a customer refund.</p>
    @foreach($receipt->lines as $line)
    @php($actions=$line->status==='awaiting_clearance'?['clear'=>'Mark cheque cleared','bounce'=>'Cheque bounced / rejected','cancel'=>'Cancel uncleared cheque']:($line->status==='cleared'?['bounce'=>'Cheque bounced after clearance','reverse'=>'Reverse recorded payment']:($line->status==='confirmed'?['reverse'=>'Reverse recorded payment']:[])))
    @if($actions)
    <details class="receipt-action"><summary>Manage line #{{ $line->id }} · {{ \App\Models\CustomerReceiptLine::METHODS[$line->method] }} · ${{ number_format($line->amount_cents/100,2) }}</summary>
        <form method="post" action="{{ route('admin.customers.receipts.action',[$customer,$receipt,$line]) }}" data-receipt-action data-preview-url="{{ route('admin.customers.receipts.action-preview',[$customer,$receipt,$line]) }}" class="mt-3">
            @csrf<input type="hidden" name="revision" value="{{ $receipt->revision }}">
            <div class="receipt-fields"><div class="form-group"><label for="receipt-action-{{ $line->id }}">Action</label><select class="form-control" id="receipt-action-{{ $line->id }}" name="action">@foreach($actions as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                <div class="form-group"><label for="receipt-date-{{ $line->id }}">Clearance / correction date</label><input class="form-control" id="receipt-date-{{ $line->id }}" name="payment_date" type="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
                <div class="form-group receipt-wide"><label for="receipt-reference-{{ $line->id }}">Bank clearance / correction reference (required to clear a cheque)</label><input class="form-control" id="receipt-reference-{{ $line->id }}" name="reference" maxlength="150"></div>
                <div class="form-group receipt-wide"><label for="receipt-reason-{{ $line->id }}">Required reason / bank verification details</label><textarea class="form-control" id="receipt-reason-{{ $line->id }}" name="reason" rows="2" maxlength="2000" required></textarea></div>
            </div>
            <p class="mt-3">Clear only after confirming funds in the bank. Allocation is recalculated against current eligible balances. Cancel/bounce before clearance releases the pending reservation without recording money.</p>
            <button class="btn btn-outline-primary" type="button" data-action-preview>Preview Action</button>
            <div class="alert alert-danger mt-3" role="alert" data-action-error hidden></div>
            <section class="receipt-action-result" data-receipt-result hidden><div class="receipt-metrics" data-receipt-preview-summary></div><div class="table-responsive"><table class="table"><thead><tr><th>Order / invoice</th><th>Balance before</th><th>Action / allocations</th><th>Due after action</th><th>After clearance</th></tr></thead><tbody data-receipt-preview-rows></tbody></table></div>
                <label class="d-block my-3"><input type="checkbox" name="confirmed" value="1"> I verified the actual payment records and confirm this action.</label><button class="btn btn-primary" type="submit" data-action-confirm disabled>Confirm Action</button>
            </section>
        </form>
    </details>
    @endif
    @endforeach
</section>
<section class="panel panel-content"><h2>Actual order entries</h2><div class="table-responsive"><table class="table"><thead><tr><th>Order / invoice</th><th>Payment line</th><th>Entry</th><th>Payment date</th><th>Amount CAD</th><th>Balance at last receipt action</th></tr></thead><tbody>
@php($allocationCount=0)
@foreach($snapshot['lines'] as $line)@foreach($line['allocations'] as $allocation)@php($allocationCount++)<tr><td>{{ $allocation['number'] }}<br>{{ $allocation['invoice'] }}</td><td>#{{ $line['id'] }} · {{ \App\Models\CustomerReceiptLine::METHODS[$line['method']] }}</td><td>{{ ucfirst(str_replace('_',' ',$allocation['kind'])) }} · #{{ $allocation['id'] }}</td><td>{{ $allocation['date'] }}</td><td>{{ $allocation['amount_cents']<0?'−':'+' }}${{ number_format(abs($allocation['amount_cents'])/100,2) }}</td><td>${{ number_format(max(0,$allocation['balance_cents'])/100,2) }}</td></tr>@endforeach @endforeach
@if(!$allocationCount)<tr><td colspan="6">No confirmed allocations yet. Cheques are awaiting clearance.</td></tr>@endif
</tbody></table></div></section>
<section class="panel panel-content"><h2>Full audit history &amp; receipt versions</h2><div class="table-responsive"><table class="table"><thead><tr><th>Recorded</th><th>Staff member</th><th>Action</th><th>Reason / reference</th><th>Preserved document</th></tr></thead><tbody>
@foreach($receipt->events as $event)<tr><td>{{ $event->created_at->format('l, d M Y H:i') }}</td><td>{{ $event->actor_name }}</td><td>{{ ucfirst($event->action) }}@if($event->customer_receipt_line_id)<br>Line #{{ $event->customer_receipt_line_id }}@endif</td><td class="receipt-history">{{ $event->reason }}@if(isset($event->snapshot['change']['before_outstanding_cents']))<br><small>Account outstanding: ${{ number_format($event->snapshot['change']['before_outstanding_cents']/100,2) }} → ${{ number_format($event->snapshot['outstanding_cents']/100,2) }}</small>@endif @if($event->reference)<br>{{ $event->reference }}@endif</td><td><a target="_blank" rel="noopener" href="{{ route('admin.customers.receipts.document',[$customer,$receipt,'version'=>$event->id]) }}">View version #{{ $event->id }}</a><br><a href="{{ route('admin.customers.receipts.document',[$customer,$receipt,'version'=>$event->id,'pdf'=>1]) }}">Download PDF</a></td></tr>@endforeach
</tbody></table></div></section>
<script defer src="{{ asset('admin-assets/customer-receipts.js') }}?v={{ filemtime(public_path('admin-assets/customer-receipts.js')) }}"></script>
@endsection
