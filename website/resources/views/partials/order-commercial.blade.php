@if($order->credit_warning)<div class="alert alert-warning" role="alert">{{ $order->credit_warning }}</div>@endif
@if($order->cheque_due_date)<p>Cheque due: {{ $order->cheque_due_date }} · {{ $order->cheque_days }} days from order date · {{ $order->payment_status_label }}</p>@endif
@if($order->order_discount_cents)<p>Admin special order discount ({{ $order->order_discount_bps/100 }}%): −&#36;{{ number_format($order->order_discount_cents/100,2) }}</p>@endif
