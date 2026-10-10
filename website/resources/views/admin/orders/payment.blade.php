@if($payment=$order->payment)
<div class="panel"><div class="panel-content">
<h2>Online payment · {{ ucfirst($payment->provider) }}</h2>
<p>{{ strtoupper($payment->mode) }} · {{ ucfirst(str_replace('_',' ',$order->payment_status)) }}<br>Transaction: {{ $payment->transaction_id ?: 'Awaiting confirmation' }}<br>Refunded: CAD {{ number_format($payment->refunded_cents/100,2) }}</p>
@if($payment->attention)<p class="alert alert-warning">{{ $payment->attention }}</p>@endif
@if($payment->provider==='helcim')<p>Helcim invoice: {{ $payment->provider_order_id ?: 'Awaiting checkout' }} · <a href="{{ route('admin.orders.print',$order) }}" target="_blank" rel="noopener">Print invoice / receipt</a></p>@endif
<p>Online payment status and totals are verified with the gateway. Use these actions instead of recording a manual payment.</p>
@if(auth()->user()->hasAdminPermission('orders.manage'))
<div class="d-flex flex-wrap" style="gap:12px">
<form method="post" action="{{ route('admin.payments.reconcile',$payment) }}">@csrf<button class="btn btn-outline-primary">Reconcile payment</button></form>
@if(!$payment->paid_at && !$payment->released_at)<form method="post" action="{{ route('admin.payments.cancel',$payment) }}">@csrf<button class="btn btn-outline-primary">Cancel unpaid checkout</button></form>@endif
@if($payment->paid_at && $payment->refunded_cents < $payment->amount_cents && auth()->user()->hasAdminPermission('payments.refund'))
<form method="post" action="{{ route('admin.payments.refund',$payment) }}" data-confirm="Send this refund to the customer through the payment gateway? This moves real money in live mode.">@csrf
<input type="hidden" name="expected_cents" value="{{ $payment->amount_cents-$payment->refunded_cents }}">
@if($payment->provider==='helcim')<label>Refund amount (CAD)<input type="number" class="form-control" name="amount" min="0.01" step="0.01" max="{{ ($payment->amount_cents-$payment->refunded_cents)/100 }}" value="{{ number_format(min($payment->amount_cents-$payment->refunded_cents,$order->balance_cents<0?abs($order->balance_cents):$payment->amount_cents-$payment->refunded_cents)/100,2,'.','') }}" required></label>@endif
<label><input type="checkbox" name="confirm" value="REFUND" required> Confirm refund (available CAD {{ number_format(($payment->amount_cents-$payment->refunded_cents)/100,2) }})</label>
<button class="btn btn-primary">Refund via gateway</button></form>
@endif
</div>
@endif
</div></div>
@endif
