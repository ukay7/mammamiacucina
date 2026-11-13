@if(auth()->user()->isSuper() && $order->customer_id && !$order->trashed() && in_array($order->status,['delivered','completed']) && $order->currency==='CAD')
<a class="btn btn-outline-primary btn-sm" href="{{ route('admin.customers.refunds.create',[$order->customer_id,'order_id'=>$order->id]) }}">Refund</a>
@endif
