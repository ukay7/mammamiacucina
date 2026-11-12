<link rel="stylesheet" href="{{ asset('admin-assets/warehouse-orders.css') }}?v={{ filemtime(public_path('admin-assets/warehouse-orders.css')) }}">
@php
    $customerProfile=$order->customer;
    $businessCustomer=$customerProfile?->account_type==='business';
    $businessName=$businessCustomer?trim((string)$customerProfile->business_name):'';
    $orderContactName=trim($order->first_name.' '.$order->last_name) ?: ($customerProfile?->name ?: 'Customer');
    $customerHeading=$businessName!==''?$businessName:$orderContactName;
    $customerType=$customerProfile?($businessCustomer?'Business customer':'Individual customer'):'Guest customer';
    $customerNode=\App\Services\AdminNavigation::nodes()['access.admin.customers.index']??null;
    $canOpenCustomerProfile=$customerProfile && $customerNode && auth()->user()->hasAdminPermission('orders.view') && \App\Services\AdminNavigation::allowed(auth()->user(),$customerNode);
@endphp
<section class="wh-detail-summary">
    <div class="wh-detail-title"><h1><i class="fas fa-box" aria-hidden="true"></i> Order <strong>{{ $order->number }}</strong></h1><span class="mmc-status-pill" data-status="{{ $order->status }}">{{ $order->status_label }}</span></div>
    <div class="wh-detail-info">
        <div class="wh-order-customer"><i class="fas fa-{{ $businessCustomer?'building':'user' }}" aria-hidden="true"></i><div>
            <span class="wh-order-customer-type">{{ $customerType }}</span>
            <div class="wh-order-customer-heading"><strong>{{ $customerHeading }}</strong>@if($canOpenCustomerProfile)<a class="btn btn-outline-primary wh-order-customer-profile" href="{{ route('admin.customers.show',$customerProfile) }}" title="Open customer profile" aria-label="Open customer profile for {{ $customerHeading }}"><i class="fas fa-user-circle" aria-hidden="true"></i></a>@endif</div>
            @if($businessName!=='')<span><b>Customer:</b> {{ $orderContactName }}</span>@endif
            <span><b>Email:</b> {{ filled($order->email)?$order->email:'Not provided' }}</span>
            <span><b>Phone:</b> {{ filled($order->phone)?$order->phone:'Not provided' }}</span>
        </div></div>
        <div><i class="fas fa-{{ $order->fulfillment==='pickup'?'store':'truck' }}" aria-hidden="true"></i><div><strong>{{ $order->fulfillment==='pickup'?'Pick up':'Delivery' }}</strong><span>{{ $order->fulfillment==='pickup'?'Pick up in store':trim($order->address.', '.$order->city.', '.$order->province.' '.$order->postal_code.', '.$order->country,', ') }}</span></div></div>
        <div><i class="fas fa-calendar-alt" aria-hidden="true"></i><div><strong>{{ \App\Support\BusinessTime::format($order->ordered_at,'d M Y') }}</strong><span>{{ \App\Support\BusinessTime::format($order->ordered_at,'H:i T') }} · {{ $order->source==='pos'?'Quick Sale / POS':'Website' }}</span></div></div>
    </div>
    @include('partials.order-delivery-schedule')
    @if($order->notes)<div class="wh-customer-note"><strong>Customer instructions</strong><p>{{ $order->notes }}</p></div>@endif
    @if($showFinancialSummary??false)<div class="order-detail-metrics"><div><span>Payment</span><strong>{{ $order->payment_label }}</strong><small>{{ $order->payment_status_label }}</small></div><div><span>Order total</span><strong>{{ $order->final_total_cents===null?'Not confirmed':'CAD '.number_format($order->final_total_cents/100,2) }}</strong><small>{{ $order->items->sum('quantity') }} {{ Str::plural('item',$order->items->sum('quantity')) }}</small></div><div><span>{{ $order->balance_cents<0?'Refund due':'Amount due' }}</span><strong>{{ $order->balance_cents===null?'Not confirmed':'CAD '.number_format(abs($order->balance_cents)/100,2) }}</strong><small>{{ $order->currency }}</small></div></div>@endif
</section>
@php($inventoryShortage=$order->items->sum(fn($item)=>(float)($item->inventory_shortage_quantity??0)))
@php($inventoryUnset=$order->items->contains(fn($item)=>$item->inventory_shortage_quantity===null))
@if($inventoryShortage>0 || $inventoryUnset)<div class="alert alert-warning wh-inventory-warning" role="alert"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i><div><strong>Inventory attention required</strong>@if($inventoryShortage>0)<span>This order was accepted with {{ \App\Support\NumberDisplay::quantity($inventoryShortage) }} units beyond available tracked stock. Review the highlighted packing items.</span>@endif @if($inventoryUnset)<span>At least one product did not have opening inventory configured when this order was placed.</span>@endif</div></div>@endif
<div class="wh-detail-navigation" aria-label="Order actions">
    <a class="btn btn-outline-primary" href="{{ route($order->status==='completed'?'admin.orders.completed':'admin.orders.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to Orders</a>
    <a class="btn btn-outline-primary" href="{{ route('admin.orders.print',$order) }}" target="_blank" rel="noopener"><i class="fas fa-print" aria-hidden="true"></i> {{ $printLabel??(($receiptView??false)?'Print Receipt':'Print Packing Slip') }}</a>
    @include('admin.orders.delivery-actions')
    @if($showOrderLinks??false)
        @if($order->invoice)<a class="btn btn-outline-primary" href="{{ route('admin.invoices.show',$order->invoice) }}" target="_blank" rel="noopener"><i class="fas fa-file-invoice" aria-hidden="true"></i> Invoice {{ $order->invoice->label }}</a>@endif
        <a class="btn btn-outline-primary" href="{{ route('admin.orders.payments',$order) }}"><i class="fas fa-money-check-alt" aria-hidden="true"></i> Payments &amp; receipts</a>
    @endif
</div>
@if($order->fulfillment==='delivery')<details class="wh-route-detail"><summary>Delivery service &amp; route</summary>@include('partials.order-delivery')</details>@endif
