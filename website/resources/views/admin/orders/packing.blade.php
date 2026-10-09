@if(in_array($order->source,['website','pos']))
<link rel="stylesheet" href="{{ asset('admin-assets/warehouse.css') }}?v={{ filemtime(public_path('admin-assets/warehouse.css')) }}">
@php
$canPack=auth()->user()->hasAdminPermission('warehouse.pack') && in_array($order->status,['warehouse_pending','packing']);
@endphp
<div class="panel"><div class="panel-content warehouse-packing">
<h2>Warehouse packing</h2><p>{{ $order->status_label }} · Round {{ $order->warehouse_round }} · {{ $order->fulfillment==='pickup'?'Customer pick up':'Delivery' }}</p>
@if($order->warehouse_note)<div class="warehouse-notice">{{ $order->warehouse_note }}</div>@endif
@if($canPack)
<div class="warehouse-scanner"><h3>Scan to pack</h3><p>Scan the product barcode or QR code to tick its row. One scan marks the <strong>entire ordered quantity</strong> packed—check the quantity first. Save progress to keep your changes.</p><div class="warehouse-actions"><input class="form-control" id="warehouse-code" aria-label="Scan or enter product barcode" placeholder="Scan or enter barcode" autocomplete="off"><button type="button" class="btn btn-outline-primary" id="warehouse-match">Match barcode</button><button type="button" class="btn btn-primary" id="warehouse-camera-start">Scan with camera</button></div><div id="warehouse-camera" hidden><video id="warehouse-video" autoplay muted playsinline></video><button type="button" class="btn btn-outline-primary" id="warehouse-camera-stop">Stop camera</button></div><p id="warehouse-scan-result" role="status" aria-live="polite"></p></div>
<form data-warehouse-form method="post" action="{{ route('admin.orders.packing',$order) }}">@csrf<input type="hidden" name="revision" value="{{ $order->revision }}">
@endif
<div class="table-responsive"><table class="table warehouse-table"><thead><tr><th scope="col">Packed</th><th scope="col">Product / Barcode</th><th scope="col">Qty</th><th scope="col">Item notes / shortage</th><th scope="col">Status</th></tr></thead><tbody>
@foreach($order->items as $item)
@php
$note=$canPack?old('items.'.$item->id.'.note',$item->warehouse_note):$item->warehouse_note;
$issue=trim($note??'')!=='';
$packed=($canPack?old('items.'.$item->id.'.packed',$item->packed):$item->packed) && !$issue;
$barcode=$item->qr_code?:$item->product?->qr_code;
@endphp
<tr data-packing-row data-barcode="{{ $barcode }}" data-name="{{ $item->name }}" class="{{ $issue?'has-issue':($packed?'is-packed':'') }}">
<td>@if($canPack)<input type="hidden" name="items[{{ $item->id }}][packed]" value="0"><input aria-label="Packed {{ $item->name }}" type="checkbox" data-packed-check name="items[{{ $item->id }}][packed]" value="1" @checked($packed)>@else{{ $packed?'✓':'—' }}@endif</td>
<td><strong>{{ $item->name }}</strong><small class="d-block">Barcode: {{ $barcode?:'Not set' }}</small></td><td><strong>{{ $item->quantity }}</strong></td>
<td>@if($canPack)<textarea class="form-control" data-item-note aria-label="Item note for {{ $item->name }}" name="items[{{ $item->id }}][note]" maxlength="1000" rows="2" placeholder="Describe a shortage or problem">{{ $note }}</textarea><button type="button" class="warehouse-shortage" data-shortage>~ Mark shortage</button>@else<span class="warehouse-note">{{ $note?:'—' }}</span>@endif</td>
<td><span data-item-state class="warehouse-state">{{ $issue?'~ Issue / shortage':($packed?'✓ Packed':'Awaiting packing') }}</span></td></tr>
@endforeach
</tbody></table></div>
@if($canPack)
<p class="text-muted">Yellow rows need attention. Clear the resolved note before ticking the item as packed.</p><label for="warehouse-note">Order note for admin</label><textarea class="form-control" id="warehouse-note" name="note" rows="2" maxlength="2000">{{ old('note',$order->warehouse_note) }}</textarea>
<div class="warehouse-actions"><button class="btn btn-outline-primary" name="action" value="save">Save Packing Progress</button><button class="btn btn-outline-danger" name="action" value="return">Return to Admin — Issue</button><button class="btn btn-primary" name="action" value="ready">All Packed — Ready for Dispatch</button></div></form>
<script src="{{ asset('admin-assets/scanner-libs/zxing-browser-0.1.5.min.js') }}"></script><script src="{{ asset('admin-assets/warehouse.js') }}?v={{ filemtime(public_path('admin-assets/warehouse.js')) }}"></script>
@elseif($order->payment_method === 'etransfer' && $order->payment_status !== 'paid')
<p class="text-muted">Verify the e-transfer payment above before sending this order to warehouse.</p>
@elseif(auth()->user()->hasAdminPermission('orders.manage') && array_key_exists('warehouse_pending',$order->status_options))
<form method="post" action="{{ route('admin.orders.status',$order) }}">@csrf @method('PATCH')<input type="hidden" name="revision" value="{{ $order->revision }}"><input type="hidden" name="status" value="warehouse_pending"><button class="btn btn-primary">Send {{ $order->warehouse_round?'Back ':'' }}to Warehouse</button><p class="text-muted mt-2">Starts a fresh packing check. Earlier notes remain in Order History.</p></form>
@endif
</div></div>
@endif

@if($order->source==='pos' && $order->fulfillment==='pickup' && $order->status==='ready_to_dispatch' && $order->payment_status==='paid' && auth()->user()->hasAdminPermission('pos.manage') && ((int)$order->created_by===auth()->id() || auth()->user()->hasAdminPermission('orders.manage')))
<form method="post" action="{{ route('admin.pos.handover',$order) }}" class="panel panel-content">@csrf<input type="hidden" name="revision" value="{{ $order->revision }}"><label><input type="checkbox" name="handed_over" value="1" required> Items handed to the customer</label><button class="btn btn-primary" type="submit">Complete pickup</button></form>
@endif
