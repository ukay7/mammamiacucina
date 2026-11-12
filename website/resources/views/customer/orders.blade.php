@extends('admin.layout')
@section('title','My Orders')
@section('content')
<div class="panel panel-content">
<p>{{ auth()->user()->name }} · {{ auth()->user()->account_type==='business'?'Business owner':'Individual' }}</p>
<p><a class="btn btn-outline-primary" href="{{ route('customer.documents') }}">My Documents</a></p>
<div style="overflow:auto"><table class="table"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Total CAD</th><th>Action</th></tr></thead><tbody>
@forelse($orders as $order)<tr><td>{{ $order->number }}</td><td>{{ \App\Support\BusinessTime::format($order->ordered_at,'d M Y H:i T') }}</td><td>{{ $order->status_label }}</td><td>{{ number_format(($order->final_total_cents??0)/100,2) }}</td><td><a href="{{ route('customer.order',$order) }}">View order</a> <a class="btn btn-outline-primary ml-2" href="{{ route('customer.order.print',$order) }}" target="_blank" rel="noopener">Print</a></td></tr>@empty<tr><td colspan="5">You have no orders yet.</td></tr>@endforelse
</tbody></table></div>{{ $orders->links() }}
<a class="btn btn-primary" href="{{ route('theme.product-grid') }}">Continue shopping</a>

</div>
@endsection
