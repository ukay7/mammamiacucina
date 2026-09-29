@extends('admin.layout')
@section('title','Review Product Price')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/product-dna.css') }}">
<div class="dna-review">
<a href="{{ route('admin.price-reviews.index') }}">Back to approvals</a>
<h2 class="mt-3">{{ $review->product->premium_marketing_name }} · {{ $review->product->qr_code }}</h2>
<p>Proposal #{{ $review->id }} · {{ ucfirst($review->status) }} · Draft revision {{ $review->draft_revision }} · Submitted by {{ $review->submitter?->name }} on {{ $review->created_at }}</p>
<div class="dna-results"><div>Previous price<strong>{{ $review->previous_price===null?'Not set':'CAD '.number_format($review->previous_price,2) }}</strong></div><div>Proposed price<strong>CAD {{ number_format($review->proposed_cents/100,2) }}</strong>{{ $review->sale_label }}</div><div>Current store price<strong>{{ $review->product->total_selling_price_cad===null?'Not set':'CAD '.number_format($review->product->total_selling_price_cad,2) }}</strong></div></div>
<h3>Calculation submitted for review</h3>
<div class="row"><div class="col-md-6"><table class="table"><tbody>@foreach($review->snapshot['inputs'] as $key=>$value)<tr><th>{{ ucwords(str_replace('_',' ',$key)) }}</th><td>{{ $value===null?'Not specified':$value }}</td></tr>@endforeach</tbody></table></div>
<div class="col-md-6"><table class="table"><tbody>@foreach($review->snapshot['result'] as $key=>$value)@if($key!=='options')<tr><th>{{ ucwords(str_replace(['_cents','_'],[' (CAD)',' '],$key)) }}</th><td>{{ $value===null?'—':number_format(str_ends_with($key,'_cents')?$value/100:$value,4) }}</td></tr>@endif @endforeach</tbody></table></div></div>
<h3>Pack options (admin review only)</h3><div class="table-responsive"><table class="table"><thead><tr><th>Pack</th><th>Pieces</th><th>Discount %</th><th>Total CAD</th></tr></thead><tbody>@foreach($review->snapshot['result']['options'] as $option)<tr><td>{{ $option['label'] }}</td><td>{{ $option['quantity'] }}</td><td>{{ $option['discount'] }}</td><td>{{ number_format($option['total_cents']/100,2) }}</td></tr>@endforeach</tbody></table></div>
@if($review->status==='pending')
<form method="post" action="{{ route('admin.price-reviews.decide',$review) }}" class="dna-panel">@csrf
<p>Approval publishes {{ $review->sale_label }} at CAD {{ number_format($review->proposed_cents/100,2) }} as the single existing store price. Existing orders and stock quantities are unchanged.</p>
<label class="d-block"><input type="checkbox" name="unit_confirmed" value="1"> I have verified that one current checkout/POS and inventory unit corresponds to this selected selling unit.</label>
<label for="note">Review note (required when rejecting)</label><textarea name="note" id="note" class="form-control" maxlength="2000">{{ old('note') }}</textarea>
<div class="dna-controls"><button name="decision" value="approve" class="btn btn-primary">Approve and Publish Price</button><button name="decision" value="reject" class="btn btn-outline-primary">Reject</button></div>
</form>
@else<p>Reviewed by {{ $review->reviewer?->name??'—' }} · {{ $review->reviewed_at }}</p><p>{{ $review->review_note }}</p>@endif
</div>
@endsection
