@extends('admin.layout')
@section('title','Deleted order · '.$order->number)
@section('content')
<a class="btn btn-outline-primary mb-3" href="{{ route('admin.orders.deleted.index') }}">Deleted Orders</a>
<div class="alert alert-info"><strong>Deleted · View only</strong><br>{{ $order->deleted_at->format('d M Y H:i') }} · {{ $order->deletedBy?->name ?? 'Former user / migration' }}<br>Reason: {{ $order->deletion_reason }}<br>Inventory decision: {{ $order->deletion_restock?'Return deducted stock':'Keep inventory unchanged' }}. This retained order does not contribute to normal reports or balances.</div>
<section class="panel panel-content">
<h2>{{ $order->number }}</h2><p>Order date: {{ $order->ordered_at->format('d M Y H:i') }} · Status when deleted: {{ $order->status_label }} · Payment: {{ $order->payment_status_label }}</p>
<p>{{ $order->first_name }} {{ $order->last_name }}<br>{{ $order->email }}<br>{{ $order->phone }}</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Product</th><th>Code</th><th>Quantity</th><th>Unit price (CAD)</th><th>Total (CAD)</th></tr></thead><tbody>
@foreach($order->items as $item)<tr><td>{{ $item->name }}</td><td>{{ $item->product_code ?: '—' }}</td><td>{{ $item->quantity }}</td><td>{{ number_format($item->unit_cents/100,2) }}</td><td>{{ number_format($item->line_cents/100,2) }}</td></tr>@endforeach
</tbody></table></div>
<p>Order total at deletion: {{ $order->final_total_cents===null?'Not confirmed':'C$'.number_format($order->final_total_cents/100,2) }} · Recorded net received: C${{ number_format($order->net_received_cents/100,2) }} · Recorded balance: C${{ number_format($order->balance_cents/100,2) }}</p>
</section>
<section class="panel panel-content"><h2>Retained invoice · View only</h2>
@if($invoice=$order->invoice)
<p>{{ $invoice->label }} · Issued {{ $invoice->issued_at->format('d M Y H:i') }} · Original total: C${{ number_format($invoice->total_cents/100,2) }}</p>
<p>{{ data_get($invoice->snapshot,'order.first_name') }} {{ data_get($invoice->snapshot,'order.last_name') }} · {{ data_get($invoice->snapshot,'order.email') }}</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Original invoiced item</th><th>Code</th><th>Quantity</th><th>Unit price (CAD)</th><th>Total (CAD)</th></tr></thead><tbody>
@foreach(data_get($invoice->snapshot,'items',[]) as $item)<tr><td>{{ $item['name'] }}</td><td>{{ $item['product_code'] ?? '—' }}</td><td>{{ $item['quantity'] }}</td><td>{{ number_format($item['unit_cents']/100,2) }}</td><td>{{ number_format($item['line_cents']/100,2) }}</td></tr>@endforeach
</tbody></table></div><p>Original invoice snapshot retained. This is a deleted-order audit record.</p>
@else<p>No invoice was issued.</p>@endif
</section>
<section class="panel panel-content"><h2>Retained payments & refunds · View only</h2>
@if($order->payment)<p>{{ ucfirst($order->payment->provider) }} · {{ $order->payment->mode }} · {{ $order->payment->status }} · Reference {{ $order->payment->transaction_id ?? $order->payment->reference }}</p>@endif
@foreach($order->paymentAttempts->whereNotNull('superseded_at') as $attempt)<p>Superseded checkout retained · {{ ucfirst($attempt->provider) }} · {{ $attempt->status }} · {{ $attempt->reference }}</p>@endforeach
<p>Originally collected: C${{ number_format($order->collected_cents/100,2) }} · Originally recorded refund: C${{ number_format(($order->payment?->refunded_cents ?? $order->manual_refunded_cents ?? ($order->payment_status==='refunded'?$order->collected_cents:0))/100,2) }}</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Entry / Method</th><th>Reference / Notes</th><th>Recorded by</th><th>Amount (CAD)</th></tr></thead><tbody>
@foreach($order->settlements as $entry)<tr><td>{{ $entry->created_at->format('d M Y H:i') }}</td><td>{{ $entry->entry_label }} · {{ $entry->method }}</td><td>{{ $entry->reference }}<small class="d-block">{{ $entry->note }}</small></td><td>{{ $entry->author?->name ?? 'Former user' }}</td><td>{{ number_format($entry->amount_cents/100,2) }}</td></tr>@endforeach
@foreach($refunds as $refund)<tr><td>{{ $refund->created_at }}</td><td>Gateway refund · {{ $refund->status }}</td><td>{{ $refund->provider_refund_id ?? '—' }}</td><td>Gateway</td><td>−{{ number_format($refund->amount_cents/100,2) }}</td></tr>@endforeach
@if($order->settlements->isEmpty() && $refunds->isEmpty())<tr><td colspan="5">No additional payment or refund entries.</td></tr>@endif
</tbody></table></div></section>
<section class="panel panel-content"><h2>Order history · View only</h2>@foreach($order->events as $event)<p>{{ $event->created_at->format('d M Y H:i') }} · {{ $event->author?->name ?? 'System' }}<br>{{ $event->description }}</p>@endforeach</section>
@endsection
