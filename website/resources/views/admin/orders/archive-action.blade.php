@if(auth()->user()->hasAdminPermission('orders.manage') && !$order->archived_at)
<section class="panel panel-content mt-3">
<h2>Archive order</h2>
@if($reason=$order->archiveBlockReason())
<p>{{ $reason }}</p>
@else
<p>Move this order to Archived Orders. It will become read-only. Products, stock, payments and financial history will be preserved.</p>
<form method="post" action="{{ route('admin.orders.archive',$order) }}">@csrf
<input type="hidden" name="revision" value="{{ $order->revision }}">
<label class="d-block"><input type="checkbox" name="confirm" value="1" required> I confirm this order needs no further work.</label>
<button class="btn btn-outline-primary" type="submit">Archive order</button>
</form>
@endif
</section>
@endif
