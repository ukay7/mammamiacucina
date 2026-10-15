@extends('admin.layout')
@section('title','Archived order · '.$order->number)
@section('content')
<div class="alert alert-info"><strong>Archived · Read-only</strong> — {{ $order->archived_at->format('d M Y H:i') }}. No order, packing or payment changes are allowed.</div>
@if(!auth()->user()->isWarehouseOnly())<a class="btn btn-outline-primary mb-3" href="{{ route('admin.orders.archived') }}">Archived Orders</a>@endif
@if(auth()->user()->hasAdminPermission('orders.view'))
<a class="btn btn-outline-primary mb-3" href="{{ route('admin.orders.print',$order) }}">Print receipt</a>
<a class="btn btn-outline-primary mb-3" href="{{ route('admin.orders.payments',$order) }}">Payment history & receipts</a>
@endif
<section class="panel panel-content">
<h2>{{ $order->number }}</h2><p>Final order status: {{ $order->status_label }} · Payment: {{ $order->payment_status }}</p>
<p>{{ $order->first_name }} {{ $order->last_name }}<br>{{ $order->email }}<br>{{ $order->phone }}</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Product</th><th>Code</th><th>Quantity</th><th>Unit price</th><th>Total</th></tr></thead><tbody>
@foreach($order->items as $item)<tr><td>{{ $item->name }}</td><td>{{ $item->product_code ?? '—' }}</td><td>{{ $item->quantity }}</td><td>${{ number_format($item->unit_cents/100,2) }}</td><td>${{ number_format($item->line_cents/100,2) }}</td></tr>@endforeach
</tbody></table></div>
<p>Order total: {{ $order->final_total_cents===null?'Not confirmed':'$'.number_format($order->final_total_cents/100,2) }} · Net received: ${{ number_format($order->net_received_cents/100,2) }} · Balance: ${{ number_format($order->balance_cents/100,2) }}</p>
<h3>Payment history</h3>
@if($order->payment)<p>{{ $order->payment->provider }} · {{ $order->payment->status }} · ${{ number_format($order->payment->amount_cents/100,2) }}</p>@endif
@foreach($order->settlements as $entry)<p>{{ $entry->created_at }} · {{ $entry->method }} · ${{ number_format($entry->amount_cents/100,2) }} · {{ $entry->note }}</p>@endforeach
<h3>Order history</h3>@foreach($order->events as $event)<p>{{ $event->created_at }} · {{ $event->author?->name ?? 'System' }}<br>{{ $event->description }}</p>@endforeach
</section>
@endsection
