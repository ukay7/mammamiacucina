@if(auth()->user()->isSuper() && $order->customer_id)
<section class="panel panel-content mt-3"><div class="d-flex justify-content-between align-items-center mb-3"><h2>Customer refunds &amp; credits</h2>@include('admin.orders.refund-button')</div>
<p>Original order: CAD {{ number_format(($order->final_total_cents??0)/100,2) }} · Confirmed credits: CAD {{ number_format($order->refund_credit_cents/100,2) }} · Adjusted order amount: CAD {{ number_format(($order->adjusted_total_cents??0)/100,2) }} · Pending refund cheques: CAD {{ number_format($order->pending_refund_cents/100,2) }}.</p>
@include('admin.customers.refund-table',['accountRefunds'=>$order->refunds()->summary()->with('lines','order:id,number','author')->latest('id')->paginate(10,['*'],'refunds_page')])
</section>
@endif
