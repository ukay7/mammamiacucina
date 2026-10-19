<link rel="stylesheet" href="{{ asset('admin-assets/warehouse-orders.css') }}?v={{ filemtime(public_path('admin-assets/warehouse-orders.css')) }}">
<section class="wh-detail-summary">
    <div class="wh-detail-title"><h1><i class="fas fa-box" aria-hidden="true"></i> Order <strong>{{ $order->number }}</strong></h1><span class="mmc-status-pill" data-status="{{ $order->status }}">{{ $order->status_label }}</span></div>
    <div class="wh-detail-info">
        <div><i class="fas fa-user" aria-hidden="true"></i><div><strong>{{ $order->first_name }} {{ $order->last_name }}</strong><span>{{ $order->email }}</span><span>{{ $order->phone }}</span></div></div>
        <div><i class="fas fa-{{ $order->fulfillment==='pickup'?'store':'truck' }}" aria-hidden="true"></i><div><strong>{{ $order->fulfillment==='pickup'?'Pick up':'Delivery' }}</strong><span>{{ $order->fulfillment==='pickup'?'Pick up in store':trim($order->address.', '.$order->city.', '.$order->province.' '.$order->postal_code.', '.$order->country,', ') }}</span></div></div>
        <div><i class="fas fa-calendar-alt" aria-hidden="true"></i><div><strong>{{ $order->ordered_at->format('d M Y') }}</strong><span>{{ $order->ordered_at->format('H:i') }} · {{ $order->source==='pos'?'Quick Sale / POS':'Website' }}</span></div></div>
    </div>
    @if($order->notes)<div class="wh-customer-note"><strong>Customer instructions</strong><p>{{ $order->notes }}</p></div>@endif
    @if($showFinancialSummary??false)<div class="order-detail-metrics"><div><span>Payment</span><strong>{{ $order->payment_label }}</strong><small>{{ $order->payment_status_label }}</small></div><div><span>Order total</span><strong>{{ $order->final_total_cents===null?'Not confirmed':'CAD '.number_format($order->final_total_cents/100,2) }}</strong><small>{{ $order->items->sum('quantity') }} {{ Str::plural('item',$order->items->sum('quantity')) }}</small></div><div><span>{{ $order->balance_cents<0?'Refund due':'Amount due' }}</span><strong>{{ $order->balance_cents===null?'Not confirmed':'CAD '.number_format(abs($order->balance_cents)/100,2) }}</strong><small>{{ $order->currency }}</small></div></div>@endif
</section>
<div class="wh-detail-navigation" aria-label="Order actions">
    <a class="btn btn-outline-primary" href="{{ route($order->status==='completed'?'admin.orders.completed':'admin.orders.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to Orders</a>
    <a class="btn btn-outline-primary" href="{{ route('admin.orders.print',$order) }}" target="_blank" rel="noopener"><i class="fas fa-print" aria-hidden="true"></i> {{ $printLabel??(($receiptView??false)?'Print Receipt':'Print Packing Slip') }}</a>
    @if($showOrderLinks??false)
        @if($order->invoice)<a class="btn btn-outline-primary" href="{{ route('admin.invoices.show',$order->invoice) }}" target="_blank" rel="noopener"><i class="fas fa-file-invoice" aria-hidden="true"></i> Invoice {{ $order->invoice->label }}</a>@endif
        <a class="btn btn-outline-primary" href="{{ route('admin.orders.payments',$order) }}"><i class="fas fa-money-check-alt" aria-hidden="true"></i> Payments &amp; receipts</a>
    @endif
</div>
@if($order->fulfillment==='delivery')<details class="wh-route-detail"><summary>Delivery service &amp; route</summary>@include('partials.order-delivery')</details>@endif
