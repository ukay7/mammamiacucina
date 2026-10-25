@if(auth()->user()->isSuper() && !$order->trashed())
<section id="delete-order" class="panel panel-content">
<h2>Delete order</h2>
@if($reason=$order->deletionBlockReason())
<p>{{ $reason }}</p>
@else
<p>This order will be removed from normal screens, reports and balances. Its invoices, payments and history remain view-only in Deleted Orders. There is no Restore action. Deleting does not issue a refund.</p>
<form method="post" action="{{ route('admin.orders.destroy',$order) }}">
@csrf @method('DELETE')
<input type="hidden" name="revision" value="{{ $order->revision }}">
<label class="d-block">Reason for deletion<textarea class="form-control" name="reason" maxlength="2000" rows="3" required>{{ old('reason') }}</textarea></label>
@if($order->items->contains(fn($item)=>$item->stock_deducted && $item->product_id))
<fieldset class="my-3"><legend class="h6">Return this order’s deducted items to inventory?</legend>
<label class="d-block"><input type="radio" name="restock_inventory" value="1" required> Yes, return deducted quantities to stock</label>
<label class="d-block"><input type="radio" name="restock_inventory" value="0" required> No, keep inventory unchanged</label>
</fieldset>
@else
<input type="hidden" name="restock_inventory" value="0"><p>No deducted stock remains to return.</p>
@endif
<label class="d-block my-3"><input type="checkbox" name="confirm" value="1" required> I confirm deletion of {{ $order->number }} and understand its retained records will be view-only.</label>
<button class="btn btn-danger" type="submit"><i class="fas fa-trash-alt" aria-hidden="true"></i> Delete order</button>
</form>
@endif
</section>
@endif
