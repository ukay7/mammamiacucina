@if($payment=$order->payment)
<div class="panel"><div class="panel-content">
<h2>Online payment Â· {{ ucfirst($payment->provider) }}</h2>
<p>{{ strtoupper($payment->mode) }} Â· {{ $order->payment_status_label }}<br>Transaction: {{ $payment->transaction_id ?: 'Awaiting confirmation' }}<br>Refunded: CAD {{ number_format($payment->refunded_cents/100,2) }}</p>
@if($payment->attention)<p class="alert alert-warning">{{ $payment->attention }}</p>@endif
@if($payment->provider==='helcim')<p>Helcim invoice: {{ $payment->provider_order_id ?: 'Awaiting checkout' }} Â· <a href="{{ route('admin.orders.print',$order) }}" target="_blank" rel="noopener">Print invoice / receipt</a></p>@endif
<p>Online payment status and totals are verified with the gateway. For a stuck payment, check your merchant dashboard or bank and record the decision below.</p>
@if(auth()->user()->hasAdminPermission('orders.manage'))
@if(in_array($order->status,['awaiting_payment','payment_review']) || ($payment->manual_resolution==='paid' && $order->status==='warehouse_pending'))
<form method="post" action="{{ route('admin.payments.resolve',$payment) }}" class="mb-3">
@csrf<input type="hidden" name="revision" value="{{ $order->revision }}">
<h3>Record manual payment decision</h3>
<p>Check the merchant dashboard or bank first. These actions only update our records; they do not charge or refund money.</p>
<label for="manual-payment-resolution">Decision</label><select id="manual-payment-resolution" class="form-control" name="resolution" required>
<option value="">Select a decision</option>
@if(in_array($order->status,['awaiting_payment','payment_review']))
@if(!$payment->refunded_cents)<option value="paid">Payment received — mark Paid and Send to Warehouse</option>@endif
@if(!$payment->paid_at && !$payment->refunded_cents)<option value="unpaid">No payment received — mark Unpaid and Cancel</option>@endif
@endif
@if(auth()->user()->hasAdminPermission('payments.refund'))<option value="refunded">Full refund already completed manually — mark Refunded and Cancel</option>@endif
</select>
<label for="manual-payment-reason" class="mt-2">Merchant / bank reference and note</label><textarea id="manual-payment-reason" class="form-control" name="reason" required maxlength="1000"></textarea>
<label class="mt-2"><input type="checkbox" name="confirmed" value="1" required> I checked the bank / merchant records. For a refund, I have already returned the full payment externally.</label>
<button type="submit" class="btn btn-primary">Save payment decision</button>
</form>
@endif
@if($payment->manual_resolution)<p>Payment is managed manually. Any refund must be completed externally and recorded here before warehouse processing starts.</p>@else
<div class="d-flex flex-wrap" style="gap:12px">
<form method="post" action="{{ route('admin.payments.reconcile',$payment) }}">@csrf<button class="btn btn-outline-primary">Reconcile payment</button></form>
@if(!$payment->paid_at && !$payment->released_at)<form method="post" action="{{ route('admin.payments.cancel',$payment) }}">@csrf<button class="btn btn-outline-primary">Cancel unpaid checkout</button></form>@endif
@if($payment->paid_at && $payment->refunded_cents < $payment->amount_cents && auth()->user()->hasAdminPermission('payments.refund'))
<form method="post" action="{{ route('admin.payments.refund',$payment) }}" data-confirm="Send this refund to the customer through the payment gateway? This moves real money in live mode.">@csrf
<input type="hidden" name="expected_cents" value="{{ $payment->amount_cents-$payment->refunded_cents }}">
@if($payment->provider==='helcim')<label>Refund amount (CAD)<input type="number" data-number-display="decimal" class="form-control" name="amount" min="0.01" step="0.01" max="{{ ($payment->amount_cents-$payment->refunded_cents)/100 }}" value="{{ number_format(min($payment->amount_cents-$payment->refunded_cents,$order->balance_cents<0?abs($order->balance_cents):$payment->amount_cents-$payment->refunded_cents)/100,2,'.','') }}" required></label>@endif
<label><input type="checkbox" name="confirm" value="REFUND" required> Confirm refund (available CAD {{ number_format(($payment->amount_cents-$payment->refunded_cents)/100,2) }})</label>
<button class="btn btn-primary">Refund via gateway</button></form>
@endif
</div>
@endif
@endif
</div></div>
@endif
