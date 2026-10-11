<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#34271d}h1{color:#8b641f;font-size:24px}h2{font-size:15px}table{width:100%;border-collapse:collapse;margin:18px 0}th{background:#39251c;color:white;text-align:left}td,th{padding:8px;border-bottom:1px solid #e7dccb}thead{display:table-header-group}tr{page-break-inside:avoid}.money{text-align:right}.muted{color:#756858}.box{padding:12px;background:#f7f1e7}footer{margin-top:24px;font-size:10px}
</style></head><body>
<h1>Mamma Mia Cucina</h1><h2>Order summary / payment record — {{ $values['order_number'] }}</h2>
<p>{{ $values['environment'] }}</p><p class="muted">Snapshot generated {{ $date }} ({{ config('app.timezone') }})</p>
<p><strong>{{ $values['name'] }}</strong><br>{{ $values['fulfillment'] }}</p>
<div class="box">Order: {{ $values['status'] }}<br>Payment: {{ $values['payment_status'] }} · {{ $values['payment_method'] }}</div>
<table><thead><tr><th>Code / Product</th><th>Quantity</th><th class="money">Unit price</th><th class="money">Total</th></tr></thead><tbody>
@foreach($items as $item)<tr><td>{{ $item['code'] }}<br>{{ $item['name'] }}</td><td>{{ $item['quantity'] }}</td><td class="money">{{ $item['unit'] }}</td><td class="money">{{ $item['total'] }}</td></tr>@endforeach
</tbody></table><table><tr><td>Subtotal</td><td class="money">{{ $subtotal }}</td></tr><tr><td>Delivery</td><td class="money">{{ $delivery }}</td></tr><tr><td>Tax</td><td class="money">{{ $tax }}</td></tr><tr><td><strong>Order total</strong></td><td class="money"><strong>{{ $values['total'] }}</strong></td></tr><tr><td>Net received</td><td class="money">{{ $values['received'] }}</td></tr></table>
<p><strong>{{ $values['balance'] }}</strong></p><p>{{ $values['instructions'] }}</p>
<footer>This document confirms the recorded order details. An unpaid or pending order is not proof of payment. Keep this document for your records.</footer>
</body></html>
