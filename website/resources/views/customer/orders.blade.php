@extends('admin.layout')
@section('title','My Orders')
@include('customer.account-style')
@section('content')
<div class="panel panel-content">
<nav class="account-actions mb-3" aria-label="Order views">
    @foreach(['' => 'All orders', 'open' => 'Open', 'closed' => 'Completed / closed', 'paid' => 'Paid'] as $value => $label)
        <a class="btn {{ ($filters['view'] ?? '') === $value ? 'btn-primary' : 'btn-outline-primary' }}" @if(($filters['view'] ?? '') === $value) aria-current="page" @endif
           href="{{ route('customer.orders', array_filter(['view' => $value, 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null])) }}">{{ $label }}</a>
    @endforeach
</nav>
<form class="account-filters" method="get" action="{{ route('customer.orders') }}">
    @if(!empty($filters['view']))<input type="hidden" name="view" value="{{ $filters['view'] }}">@endif
    <label>From<input type="date" class="form-control" name="from" value="{{ $filters['from'] ?? '' }}"></label>
    <label>To<input type="date" class="form-control" name="to" value="{{ $filters['to'] ?? '' }}"></label>
    <button type="submit" class="btn btn-outline-primary">Apply dates</button>
    @if(!empty($filters['from']) || !empty($filters['to']))<a href="{{ route('customer.orders', array_filter(['view' => $filters['view'] ?? null])) }}">Clear dates</a>@endif
</form>
<div style="overflow:auto"><table class="table"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Total CAD</th><th>Action</th></tr></thead><tbody>
@forelse($orders as $order)<tr><td>{{ $order->number }}</td><td>{{ \App\Support\BusinessTime::format($order->ordered_at,'d M Y H:i T') }}</td><td>{{ $order->status_label }}<small class="text-muted d-block">{{ $order->payment_status_label }}</small></td><td>{{ number_format(($order->final_total_cents??0)/100,2) }}</td><td><a href="{{ route('customer.order',$order) }}">View order</a> <a class="btn btn-outline-primary ml-2" href="{{ route('customer.order.print',$order) }}" target="_blank" rel="noopener">Print</a></td></tr>@empty<tr><td colspan="5">No orders match these filters.</td></tr>@endforelse
</tbody></table></div>{{ $orders->links() }}
<a class="btn btn-primary" href="{{ route('customer.catalogue') }}">Browse Product Catalogue</a>

</div>
@endsection
