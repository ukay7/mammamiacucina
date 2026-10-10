<p>Pay securely by card through Helcim. Your card details are entered in Helcim’s payment window.</p>
@if($payment->mode==='sandbox')<p class="mmc-eyebrow">TEST ACCOUNT · Use Helcim test cards only</p>@endif
<p id="card-payment-message" role="status" aria-live="polite">If you have already paid, check payment status. Keep this order number for reference.</p>
@if($payment->cancel_requested_at)
<p>Cancellation requested. We are checking for any payment before releasing your reservation. This can take until {{ $payment->expires_at->copy()->addMinutes(10)->format('H:i T') }}.</p>
@elseif(!$payment->paid_at && !$payment->expires_at->isPast())
@if($payment->checkout_token)
<div id="helcim-checkout" data-checkout-token="{{ $payment->checkout_token }}" data-confirm-url="{{ route('payment.card-confirm',$payment->reference) }}" data-csrf="{{ csrf_token() }}">
<button class="mmc-button" id="helcim-pay-button" type="button">Pay CAD {{ number_format($payment->amount_cents/100,2) }} by card</button>
</div>
<script src="https://secure.helcim.app/helcim-pay/services/start.js" defer></script>
<script src="{{ asset('assets/js/helcim-checkout.js') }}?v={{ filemtime(public_path('assets/js/helcim-checkout.js')) }}" defer></script>
@else
<form method="post" action="{{ route('payment.start',$payment->reference) }}">@csrf<button class="mmc-button" type="submit">Prepare secure card payment</button></form>
@endif
@elseif(!$payment->paid_at)
<p>The card payment window has expired. Check payment status to confirm whether payment was received before placing another order.</p>
@endif
<form method="post" action="{{ route('payment.check',$payment->reference) }}" style="margin-top:16px">@csrf<button class="mmc-button" type="submit">Check payment status</button></form>
@if(!$payment->paid_at && !$payment->cancel_requested_at)
<form method="post" action="{{ route('payment.cancel',$payment->reference) }}" style="margin-top:16px">@csrf<button class="mmc-text-link" style="background:none;border:0;padding:0;color:inherit;text-decoration:underline;cursor:pointer" type="submit">Cancel unpaid order</button></form>
@endif
