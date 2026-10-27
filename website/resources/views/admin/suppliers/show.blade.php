@extends('admin.layout')
@section('title','Supplier #'.$supplier->id)
@section('content')
<section class="panel panel-content"><div class="d-flex flex-wrap justify-content-between"><div><h2>{{ $supplier->name }}</h2><p>#{{ $supplier->id }} · {{ $supplier->is_active?'Active':'Inactive' }}</p></div><div>@if(auth()->user()->hasAdminPermission('suppliers.manage'))<a class="btn btn-outline-primary" href="{{ route('admin.suppliers.edit',$supplier) }}">Edit Supplier</a>@endif @if($supplier->is_active && auth()->user()->hasAdminPermission('purchases.manage'))<a class="btn btn-primary" href="{{ route('admin.supplier-orders.create',['supplier_id'=>$supplier->id]) }}">Create Supplier Order</a>@endif</div></div>
<dl class="row">@foreach(['contact_name'=>'Contact','email'=>'Email','phone'=>'Phone','address'=>'Address','notes'=>'Notes'] as $key=>$label)<dt class="col-md-2">{{ $label }}</dt><dd class="col-md-10" style="white-space:pre-line;overflow-wrap:anywhere">{{ $supplier->$key ?: 'Not provided' }}</dd>@endforeach</dl>
@if(auth()->user()->hasAdminPermission('suppliers.manage'))
@if($supplier->products_count || $supplier->orders_count)
<p class="text-muted">This supplier has linked products or orders. You can mark it inactive from Edit Supplier to preserve its history.</p>
@else
<form method="post" action="{{ route('admin.suppliers.destroy',$supplier) }}" onsubmit="return confirm('Delete this unused supplier?');">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Delete unused supplier</button></form>
@endif
@endif
</section>
<section class="panel panel-content"><h3>Linked products</h3><div class="table-responsive"><table class="table"><thead><tr><th>Record ID</th><th>Product code</th><th>Internal code</th><th>Product</th><th>Status</th></tr></thead><tbody>@forelse($products as $product)<tr><td>{{ $product->id }}</td><td>{{ $product->product_code ?: '—' }}</td><td>{{ $product->qr_code }}</td><td>{{ $product->premium_marketing_name }}</td><td>{{ $product->is_active?'Active':'Inactive' }}</td></tr>@empty<tr><td colspan="5">No linked products.</td></tr>@endforelse</tbody></table></div>{{ $products->links() }}</section>
@if(auth()->user()->hasAdminPermission('purchases.view'))<section class="panel panel-content"><h3>Recent supplier orders</h3>@forelse($orders as $order)<p><a href="{{ route('admin.supplier-orders.show',$order) }}">{{ $order->number }}</a> · {{ \App\Models\SupplierOrder::STATUS_LABELS[$order->status] }} · {{ $order->created_at->format('d M Y H:i') }}</p>@empty<p>No supplier orders yet.</p>@endforelse</section>@endif
@endsection
