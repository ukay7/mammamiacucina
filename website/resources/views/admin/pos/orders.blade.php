@extends('admin.layout')
@section('title','My POS Orders')
@section('content')
<p><a class="btn btn-primary" href="{{ route('admin.pos.index') }}">New sale</a></p>
<div class="panel panel-content table-responsive"><table class="table"><thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th>Action</th></tr></thead><tbody>@foreach($orders as $order)<tr><td>{{ $order->number }}</td><td>{{ $order->first_name }} {{ $order->last_name }}</td><td>{{ $order->status_label }}</td><td>{{ $order->payment_label }} · {{ ucfirst($order->payment_status) }}@if($order->transfer_receipt_path && $order->payment_status==='unpaid')<br>Receipt submitted — awaiting verification
@endif</td><td><a href="{{ route('admin.pos.order',$order) }}">View / upload receipt</a></td></tr>@endforeach</tbody></table>{{ $orders->links() }}</div>
@endsection
