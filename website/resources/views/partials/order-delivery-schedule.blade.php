@if($order->fulfillment==='delivery' && $order->delivery_scheduled_at)
<p class="order-delivery-schedule"><strong>Delivery date &amp; time:</strong> {{ \App\Support\BusinessTime::format($order->delivery_scheduled_at,'D, d M Y, g:i A T') }} (Toronto)</p>
@endif
