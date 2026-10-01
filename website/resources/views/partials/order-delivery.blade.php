<section class="order-delivery-detail" style="margin:18px 0;padding:16px;border:1px solid #e5d8bf;border-radius:6px;break-inside:avoid">
<h3 style="font-family:Georgia,serif;font-size:18px;color:#65451e;margin:0 0 10px">{{ $order->fulfillment==='pickup'?'Pick up':'Delivery service & route' }}</h3>
@if($order->fulfillment==='pickup')
<p>Pick up</p>
@elseif($order->delivery_service)
<p><strong>{{ $order->delivery_service_name ?: ucwords(str_replace('_',' ',$order->delivery_service)) }}</strong></p>
<p><strong>From:</strong> {{ $order->delivery_from_postal }} (Zone {{ $order->delivery_from_zone }})<br><strong>To:</strong> {{ $order->delivery_to_postal ?: $order->postal_code }} (Zone {{ $order->delivery_to_zone }})</p>
<p><strong>Delivery charge:</strong> CAD {{ number_format(($order->delivery_rate_cents ?? $order->delivery_cents)/100,2) }}<br><small>Base rate for the selected service and postal-code route, saved with this order.</small></p>
@else
<p>Delivery charge: {{ $order->delivery_cents===null?'To be confirmed':'CAD '.number_format($order->delivery_cents/100,2) }}<br><small>No courier service or origin was recorded for this order.</small></p>
@endif
</section>
