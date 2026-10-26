@if(auth()->user()->isSuper() && !$order->trashed())
<link rel="stylesheet" href="{{ asset('admin-assets/payment-controls.css') }}?v={{ filemtime(public_path('admin-assets/payment-controls.css')) }}">
<section class="panel panel-content payment-controls" aria-labelledby="payment-controls-title">
    <h2 id="payment-controls-title">Super Admin payment controls</h2>
    <p>Selected payment method: <strong>{{ $order->payment_label }}</strong>. Changing a method does not record money received, change stock or move the order through fulfillment.</p>
    @if($order->payment_method==='credit' && $order->credit_warning)<div class="alert alert-warning">{{ $order->credit_warning }}</div>@endif
    @if($order->payment?->paid_at)
    <div class="alert alert-info">Confirmed online payment is locked. Use gateway reconciliation or refund controls; its transaction cannot be relabelled or manually reversed.</div>
    @else
    @if(($order->final_total_cents===null || $order->balance_cents>0) && in_array($order->payment_status,['unpaid','pending']) && $order->status!=='cancelled')
    <details @if($errors->any() && old('_payment_control')==='method') open @endif>
        <summary>Change unpaid payment method</summary>
        <form method="post" action="{{ route('admin.orders.payment-method',$order) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="revision" value="{{ $order->revision }}"><input type="hidden" name="_payment_control" value="method">
            <p>The outstanding balance stays unpaid until collection is recorded. Any online checkout must first be confirmed closed with no payment received. Cancelled orders cannot be reopened here.</p>
            <label>New method<select class="form-control" name="method" required><option value="">Select a method</option>@foreach(\App\Services\ManualPaymentControls::METHODS as $key=>$label)@if($key!==$order->payment_method && ($key!=='credit'||$order->customer_id))<option value="{{ $key }}">{{ $label }}</option>@endif @endforeach</select></label>
            <label>Reason for method change<textarea class="form-control" name="reason" rows="2" maxlength="2000" required>{{ old('_payment_control')==='method'?old('reason'):'' }}</textarea></label>
            <label class="payment-control-confirm"><input type="checkbox" name="confirmed" value="1" required> I checked existing payments and confirm this change. It does not mark the order Paid.</label>
            <button class="btn btn-primary" type="submit">Change payment method</button>
        </form>
    </details>
    @endif
    @if(count($paymentSources))
    <details @if($errors->any() && old('_payment_control')==='adjust') open @endif>
        <summary>Correct / reverse a manual payment</summary>
        <form method="post" action="{{ route('admin.orders.payment-adjustment',$order) }}" data-payment-adjustment>
            @csrf<input type="hidden" name="revision" value="{{ $order->revision }}"><input type="hidden" name="_payment_control" value="adjust">
            <p>Original entries and receipts are preserved. A correction reassigns a recorded amount to the actual method. A reversal or bounced cheque reopens the balance; it is not a customer refund.</p>
            <label>Action<select class="form-control" name="action" required><option value="">Select an action</option><option value="correct_method">Correct recorded payment method</option><option value="reverse">Reverse a mistaken payment entry</option><option value="bounced_cheque" @disabled(!collect($paymentSources)->contains('method','cheque'))>Cheque bounced — reverse collection</option></select></label>
            <label>Payment to correct / reverse<select class="form-control" name="source" required><option value="">Select a payment</option>@foreach($paymentSources as $source)<option value="{{ $source['id'] }}" data-available="{{ number_format($source['available']/100,2,'.','') }}">{{ $source['label'] }}</option>@endforeach</select></label>
            <label>Amount to correct / reverse (CAD)<input class="form-control" type="number" data-number-display="decimal" name="amount" min="0.01" step="0.01" required></label>
            <div data-payment-correct-fields>
                <label>Actual payment method (for method corrections)<select class="form-control" name="method"><option value="">Select the actual method</option>@foreach(\App\Services\ManualPaymentControls::METHODS as $key=>$label)@if($key!=='credit'||$order->customer_id)<option value="{{ $key }}">{{ $label }}</option>@endif @endforeach</select></label>
                <p class="alert alert-info" data-payment-credit-note hidden>Customer Credit means payment is still owed. The selected amount will be removed from recorded collections, and the outstanding balance will be placed on the customer's credit account, subject to their credit settings. No payment or refund is made.</p>
                <label>Verified transaction reference (required except Cash / Customer Credit)<input class="form-control" name="reference" maxlength="255"></label>
            </div>
            <label>Required reason / bank reference<textarea class="form-control" name="reason" rows="3" maxlength="2000" required>{{ old('_payment_control')==='adjust'?old('reason'):'' }}</textarea></label>
            <label class="payment-control-confirm"><input type="checkbox" name="confirmed" value="1" required> I verified the actual payment records and confirm this correction / reversal. This action does not send a refund or collect money.</label>
            <button class="btn btn-primary" type="submit">Save payment correction / reversal</button>
        </form>
    </details>
    @endif
    @endif
    @if($order->transfer_receipt_path && !in_array($order->payment_method,['etransfer','cheque']))<p class="mt-3"><a href="{{ route('orders.transfer-receipt',$order) }}" target="_blank" rel="noopener">View previously uploaded payment proof</a> · retained for audit; this does not confirm receipt of funds.</p>@endif
    @foreach($order->paymentAttempts->whereNotNull('superseded_at') as $attempt)<p class="mt-3">Closed online checkout retained: {{ ucfirst($attempt->provider) }} · {{ $attempt->status }} · {{ $attempt->reference }} · Replaced {{ $attempt->superseded_at->format('d M Y H:i') }}.</p>@endforeach
</section>
<script defer src="{{ asset('admin-assets/payment-controls.js') }}?v={{ filemtime(public_path('admin-assets/payment-controls.js')) }}"></script>
@endif
