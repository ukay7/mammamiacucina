@extends('admin.layout')
@section('title', 'Profile Change Request #'.$change->id)
@include('customer.account-style')
@section('content')
<p><a href="{{ route('admin.profile-requests.index', ['status' => $change->status]) }}">Back to profile change requests</a></p>
<section class="panel panel-content">
    <div class="account-section-heading">
        <div><h2>{{ $change->customer->business_name ?: $change->customer->name }}</h2>
            <p>Customer #{{ $change->customer_id }} · {{ \App\Support\BusinessTime::format($change->created_at) }}</p></div>
        <strong>{{ \App\Models\CustomerProfileRequest::STATUSES[$change->status] }}</strong>
    </div>
    <div class="table-responsive"><table class="table account-table">
        <thead><tr><th scope="col">Field</th><th scope="col">Value at submission</th><th scope="col">Requested value</th></tr></thead>
        <tbody>@foreach($change->proposed as $field => $value)
            <tr><th scope="row">{{ \App\Services\CustomerProfileChanges::LABELS[$field] }}</th>
                <td>{{ $change->original[$field] ?? '—' }}</td><td>{{ $value ?? 'Clear this field' }}</td></tr>
        @endforeach</tbody>
    </table></div>
    @if($change->status === 'pending')
        <form method="post" action="{{ route('admin.profile-requests.review', $change) }}" class="mt-3">
            @csrf @method('PATCH')
            <label for="review-note">Note to customer</label>
            <textarea id="review-note" class="form-control mb-3" name="review_note" rows="3" maxlength="1000" aria-describedby="review-note-help">{{ old('review_note') }}</textarea>
            <p id="review-note-help" class="text-muted">Required when rejecting a request.</p>
            <div class="account-actions">
                <button type="submit" name="status" value="approved" class="btn btn-primary">Approve and update profile</button>
                <button type="submit" name="status" value="rejected" class="btn btn-outline-danger">Reject request</button>
            </div>
        </form>
    @else
        <p class="mb-1">Reviewed {{ \App\Support\BusinessTime::format($change->reviewed_at) }}</p>
        @if($change->review_note)<p class="account-message">{{ $change->review_note }}</p>@endif
    @endif
</section>
@endsection
