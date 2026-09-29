@extends('admin.layout')
@section('title','Price Approvals')
@section('content')
<p>Only approved proposals change the store selling price. Pack options remain in admin.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Product</th><th>Selected price</th><th>Before (CAD)</th><th>Proposed (CAD)</th><th>Status</th><th>Submitted by</th><th></th></tr></thead><tbody>
@forelse($reviews as $review)<tr><td>{{ $review->product->premium_marketing_name }}</td><td>{{ $review->sale_label }}</td><td>{{ $review->previous_price===null?'Not set':number_format($review->previous_price,2) }}</td><td>{{ number_format($review->proposed_cents/100,2) }}</td><td>{{ ucfirst($review->status) }}</td><td>{{ $review->submitter?->name }}<small class="d-block">{{ $review->created_at }}</small></td><td><a class="btn btn-outline-primary" href="{{ route('admin.price-reviews.show',$review) }}">Review</a></td></tr>@empty<tr><td colspan="7">No pricing proposals yet.</td></tr>@endforelse
</tbody></table></div>{{ $reviews->links('pagination::bootstrap-4') }}
@endsection
