@extends('admin.layout')
@section('title','Supplier Order '.$order->number)
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/supplier-orders.css') }}?v={{ filemtime(public_path('admin-assets/supplier-orders.css')) }}">
<div class="purchase-filter-actions mb-3"><a class="btn btn-outline-primary" href="{{ route('admin.supplier-orders.index',['status'=>$order->status]) }}">Back to Supplier Orders</a><button type="button" class="btn btn-outline-primary" data-supplier-pdf-open data-preview-url="{{ route('admin.supplier-orders.pdf-preview',$order) }}" data-pdf-url="{{ route('admin.supplier-orders.pdf',$order) }}" aria-label="PDF for {{ $order->number }}">PDF</button></div>
<section class="panel panel-content">
    <h2>{{ $order->number }}</h2><p><strong>{{ \App\Models\SupplierOrder::STATUS_LABELS[$order->status] }}</strong> · {{ $order->created_at->format('l, d M Y H:i') }} · {{ $order->author?->name }}</p>
    <h3>{{ $order->supplier_snapshot['name'] }}</h3><p>Supplier #{{ $order->supplier_id }} · Stock view when ordered: {{ $order->warehouse_name }}</p>
    <dl class="row">@foreach(['contact_name'=>'Contact','email'=>'Email','phone'=>'Phone','address'=>'Address'] as $key=>$label)<dt class="col-md-2">{{ $label }}</dt><dd class="col-md-10" style="white-space:pre-line;overflow-wrap:anywhere">{{ $order->supplier_snapshot[$key] ?: 'Not provided' }}</dd>@endforeach</dl>
    <p class="text-muted">Supplier details and prices are preserved from placement. The stock view above is not a receiving destination. Choose the destination when marking the order Received.</p>
    @if($order->status==='completed')<div class="alert alert-warning">This order was completed under the earlier manual-stock workflow. No stock has been added automatically. If you already entered it manually, choose No when marking it Received.</div>@endif
</section>
@if($order->status==='deleted')<div class="alert alert-warning">Deleted supplier order — view only. {{ $order->deletion_reason }}</div>@endif
<section class="panel panel-content"><h3>Ordered products</h3>
    <div class="table-responsive"><table class="table"><thead><tr><th>Code</th><th>Product</th><th>Quantity (cartons)</th><th>Currency</th><th>Quoted price</th><th>Basis</th><th>Pieces / carton</th><th>Discount</th><th>Discounted price</th><th>Cost / carton</th><th>Total</th></tr></thead><tbody>
    @foreach($order->items as $item)@php($value=$item->pricing_snapshot['valuation'])<tr><td>{{ $item->product_code ?: '—' }}<br><small>{{ $item->barcode }}</small></td><td>{{ $item->name }}</td><td>{{ \App\Support\NumberDisplay::quantity($item->quantity) }}</td><td>{{ $item->currency }}</td><td>{{ $value['purchase_price'] }}</td><td>{{ ucfirst($value['purchase_basis']) }}</td><td>{{ $value['pieces_per_carton']??'—' }}</td><td>{{ $value['discount'] }}%</td><td>{{ $value['discounted_price'] }}</td><td>{{ \App\Support\NumberDisplay::decimal($value['carton_cost'],8,true) }}</td><td>{{ $item->line_total }}</td></tr>@endforeach
    </tbody></table></div><div class="text-right">@foreach($order->totals as $currency=>$amount)<p class="h4">Total {{ $currency }} {{ $amount }}</p>@endforeach</div><h4>Order notes</h4><p style="white-space:pre-line;overflow-wrap:anywhere">{{ $order->notes ?: 'No notes' }}</p>
</section>
@if($order->receipt)
@php($receipt=$order->receipt)
<section class="panel panel-content" id="receiving-history"><h3>Receiving record #{{ $receipt->id }}</h3>
    <p><strong>{{ $receipt->add_to_inventory?'Inventory added to '.$receipt->warehouse_name:'Inventory not added — quantities unchanged' }}</strong></p>
    <p>{{ $receipt->received_at->format('l, d M Y H:i') }} · {{ $receipt->received_by_name }}</p><p class="purchase-receiving-note">{{ $receipt->reason }}</p>
    <p class="text-muted">Received quantities and stock levels below are historical snapshots. Later stock movements do not change this record. Received orders are view only; make later stock corrections through Inventory with a reason.</p>
    <div class="table-responsive"><table class="table"><thead><tr><th>Product / code</th><th>Received quantity</th><th>Warehouse before</th><th>Warehouse after</th><th>Total before</th><th>Total after</th><th>Stock history entry</th></tr></thead><tbody>
    @foreach($receipt->items as $entry)<tr><td>{{ $entry->name }}<br><small>{{ $entry->product_code ?: $entry->barcode }}</small></td><td>{{ \App\Support\NumberDisplay::quantity($entry->quantity) }}</td><td>{{ $receipt->add_to_inventory?\App\Support\NumberDisplay::quantity($entry->quantity_before,'Not set'):'Not changed' }}</td><td>{{ $receipt->add_to_inventory?\App\Support\NumberDisplay::quantity($entry->quantity_after):'Not changed' }}</td><td>{{ \App\Support\NumberDisplay::quantity($entry->total_before,'Not set') }}</td><td>{{ \App\Support\NumberDisplay::quantity($entry->total_after,'Not set') }}</td><td>{{ $entry->inventory_movement_id?'#'.$entry->inventory_movement_id:'No stock movement' }}</td></tr>@endforeach
    </tbody></table></div>
</section>
@endif
@if(!in_array($order->status,['deleted','received']) && !$order->receipt && auth()->user()->hasAdminPermission('purchases.manage'))
<section class="panel panel-content"><h3>Update supplier order</h3><p>In progress → Packing → Ready → Received. Stock changes only when you confirm receipt and choose Yes.</p>
    <form method="post" action="{{ route('admin.supplier-orders.status',$order) }}" data-supplier-status>@csrf<input type="hidden" name="revision" value="{{ $order->revision }}">
        <div class="form-group"><label for="supplier-order-status">Status</label><select class="form-control" id="supplier-order-status" name="status">@if($order->status==='completed')<option value="completed" selected>Completed (legacy manual stock)</option>@endif @foreach(['placed','packing','ready','deleted'] as $key)<option value="{{ $key }}" @selected($order->status===$key)>{{ \App\Models\SupplierOrder::STATUS_LABELS[$key] }}</option>@endforeach</select></div>
        <div class="form-group"><label for="supplier-order-reason">Reason / note (required for deletion)</label><textarea class="form-control" id="supplier-order-reason" name="reason" rows="2" maxlength="2000"></textarea></div>
        <label class="d-block"><input type="checkbox" name="confirm" value="1" required> Confirm this status change. This form does not add inventory.</label>
        <div class="purchase-filter-actions mt-3"><button class="btn btn-primary">Save Status</button><button class="btn btn-outline-primary" type="button" data-receive-open>Mark Received</button></div>
    </form>
</section>
<div class="purchase-modal" data-receive-modal data-open-on-error="{{ $errors->any() && old('receipt_form')?'1':'0' }}" hidden>
    <section class="purchase-dialog" role="dialog" aria-modal="true" aria-labelledby="supplier-receiving-title">
        <header class="d-flex justify-content-between align-items-center"><h2 id="supplier-receiving-title" tabindex="-1">Receive {{ $order->number }}</h2><button class="btn btn-outline-primary" type="button" data-receive-close aria-label="Close receiving">×</button></header>
        <p>Confirm that all ordered goods have arrived. Each product's ordered quantity will be received once, without multiplying it by pieces per carton.</p>
        @if($order->status==='completed')<p class="alert alert-warning">Already entered this delivery manually? Choose No below to prevent adding it twice.</p>@endif
        <form method="post" action="{{ route('admin.supplier-orders.receive',$order) }}" data-supplier-receiving>
            @csrf<input type="hidden" name="revision" value="{{ $order->revision }}"><input type="hidden" name="request_key" value="{{ old('request_key',(string)\Illuminate\Support\Str::uuid()) }}"><input type="hidden" name="receipt_form" value="1">
            @if($errors->any() && old('receipt_form'))<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <div class="form-group"><label for="receive-stock-choice">Add these quantities to inventory?</label><select class="form-control" id="receive-stock-choice" name="add_to_inventory" required><option value="">Choose Yes or No</option><option value="1" @selected(old('add_to_inventory')==='1') @disabled(!auth()->user()->hasAdminPermission('inventory.manage'))>Yes — add to the receiving warehouse</option><option value="0" @selected(old('add_to_inventory')==='0')>No — mark received without changing stock</option></select>@if(!auth()->user()->hasAdminPermission('inventory.manage'))<small>Adding stock requires the Adjust stock permission as well as Manage supplier orders.</small>@endif</div>
            <fieldset data-receive-stock hidden disabled>
                <div class="form-group"><label for="receive-warehouse">Receiving warehouse</label><select class="form-control" id="receive-warehouse" name="warehouse_id"><option value="">Select receiving warehouse</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string)old('warehouse_id')===(string)$warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select><small>Stock view when ordered: {{ $order->warehouse_name }}. Confirm the actual delivery destination here.</small></div>
                <label class="d-block mb-3"><input type="checkbox" name="initialize_unset" value="1" @checked(old('initialize_unset'))> If stock is not set at this warehouse, initialize it using this delivery quantity. Any older, uncounted stock must be entered separately.</label>
            </fieldset>
            <div class="form-group"><label for="receive-note">Required receiving note / reason</label><textarea class="form-control" id="receive-note" name="reason" rows="2" maxlength="2000" required>{{ old('receipt_form')?old('reason'):'' }}</textarea></div>
            <div class="table-responsive"><table class="table"><thead><tr><th>Product / code</th><th>Quantity to receive</th></tr></thead><tbody>@foreach($order->items as $item)<tr><td>{{ $item->name }}<br><small>{{ $item->product_code ?: $item->barcode }}</small></td><td>{{ \App\Support\NumberDisplay::quantity($item->quantity) }}</td></tr>@endforeach</tbody></table></div>
            <p class="purchase-receive-effect" data-receive-effect>Choose whether inventory should be updated.</p>
            <label class="d-block my-3"><input type="checkbox" name="confirm" value="1" required> I confirm all goods have arrived and the inventory choice and destination are correct. This receiving action is final; later corrections require an inventory adjustment.</label>
            <footer class="purchase-filter-actions"><button class="btn btn-outline-primary" type="button" data-receive-close>Cancel</button><button class="btn btn-primary" type="submit" data-receive-submit>Confirm Received</button></footer>
        </form>
    </section>
</div>
<script defer src="{{ asset('admin-assets/supplier-receiving.js') }}?v={{ filemtime(public_path('admin-assets/supplier-receiving.js')) }}"></script>
@endif
<section class="panel panel-content"><h3>Status history</h3><table class="table"><thead><tr><th>When</th><th>Who</th><th>Change</th><th>Reason</th></tr></thead><tbody>@foreach($order->events as $event)<tr><td>{{ $event->created_at->format('d M Y H:i') }}</td><td>{{ $event->actor_name ?: ($event->author?->name??'Former staff member') }}</td><td>{{ $event->from_status?\App\Models\SupplierOrder::STATUS_LABELS[$event->from_status].' → ':'' }}{{ \App\Models\SupplierOrder::STATUS_LABELS[$event->to_status] }}</td><td class="purchase-receiving-note">{{ $event->reason }}</td></tr>@endforeach</tbody></table></section>
@include('admin.supplier-orders.pdf-modal')
@endsection
