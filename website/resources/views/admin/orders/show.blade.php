@extends('admin.layout')
@section('title','Order '.$order->number)
@section('page-class','warehouse-detail-page order-detail-page')
@section('hide-page-heading',true)
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/warehouse-detail.css') }}?v={{ filemtime(public_path('admin-assets/warehouse-detail.css')) }}">
<link rel="stylesheet" href="{{ asset('admin-assets/order-detail.css') }}?v={{ filemtime(public_path('admin-assets/order-detail.css')) }}">

@include('admin.orders.detail-header',['printLabel'=>'Print Order / PDF','showFinancialSummary'=>true,'showOrderLinks'=>true])

<div class="order-detail-notices">
    @if($order->status === 'cancelled' && $order->cancellation_restock !== null)
        <div class="alert alert-info" role="status"><strong>Cancellation stock decision:</strong> {{ $order->cancellation_restock ? 'Yes — quantities previously deducted were returned to inventory.' : 'No — inventory was left unchanged.' }}</div>
    @endif
    @include('partials.order-commercial')
    @include('partials.transfer-receipt')
    @include('admin.orders.payment')
</div>

@include('admin.orders.packing',['commercialShown'=>true])

{{-- Payment adjustments panel remains hidden at the user's request. --}}
<section class="order-detail-workspace" aria-label="Order details and controls">
@if(auth()->user()->hasAdminPermission('orders.manage'))
    <div class="order-detail-section-heading">
        <div><span class="order-detail-eyebrow">ADMIN TOOLS</span><h2 id="order-admin-tools">Order controls</h2></div>
        <p>Use these controls only when the order needs a correction or manual decision.</p>
    </div>

    @if(!in_array($order->status,['cancelled','completed','delivered','out_for_delivery','awaiting_payment','payment_review']))
    <details class="order-detail-disclosure" @if($errors->hasAny(['items','first_name','last_name','email','phone','address','city','province','postal_code','country','fulfillment','delivery','tax','reason'])) open @endif>
        <summary><span><i class="fas fa-edit" aria-hidden="true"></i><strong>Edit products &amp; customer details</strong><small>Quantities, prices, fulfillment, address and charges</small></span><i class="fas fa-chevron-down order-detail-chevron" aria-hidden="true"></i></summary>
        <div class="order-detail-disclosure-body">
            @if($order->fulfillment === 'pickup' && $order->pickup_address)<div class="order-detail-inline-note"><strong>Pickup address</strong><span>{{ $order->pickup_address }}</span></div>@endif
            @include('admin.orders.amend')
        </div>
    </details>
    @endif

    <div class="order-detail-control-grid">
        <section class="order-detail-control-card">
            <div class="order-detail-card-title"><i class="fas fa-calendar-alt" aria-hidden="true"></i><div><h3>Correct Order Date</h3><p>Creation time stays unchanged in the audit record.</p></div></div>
            <form method="post" action="{{ route('admin.orders.order-date',$order) }}">@csrf @method('PATCH')
                <input type="hidden" name="revision" value="{{ $order->revision }}">
                <label for="order-date">Order Date and time</label>
                <div class="order-detail-inline-form"><input class="form-control" id="order-date" type="datetime-local" name="ordered_at" max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('ordered_at',$order->ordered_at->format('Y-m-d\TH:i')) }}" required><button class="btn btn-primary" type="submit">Save date</button></div>
                @if(!$order->ordered_at->equalTo($order->created_at))<small>Entered into the system {{ $order->created_at->format('d M Y H:i') }}</small>@endif
            </form>
        </section>

        <section class="order-detail-control-card">
            <div class="order-detail-card-title"><i class="fas fa-tasks" aria-hidden="true"></i><div><h3>Status &amp; payment</h3><p>Record an operational or payment decision.</p></div></div>
            <form method="post" action="{{ route('admin.orders.update',$order) }}" data-cancellation-kind="status" data-current-status="{{ $order->status }}" data-order-number="{{ $order->number }}">@csrf @method('PATCH')
                @if($order->payment)<input type="hidden" name="payment_status" value="{{ $order->payment_status }}">@endif
                <input type="hidden" name="revision" value="{{ $order->revision }}">
                <div class="order-detail-form-grid"><label>Status<select class="form-control" name="status">@foreach(($order->status_options + (!$order->payment && !in_array($order->status,['completed','delivered','out_for_delivery','cancelled']) ? ['cancelled'=>'Cancelled'] : [])) as $key=>$label)<option value="{{ $key }}" @selected(old('status',$order->status)===$key)>{{ $label }}</option>@endforeach</select></label><label>Payment status<select class="form-control" name="payment_status" @if($order->payment) disabled @endif>@foreach(($order->payment?['pending'=>'Pending gateway confirmation','partially_refunded'=>'Partially refunded']:[])+['unpaid'=>'Unpaid','paid'=>'Paid — payment received','refunded'=>'Refunded — payment returned'] as $key=>$label)<option value="{{ $key }}" @selected(old('payment_status',$order->payment_status)===$key)>{{ $label }}</option>@endforeach</select></label><label>Delivery charge (CAD)<input class="form-control" type="number" data-number-display="decimal" min="0" max="9999999.99" step="0.01" name="delivery" @readonly($order->payment || $order->delivery_service) value="{{ old('delivery',$order->delivery_cents===null?'':number_format($order->delivery_cents/100,2,'.','')) }}"></label><label>Tax amount (CAD)<input class="form-control" type="number" data-number-display="decimal" min="0" max="9999999.99" step="0.01" name="tax" @readonly($order->payment) value="{{ old('tax',$order->tax_cents===null?'':number_format($order->tax_cents/100,2,'.','')) }}"></label></div>
                @include('admin.orders.cancellation-fallback')
                <details class="order-detail-note-toggle"><summary>Add an internal change note</summary><textarea class="form-control" name="reason" maxlength="1000" placeholder="Reason for this change">{{ old('reason') }}</textarea></details>
                <div class="order-detail-card-footer"><small>Confirm money was received before marking Paid. Return collected funds before cancelling a paid order.</small><button class="btn btn-primary" type="submit">Save order</button></div>
            </form>
        </section>
    </div>
@endif

    <details class="order-detail-disclosure order-detail-secondary">
        <summary><span><i class="fas fa-history" aria-hidden="true"></i><strong>Order history</strong><small>{{ $order->events->count() }} recorded {{ Str::plural('event',$order->events->count()) }}</small></span><i class="fas fa-chevron-down order-detail-chevron" aria-hidden="true"></i></summary>
        <div class="order-detail-disclosure-body order-detail-timeline">@forelse($order->events as $event)<article><span aria-hidden="true"></span><div><strong>{{ $event->created_at->format('d M Y H:i') }} · {{ $event->author?->name ?? 'System' }}</strong><p>{{ $event->description }}</p></div></article>@empty<p>No changes recorded yet.</p>@endforelse</div>
    </details>

    @if(auth()->user()->isSuper())
    <details class="order-detail-disclosure order-detail-secondary">
        <summary><span><i class="fas fa-trash-alt" aria-hidden="true"></i><strong>Delete order</strong><small>Super Admin only · retained records become view-only</small></span><i class="fas fa-chevron-down order-detail-chevron" aria-hidden="true"></i></summary>
        <div class="order-detail-disclosure-body order-detail-deletion">@include('admin.orders.delete-action')</div>
    </details>
    @endif
</section>
@endsection
