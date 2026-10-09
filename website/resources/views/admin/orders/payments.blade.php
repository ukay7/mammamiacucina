@extends('admin.layout')
@section('title','Payments · '.$order->number)
@section('content')
<p><a href="{{ route('admin.orders.show',$order) }}">← Back to {{ $order->number }}</a> · {{ $order->first_name }} {{ $order->last_name }} · {{ $order->status_label }}</p>
<style>.payment-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:20px 0}.payment-metrics>div{padding:22px;background:#fffaf1;border:1px solid #dec9a8;border-radius:10px}.payment-metrics strong{display:block;font-size:25px;color:#44291d}.payment-entry{max-width:780px;padding:24px;background:#fffaf1;border:1px solid #dec9a8;border-radius:10px}.payment-entry .fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}.payment-entry label{display:block}.payment-positive{color:#247341}.payment-negative{color:#a00920}</style>
<div class="payment-metrics">
<div>Order total (CAD)<strong>{{ $order->final_total_cents===null?'Not finalized':'$'.number_format($order->final_total_cents/100,2) }}</strong></div>
<div>Net received (CAD)<strong>${{ number_format($order->net_received_cents/100,2) }}</strong></div>
<div>{{ $order->balance_cents<0?'Refund due':'Amount due' }} (CAD)<strong>${{ number_format(abs($order->balance_cents)/100,2) }}</strong></div>
</div>
<p>Positive entries are money received; negative entries are money refunded. Changing an order total changes the balance, without recording a refund until money is actually returned.</p>
<div class="panel panel-content table-responsive"><h2>Payment history</h2><table class="table"><thead><tr><th>Date</th><th>Entry / Method</th><th>Reference / Notes</th><th>Recorded by</th><th>Amount (CAD)</th><th>Receipt</th></tr></thead><tbody>
@if($order->collected_cents>0)
<tr><td>{{ $order->paid_at ?? $order->created_at }}</td><td>Original payment · {{ $order->payment_label }}</td><td>Original recorded collection</td><td>Order payment</td><td class="payment-positive">+${{ number_format($order->collected_cents/100,2) }}</td><td>@if($order->transfer_receipt_path)<a href="{{ route('orders.transfer-receipt',$order) }}" target="_blank" rel="noopener">View receipt</a>@else — @endif</td></tr>
@endif
@php($originalRefund=$order->payment ? (int)$order->payment->refunded_cents : ($order->manual_refunded_cents ?? ($order->payment_status==='refunded'?$order->collected_cents:0)))
@if($originalRefund>0)<tr><td>—</td><td>Original payment refund · {{ $order->payment_label }}</td><td>Previously recorded refund</td><td>Order payment</td><td class="payment-negative">−${{ number_format($originalRefund/100,2) }}</td><td>—</td></tr>@endif
@foreach($order->settlements->sortBy('id') as $entry)
<tr><td>{{ $entry->created_at->format('d M Y H:i') }}</td><td>{{ $entry->amount_cents<0?'Refund':'Payment received' }} · {{ ucfirst($entry->method) }}</td><td>{{ $entry->reference }}<small class="d-block">{{ $entry->note }}</small></td><td>{{ $entry->author?->name ?? 'Former user' }}</td><td class="{{ $entry->amount_cents<0?'payment-negative':'payment-positive' }}">{{ $entry->amount_cents<0?'−':'+' }}${{ number_format(abs($entry->amount_cents)/100,2) }}</td><td>@if($entry->receipt_path)<a href="{{ route('admin.orders.payment-receipt',[$order,$entry]) }}" target="_blank" rel="noopener">View receipt</a>@else — @endif</td></tr>
@endforeach
@if(!$order->collected_cents && !$order->settlements->count())<tr><td colspan="6">No verified payments recorded.</td></tr>@endif
</tbody></table></div>
@include('partials.transfer-receipt')
@if(auth()->user()->hasAdminPermission('orders.manage') && $order->balance_cents!==0 && $order->final_total_cents!==null)
<form method="post" enctype="multipart/form-data" action="{{ route('admin.orders.settlement',$order) }}" class="payment-entry">@csrf
<h2>Record {{ $order->balance_cents<0?'a refund':'a payment' }}</h2>
@if($order->payment_method==='etransfer')
<p>Record only funds you have verified in the bank account. Partial payments remain pending; once the full amount is confirmed, this pending order is sent to warehouse. Attach a receipt here if none has been uploaded to the order.</p>
@endif
<p>Record a completed transaction. This form does not charge a card or initiate a bank transfer.</p>
<input type="hidden" name="revision" value="{{ $order->revision }}"><input type="hidden" name="direction" value="{{ $order->balance_cents<0?'refund':'collect' }}">
<div class="fields"><label>Amount (CAD)<input class="form-control" name="amount" type="number" min="0.01" step="0.01" max="{{ abs($order->balance_cents)/100 }}" value="{{ old('amount',number_format(abs($order->balance_cents)/100,2,'.','')) }}" required></label><label>Method<select class="form-control" name="method">@foreach(['cash'=>'Cash','card'=>'Card','etransfer'=>'E-transfer','paypal'=>'PayPal'] as $value=>$label)<option value="{{ $value }}" @selected(old('method',$order->payment_method)===$value)>{{ $label }}</option>@endforeach</select></label><label>Transaction reference (required except cash)<input class="form-control" name="reference" maxlength="255" value="{{ old('reference') }}"></label><label>Receipt (JPG, PNG, WebP or PDF; max 5 MB)<input class="form-control" type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf"></label></div>
<label class="mt-3">Notes<textarea class="form-control" name="note" maxlength="1000" required>{{ old('note') }}</textarea></label>
<label class="my-3"><input type="checkbox" name="received" value="1" required> I confirm this money has actually been {{ $order->balance_cents<0?'refunded':'received' }}.</label><button class="btn btn-primary">Record {{ $order->balance_cents<0?'refund':'payment' }}</button>
</form>
@endif
@endsection
