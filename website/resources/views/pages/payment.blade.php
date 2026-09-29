@extends('layouts.mmc-page',['pageTitle'=>'Order Payment'])
@section('page-content')
<section class="mmc-panel" style="max-width:760px;margin:auto">
<p class="mmc-eyebrow">ORDER {{ $payment->order->number }}</p>
<h2>{{ $payment->released_at && !$payment->paid_at ? 'Checkout cancelled or expired' : ($payment->paid_at ? 'Payment received' : 'Complete your payment') }}</h2>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<p><strong>Total: CAD {{ number_format($payment->amount_cents/100,2) }}</strong></p>
@include('partials.order-delivery',['order'=>$payment->order])
<p>Payment method: {{ $payment->provider==='stripe'?'Card · Stripe':'PayPal' }} · {{ ucfirst(str_replace('_',' ',$payment->order->payment_status)) }}</p>
@if($payment->order->status==='payment_review')
<p>Your payment was received after the stock reservation ended. Please contact us with your order number so we can resolve this. Do not pay again.</p>
@elseif($payment->released_at && !$payment->paid_at)
<p>No payment has been confirmed. Reserved stock has been released. You can start a new order.</p>
<a class="mmc-button" href="{{ route('theme.product-grid') }}">Continue shopping</a>
@elseif($payment->status==='refunded' || $payment->status==='partially_refunded')
<p>Refund confirmed: CAD {{ number_format($payment->refunded_cents/100,2) }}. Please contact us for any questions.</p>
@else
<p>You will complete a one-time payment on {{ $payment->provider==='stripe'?'Stripe':'PayPal' }}. We do not store your card details.</p>
<p>Stock is reserved while you complete checkout. If you have already paid, check payment status before trying again.</p>
@if(!$payment->paid_at && !$payment->expires_at->isPast())
<form method="post" action="{{ route('payment.start',$payment->reference) }}">@csrf<button class="mmc-button" type="submit">Continue to {{ $payment->provider==='stripe'?'secure card payment':'PayPal' }}</button></form>
@endif
<form method="post" action="{{ route('payment.check',$payment->reference) }}" style="margin-top:16px">@csrf<button class="mmc-button" type="submit">Check payment status</button></form>
@if(!$payment->paid_at)
<form method="post" action="{{ route('payment.cancel',$payment->reference) }}" style="margin-top:16px">@csrf<button class="mmc-text-link" type="submit">Cancel unpaid order</button></form>
@endif
@endif
</section>
@endsection
