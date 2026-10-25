<div class="tab-pane fade" id="customer-invoices" role="tabpanel" aria-labelledby="customer-tab-invoices">

<h3>Invoices</h3><p>One invoice per order, issued after packing confirmation or handover. Amounts are in CAD.</p>

<form method="get" action="{{ route('admin.customers.show',$customer) }}#customer-invoices" class="d-flex mb-3" style="gap:10px"><input name="invoice_search" class="form-control" placeholder="Invoice number, order number or order ID" value="{{ request('invoice_search') }}"><button class="btn btn-primary">Search</button></form>

<div class="table-responsive"><table class="table"><thead><tr><th>Invoice / order</th><th>Issued</th><th>Invoice total</th><th>Net received</th><th>Balance</th><th>Status</th><th>Actions</th></tr></thead><tbody>

@forelse($invoices as $invoice)@php($o=$invoice->order)<tr><td>{{ $invoice->label }}<br><a href="{{ route('admin.orders.show',$o) }}">{{ $o->number }} · #{{ $o->id }}</a></td><td>{{ $invoice->issued_at->format('d M Y H:i') }}@if($invoice->historical)<br><small>Existing order</small>@endif</td><td>${{ number_format($invoice->total_cents/100,2) }}@if($o->final_total_cents!==null && $o->final_total_cents!=$invoice->total_cents)<br><small>Order adjustment: ${{ number_format(($o->final_total_cents-$invoice->total_cents)/100,2) }}</small>@endif</td><td>${{ number_format($o->net_received_cents/100,2) }}</td><td>${{ number_format(max(0,$o->balance_cents)/100,2) }}@if($o->balance_cents<0)<br>Refund due: ${{ number_format(-$o->balance_cents/100,2) }}@endif</td><td>{{ $o->status==='cancelled'?'Cancelled':($o->balance_cents>0?'Outstanding':($o->balance_cents<0?'Refund due':'Settled')) }}</td><td><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.invoices.show',$invoice) }}" target="_blank" rel="noopener" title="View, print or save PDF">View / PDF</a>@if(auth()->user()->hasAdminPermission('customers.invoice-email'))<form method="post" action="{{ route('admin.invoices.email',$invoice) }}" class="mt-2">@csrf<button class="btn btn-outline-primary btn-sm" title="Email invoice link to the order customer">Email invoice</button></form>@endif</td></tr>

@empty<tr><td colspan="7">No invoices issued yet. Packing must be confirmed first.</td></tr>@endforelse

</tbody></table></div>{{ $invoices->withQueryString()->fragment('customer-invoices')->links() }}</div>

<div class="tab-pane fade" id="customer-payments" role="tabpanel" aria-labelledby="customer-tab-payments">

<h3>Payments & Cheques</h3><p>Order payment arrangements and subsequent receipts/refunds. Unpaid amounts are expected payments, not money received. Amounts are in CAD.</p>

<form method="get" action="{{ route('admin.customers.show',$customer) }}#customer-payments" class="d-flex flex-wrap mb-3" style="gap:10px"><input class="form-control" style="max-width:300px" name="payment_search" placeholder="Order number or order ID" value="{{ request('payment_search') }}"><select class="form-control" style="width:auto" name="payment_method"><option value="">All methods</option>@foreach(\App\Services\CustomerTerms::METHODS as $key=>$label)<option value="{{ $key }}" @selected(request('payment_method')===$key)>{{ $label }}</option>@endforeach</select><button class="btn btn-primary">Search</button></form>

<div class="table-responsive"><table class="table"><thead><tr><th>Date / reference</th><th>Order</th><th>Method</th><th>Amount</th><th>Status / received</th><th>Proof / details</th></tr></thead><tbody>

@forelse($paymentOrders as $o)

<tr><td>{{ $o->paid_at ? \Illuminate\Support\Carbon::parse($o->paid_at)->format('d M Y H:i') : $o->ordered_at->format('d M Y H:i') }}<br><small>{{ $o->payment?->transaction_id ?: 'Order payment arrangement' }}</small></td><td><a href="{{ route('admin.orders.show',$o) }}">{{ $o->number }} · #{{ $o->id }}</a></td><td>{{ $o->payment_label }}@if($o->payment?->mode==='sandbox')<br>Sandbox @endif</td><td>{{ $o->final_total_cents===null?'Unconfirmed':'$'.number_format(($o->collected_cents?:$o->final_total_cents)/100,2) }}</td><td>{{ $o->status==='cancelled' && !$o->collected_cents?'Cancelled — no payment received':$o->payment_status_label }}<br>Initially received: ${{ number_format($o->collected_cents/100,2) }}@if($o->payment?->refunded_cents || $o->manual_refunded_cents)<br>Refunded: ${{ number_format(($o->payment?->refunded_cents ?? $o->manual_refunded_cents)/100,2) }}@endif @if($o->payment_method==='cheque' && $o->cheque_due_date)<br><small>Due: {{ $o->cheque_due_date }}@if($o->status!=='cancelled' && $o->balance_cents>0 && \Illuminate\Support\Carbon::parse($o->cheque_due_date)->lt(today())) · <strong class="text-danger">Overdue</strong>@endif</small>@endif</td><td>@if($o->collected_cents>0)<a href="{{ route('admin.orders.initial-payment-document',$o) }}" target="_blank" rel="noopener">Print receipt / PDF</a><br>@endif @if($o->transfer_receipt_path)<a href="{{ route('orders.transfer-receipt',$o) }}" target="_blank" rel="noopener">View proof</a>@elseif(in_array($o->payment_method,['cheque','etransfer']))<small>No proof uploaded</small>@endif<br><a href="{{ route('admin.orders.show',$o) }}">View payment / order</a></td></tr>

@foreach($o->settlements as $entry)<tr><td>{{ $entry->created_at->format('d M Y H:i') }}<br><small>Payment entry #{{ $entry->id }} / {{ $entry->reference }}</small></td><td>{{ $o->number }} · #{{ $o->id }}</td><td>{{ \App\Services\CustomerTerms::METHODS[$entry->method] ?? ucfirst($entry->method) }}</td><td>${{ number_format(abs($entry->amount_cents)/100,2) }}</td><td>{{ $entry->entry_label }}<br><small>{{ $entry->note }}</small></td><td><a href="{{ route('admin.orders.payment-document',[$o,$entry]) }}" target="_blank" rel="noopener">Print receipt / PDF</a><br>@if($entry->receipt_path)<a href="{{ route('admin.orders.payment-receipt',[$o,$entry]) }}" target="_blank" rel="noopener">View receipt</a>@else<small>No proof uploaded</small>@endif<br><a href="{{ route('admin.orders.show',$o) }}">View order</a></td></tr>@endforeach

@empty<tr><td colspan="6">No payment records yet.</td></tr>@endforelse

</tbody></table></div>{{ $paymentOrders->withQueryString()->fragment('customer-payments')->links() }}</div>
