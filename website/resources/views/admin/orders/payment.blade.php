@if($payment=$order->payment)
<div class="panel"><div class="panel-content">
<h2>Online payment · {{ ucfirst($payment->provider) }}</h2>
<p>{{ strtoupper($payment->mode) }} · {{ ucfirst(str_replace('_',' ',$order->payment_status)) }}<br>Transaction: {{ $payment->transaction_id ?: 'Awaiting confirmation' }}<br>Refunded: CAD {{ number_format($payment->refunded_cents/100,2) }}</p>
@if($payment->attention)<p class="alert alert-warning">{{ $payment->attention }}</p>@endif
<p>Online payment status and totals are verified with the gateway. Use these actions instead of recording a manual payment.</p>
@if(auth()->user()->hasAdminPermission('orders.manage'))
<div class="d-flex flex-wrap" style="gap:12px">
<form method="post" action="{{ route('admin.payments.reconcile',$payment) }}">@csrf<button class="btn btn-outline-primary">Reconcile payment</button></form>
@if(!$payment->paid_at && !$payment->released_at)<form method="post" action="{{ route('admin.payments.cancel',$payment) }}">@csrf<button class="btn btn-outline-primary">Cancel unpaid checkout</button></form>@endif
@if($payment->paid_at && $payment->refunded_cents < $payment->amount_cents && auth()->user()->hasAdminPermission('payments.refund'))
<form method="post" action="{{ route('admin.payments.refund',$payment) }}" data-confirm="Send the remaining refund to the customer through the payment gateway? This moves real money in live mode.">@csrf
<input type="hidden" name="expected_cents" value="{{ $payment->amount_cents-$payment->refunded_cents }}">
<label><input type="checkbox" name="confirm" value="REFUND" required> Confirm refund of CAD {{ number_format(($payment->amount_cents-$payment->refunded_cents)/100,2) }}</label>
<button class="btn btn-primary">Refund via gateway</button></form>
@endif
</div>
@endif
</div></div>
@endif
