<link rel="stylesheet" href="{{ asset('admin-assets/warehouse-orders.css') }}?v={{ filemtime(public_path('admin-assets/warehouse-orders.css')) }}">
<section class="wh-detail-summary">
    <div class="wh-detail-title"><h1><i class="fas fa-box" aria-hidden="true"></i> Order <strong>{{ $order->number }}</strong></h1><span class="mmc-status-pill" data-status="{{ $order->status }}">{{ $order->status_label }}</span></div>
    <div class="wh-detail-info">
        <div><i class="fas fa-user" aria-hidden="true"></i><div><strong>{{ $order->first_name }} {{ $order->last_name }}</strong><span>{{ $order->email }}</span><span>{{ $order->phone }}</span></div></div>
        <div><i class="fas fa-{{ $order->fulfillment==='pickup'?'store':'truck' }}" aria-hidden="true"></i><div><strong>{{ $order->fulfillment==='pickup'?'Pick up':'Delivery' }}</strong><span>{{ $order->fulfillment==='pickup'?'Pick up in store':trim($order->address.', '.$order->city.', '.$order->province.' '.$order->postal_code.', '.$order->country,', ') }}</span></div></div>
        <div><i class="fas fa-calendar-alt" aria-hidden="true"></i><div><strong>{{ $order->ordered_at->format('d M Y') }}</strong><span>{{ $order->ordered_at->format('H:i') }} · {{ $order->source==='pos'?'Quick Sale / POS':'Website' }}</span></div></div>
    </div>
    @if($order->notes)<div class="wh-customer-note"><strong>Customer instructions</strong><p>{{ $order->notes }}</p></div>@endif
</section>
<div class="wh-detail-navigation"><a class="btn btn-outline-primary" href="{{ route($order->status==='completed'?'admin.orders.completed':'admin.orders.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to Orders</a><a class="btn btn-outline-primary" href="{{ route('admin.orders.print',$order) }}" target="_blank" rel="noopener"><i class="fas fa-print" aria-hidden="true"></i> {{ $receiptView??false?'Print Receipt':'Print Packing Slip' }}</a></div>
@if($order->fulfillment==='delivery')<details class="wh-route-detail"><summary>Delivery service &amp; route</summary>@include('partials.order-delivery')</details>@endif
