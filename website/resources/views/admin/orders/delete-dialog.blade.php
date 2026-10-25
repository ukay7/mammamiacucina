<link rel="stylesheet" href="{{ asset('admin-assets/order-deletion.css') }}?v={{ filemtime(public_path('admin-assets/order-deletion.css')) }}">
<dialog id="order-delete-dialog" aria-labelledby="order-delete-title" aria-describedby="order-delete-description" data-failed-order="{{ $errors->any() ? old('_delete_order_id','') : '' }}">
    <header class="order-delete-header">
        <div><small>SUPER ADMIN ONLY</small><h2 id="order-delete-title">Delete order <span data-delete-order-number></span></h2></div>
        <button class="btn btn-outline-secondary" type="button" data-delete-close aria-label="Close delete order dialog">&times;</button>
    </header>
    <p id="order-delete-description">This order will be removed from normal screens, reports and balances. Its invoices, payments and history remain view-only in Deleted Orders. There is no Restore action. Deleting does not issue a refund.</p>
    <p class="order-delete-hint">Any pending gateway payment or refund must be resolved before deletion.</p>
    <div class="alert alert-danger" role="alert" data-delete-errors hidden>
        @if(old('_delete_order_id') && $errors->any())
        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif
    </div>
    <form method="post" data-order-delete-form>
        @csrf @method('DELETE')
        <input type="hidden" name="revision">
        <input type="hidden" name="_delete_order_id">
        <label class="order-delete-reason" for="order-delete-reason">Reason for deletion <span class="text-danger">(required)</span></label>
        <textarea id="order-delete-reason" class="form-control" name="reason" maxlength="2000" rows="3" required>{{ old('_delete_order_id') ? old('reason') : '' }}</textarea>
        <fieldset class="order-delete-stock">
            <legend>Return this order’s deducted items to inventory?</legend>
            <label><input type="radio" name="restock_inventory" value="1" required @checked(old('_delete_order_id') && old('restock_inventory')==='1')> Yes, return deducted quantities to stock</label>
            <label><input type="radio" name="restock_inventory" value="0" required @checked(old('_delete_order_id') && old('restock_inventory')==='0')> No, keep inventory unchanged</label>
            <small>Only quantities still deducted by this order can be returned. Previously returned stock will not be added again.</small>
        </fieldset>
        <label class="order-delete-confirm"><input type="checkbox" name="confirm" value="1" required> <span>I confirm deletion of <strong data-delete-order-number></strong> and understand its retained records will be view-only.</span></label>
        <footer class="order-delete-actions">
            <button class="btn btn-outline-secondary" type="button" data-delete-close>Go back</button>
            <button class="btn btn-danger" type="submit"><i class="fas fa-trash-alt" aria-hidden="true"></i> Delete order</button>
        </footer>
    </form>
</dialog>
<script defer src="{{ asset('admin-assets/order-deletion.js') }}?v={{ filemtime(public_path('admin-assets/order-deletion.js')) }}"></script>
