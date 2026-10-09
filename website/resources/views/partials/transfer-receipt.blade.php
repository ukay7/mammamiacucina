@if($order->payment_method === 'etransfer')
<section class="mmc-panel panel panel-content" style="margin:20px 0;padding:24px;border:1px solid #d9bd85;border-radius:8px">
<h2>E-transfer payment</h2>
@if(session('receipt_status'))<p role="status">{{ session('receipt_status') }}</p>@endif
@foreach(['receipt','revision','received','order'] as $errorKey) @error($errorKey)<p role="alert" style="color:#a00920">{{ $message }}</p>@enderror @endforeach
@if($order->payment_status === 'paid')
<p><strong>Paid — payment verified by administration.</strong></p>
@elseif($order->status === 'transfer_pending')
<p>Please upload your e-transfer receipt so our team can verify your payment and send your order to the warehouse.</p>
<p><strong>{{ $order->transfer_receipt_path ? 'Payment submitted — awaiting admin verification.' : 'Unpaid — awaiting your e-transfer receipt.' }}</strong></p>
@endif
@if($order->transfer_receipt_path)
@if(in_array(strtolower(pathinfo($order->transfer_receipt_path, PATHINFO_EXTENSION)), ['jpg','jpeg','png','webp']))
<a href="{{ route('orders.transfer-receipt',$order) }}" target="_blank" rel="noopener"><img src="{{ route('orders.transfer-receipt',$order) }}" alt="Submitted e-transfer receipt" style="display:block;max-width:100%;max-height:320px;object-fit:contain;margin:16px 0"></a>
@endif
<p><a class="btn btn-outline-primary mmc-text-link" href="{{ route('orders.transfer-receipt',$order) }}" target="_blank" rel="noopener">View uploaded receipt</a> · Submitted {{ $order->transfer_receipt_uploaded_at }}</p>
@endif
@if($order->status === 'transfer_pending' && $order->payment_status === 'unpaid' && auth()->check() && (auth()->user()->isCustomer() || auth()->user()->hasAdminPermission('orders.manage') || ($order->source==='pos' && (int)$order->created_by===auth()->id() && auth()->user()->hasAdminPermission('pos.manage'))))
<form action="{{ route('orders.transfer-receipt.store',$order) }}" method="post" enctype="multipart/form-data">
@csrf<input type="hidden" name="revision" value="{{ $order->revision }}">
<label for="transfer-receipt">{{ $order->transfer_receipt_path ? 'Replace receipt' : 'Upload receipt' }}</label>
<input id="transfer-receipt" class="form-control" type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf" required>
<p>JPG, PNG, WebP or PDF, maximum 5 MB. Uploading a receipt does not confirm payment.</p>
<button class="btn btn-primary mmc-button" type="submit">Submit receipt</button>
</form>
@if(!auth()->user()->isCustomer() && auth()->user()->hasAdminPermission('orders.manage') && $order->transfer_receipt_path)
<form method="post" action="{{ route('orders.transfer.verify',$order) }}" style="margin-top:24px">
@csrf<input type="hidden" name="revision" value="{{ $order->revision }}">
<label><input type="checkbox" name="received" value="1" required> I checked the receipt and confirmed the full payment was received.</label>
<button class="btn btn-primary" type="submit">Verify payment &amp; send to warehouse</button>
</form>
@endif
@endif
</section>
@endif
