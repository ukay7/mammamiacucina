@extends('admin.layout')
@section('title','Deleted Orders')
@section('content')
<p>Deleted orders are excluded from normal screens, reports and balances. Retained invoices, payments and history are view-only. There is no Restore action.</p>
<form class="mmc-filters" method="get"><label>Search deleted orders<input class="form-control" name="q" maxlength="200" value="{{ $search }}" placeholder="Order number, customer or email"></label><label>Rows<select class="form-control" name="per_page">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',20)===$size)>{{ $size }}</option>@endforeach</select></label><button class="btn btn-primary">Filter</button><a href="{{ route('admin.orders.deleted.index') }}">Reset</a></form>
<section class="panel panel-content"><div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Order / Customer</th><th>Deleted</th><th>Deleted by</th><th>Reason</th><th>Stock decision</th><th></th></tr></thead><tbody>
@forelse($orders as $order)<tr><td>{{ $order->number }}<small class="d-block">{{ $order->first_name }} {{ $order->last_name }} · {{ $order->email }}</small></td><td>{{ $order->deleted_at->format('d M Y H:i') }}</td><td>{{ $order->deletedBy?->name ?? 'Former user / migration' }}</td><td style="max-width:380px;overflow-wrap:anywhere">{{ $order->deletion_reason }}</td><td>{{ $order->deletion_restock?'Return deducted stock':'Keep inventory unchanged' }}</td><td><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.orders.deleted.show',$order->id) }}">View only</a></td></tr>
@empty<tr><td colspan="6">No deleted orders match these filters.</td></tr>@endforelse
</tbody></table></div>{{ $orders->links('pagination::bootstrap-4') }}</section>
@endsection
