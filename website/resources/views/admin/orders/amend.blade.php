@if(auth()->user()->hasAdminPermission('orders.manage') && !in_array($order->status,['cancelled','completed','delivered','out_for_delivery','awaiting_payment','payment_review']))
<div class="order-items-editor">
<p>Quantity changes adjust reserved stock. Existing line prices are kept unless you edit them. Paid amounts stay unchanged; any difference becomes an amount due or refund due.</p>
<form method="post" action="{{ route('admin.orders.amend',$order) }}" id="order-amend-form" data-matrix-delivery="{{ $order->delivery_service || \App\Models\GeneralSetting::findOrFail(1)->matrix_delivery_enabled?'1':'0' }}" data-delivery-options="{{ route('admin.orders.delivery-options') }}" data-tax-rate="{{ $order->tax_basis_points ?? \App\Models\GeneralSetting::findOrFail(1)->tax_basis_points }}" data-received="{{ $order->net_received_cents }}" data-default-delivery="{{ number_format((\App\Models\GeneralSetting::findOrFail(1)->delivery_cents ?? 0)/100,2,'.','') }}">@csrf<input type="hidden" name="revision" value="{{ $order->revision }}">
<div class="table-responsive"><table class="table order-edit-table"><colgroup><col style="width:34%"><col style="width:15%"><col style="width:19%"><col style="width:17%"><col style="width:15%"></colgroup><thead><tr><th>Product</th><th>Quantity</th><th>Unit price CAD</th><th>Line total CAD</th><th>Action</th></tr></thead><tbody>
@foreach($order->items as $item)<tr data-existing-item><td>{{ $item->name }} @if($item->warehouse_note)<small class="d-block text-danger">{{ $item->warehouse_note }}</small>@endif</td><td><input class="form-control" aria-label="Quantity for {{ $item->name }}" type="number" min="0" max="999" name="items[{{ $item->id }}][quantity]" value="{{ old('items.'.$item->id.'.quantity',$item->quantity) }}" required></td><td><input class="form-control" aria-label="Unit price for {{ $item->name }}" type="number" min="0" step=".01" name="items[{{ $item->id }}][unit_price]" value="{{ old('items.'.$item->id.'.unit_price',number_format($item->unit_cents/100,2,'.','')) }}" required></td><td><strong data-line-total>—</strong></td><td><button type="button" class="btn btn-outline-danger" data-remove-existing>Remove</button></td></tr>@endforeach
</tbody><tbody id="order-new-items"></tbody></table></div>
<button type="button" class="btn btn-outline-primary mb-3" data-add-order-item>Add Item</button>
<template id="order-add-template"><tr data-add-row><td>
<select data-field="product_id" aria-label="Product" hidden><option value="">Select a product</option>@foreach(\App\Models\Product::where('is_active',true)->orderBy('premium_marketing_name')->get(['id','premium_marketing_name','qr_code','product_code','total_selling_price_cad']) as $product)<option value="{{ $product->id }}" data-price="{{ $product->total_selling_price_cad }}" data-search="{{ $product->premium_marketing_name }} {{ $product->product_code }} {{ $product->qr_code }}">{{ $product->premium_marketing_name }}</option>@endforeach</select>
<button type="button" class="form-control order-product-select" data-product-toggle aria-haspopup="listbox" aria-expanded="false"><span>Select a product</span><span aria-hidden="true">⌄</span></button>
</td><td><input class="form-control" aria-label="Quantity for added item" data-field="quantity" type="number" min="1" max="999" value="1" required></td><td><input class="form-control" aria-label="Unit price for added item" data-field="unit_price" type="number" min="0" step=".01" required></td><td><strong data-line-total>—</strong></td><td><button type="button" class="btn btn-outline-danger" data-remove-order-item>Remove</button></td></tr></template>
<style>
.order-charge-fields label{display:block;width:100%}.order-charge-fields .form-control{width:100%}.order-charge-fields{align-items:start;margin-top:20px}.order-charge-fields small{font-size:12px}.order-live-totals{max-width:520px;margin-left:auto;padding:20px;border:1px solid #ddc391;border-radius:6px;background:#fff9ef}.order-live-totals p{display:flex;justify-content:space-between;gap:20px}.order-edit-table{min-width:750px;table-layout:fixed}.order-edit-table td{vertical-align:middle}.order-product-select{display:flex;align-items:center;justify-content:space-between;text-align:left;gap:8px}.order-product-select span:first-child{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.order-product-popup{position:fixed;z-index:9999;background:#fffaf2;border:1px solid #d5b580;border-radius:5px;padding:8px;box-shadow:0 6px 20px #0002}.order-product-options{max-height:230px;overflow:auto;margin-top:6px}.order-product-options button{display:block;width:100%;border:0;background:transparent;padding:10px;text-align:left;color:#382a1b}.order-product-options button:hover,.order-product-options button:focus{background:#f1e3cb}.order-product-options p{padding:10px;margin:0}
</style>
<details class="mt-3 mb-3"><summary>Customer &amp; Delivery / Pickup</summary><div class="row">
@foreach(['first_name'=>'First name','last_name'=>'Last name','email'=>'Email','phone'=>'Phone','address'=>'Address','city'=>'City','province'=>'Province','postal_code'=>'Postal code','country'=>'Country'] as $key=>$label)<div class="form-group col-md-4"><label>{{ $label }}<input class="form-control" name="{{ $key }}" value="{{ old($key,$order->$key) }}" @required($key==='first_name')></label></div>@endforeach
</div></details><div class="row order-charge-fields"><div class="form-group col-md-4"><label>Fulfillment<select class="form-control" name="fulfillment" data-order-fulfillment><option value="delivery" @selected(old('fulfillment',$order->fulfillment)==='delivery')>Delivery</option><option value="pickup" @selected(old('fulfillment',$order->fulfillment)==='pickup')>Pickup / Takeaway</option></select></label></div>
<div class="form-group col-md-4"><label>Delivery charge CAD<input class="form-control" type="number" min="0" step=".01" name="delivery" data-amend-delivery value="{{ old('delivery',number_format(($order->delivery_cents??0)/100,2,'.','')) }}" required></label><small class="d-block">Pickup is free. For postal-code delivery, choose a service to calculate the charge.</small></div>
@if($order->delivery_service || \App\Models\GeneralSetting::findOrFail(1)->matrix_delivery_enabled)
<div class="form-group col-md-12"><label>Delivery service<select class="form-control" name="delivery_service" id="amend-delivery-service"><option value="">Select a delivery service</option>@foreach(\Illuminate\Support\Facades\DB::table('delivery_services')->get() as $service)<option value="{{ $service->code }}" @selected(old('delivery_service',$order->delivery_service)===$service->code)>{{ $service->name }}</option>@endforeach</select></label><p id="amend-delivery-feedback" role="status">Saved delivery charge is retained until the service or destination changes.</p></div>
@endif
<input type="hidden" name="tax_mode" value="recalculate">
<div class="form-group col-md-4"><label>Tax ({{ number_format(($order->tax_basis_points ?? \App\Models\GeneralSetting::findOrFail(1)->tax_basis_points)/100,2) }}%) · CAD<input class="form-control" type="number" min="0" step=".01" name="tax" readonly value="{{ old('tax',number_format(($order->tax_cents??0)/100,2,'.','')) }}" required></label></div>
</div>
<div class="order-live-totals mb-3" aria-live="polite" aria-atomic="true">
<h3>Order total preview</h3>
<p>Product subtotal <strong data-total="subtotal">—</strong></p>
<p>Delivery charge <strong data-total="delivery">—</strong></p>
<p>Tax <strong data-total="tax">—</strong></p>
<p class="h4">Grand total <strong data-total="grand">—</strong></p>
<p>Received (after refunds) <strong data-total="received">—</strong></p>
<p><span data-balance-label>{{ $order->balance_cents < 0 ? 'Refund due' : 'Amount due' }}</span> <strong data-total="balance">—</strong></p>
<small data-preview-note>Preview only. Save Order Changes to apply.</small>
</div>
<label class="d-block">Reason for change<textarea class="form-control" name="reason" maxlength="1000" required>{{ old('reason') }}</textarea></label><p>Changing an order already in packing or ready for dispatch returns it to admin review. Send it back to warehouse after resolving the changes.</p><button class="btn btn-primary">Save Order Changes</button>
</form></div>
<script src="{{ asset('admin-assets/order-amend.js') }}?v={{ filemtime(public_path('admin-assets/order-amend.js')) }}"></script>
@if($order->delivery_service || \App\Models\GeneralSetting::findOrFail(1)->matrix_delivery_enabled)
<script src="{{ asset('admin-assets/order-delivery.js') }}?v={{ filemtime(public_path('admin-assets/order-delivery.js')) }}"></script>
@endif
@else
@include('partials.order-lines')
@endif
