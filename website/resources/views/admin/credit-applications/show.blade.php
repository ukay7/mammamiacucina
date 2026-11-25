@extends('admin.layout')
@section('title', 'Credit Application '.$application->reference)
@include('customer.account-style')
@section('content')
<p><a href="{{ route('admin.credit-applications.index') }}">← Credit Applications</a></p>
<section class="panel panel-content account-hero">
    <div>
        <h2>{{ $application->customer->business_name ?: $application->customer->name }}</h2>
        <p>Customer #{{ $application->customer_id }} · Template {{ $application->template->version }}</p>
    </div>
    <span class="account-state" data-state="{{ $application->status }}">{{ $application->status_label }}</span>
</section>
<section class="panel panel-content">
    <h2>Application record</h2>
    <dl class="account-key-values">
        <dt>Created</dt><dd>{{ \App\Support\BusinessTime::format($application->created_at) }}</dd>
        <dt>Invitation email</dt><dd>{{ $application->invitationEmail ? ucfirst($application->invitationEmail->status) : '—' }}
            @if($application->invitationEmail?->sent_at) · {{ \App\Support\BusinessTime::format($application->invitationEmail->sent_at) }}@endif</dd>
        @if($application->submitted_at)
            @php($signed = $application->signed_snapshot)
            <dt>Requested credit limit</dt><dd>CAD {{ number_format($signed['details']['requested_limit'], 2) }}</dd>
            <dt>Guarantor</dt><dd>{{ $signed['signature']['name'] }}</dd>
            <dt>Verified account email</dt><dd>{{ $signed['signature']['verified_email'] }}</dd>
            <dt>Submitted</dt><dd>{{ \App\Support\BusinessTime::format($application->submitted_at) }}</dd>
        @endif
        @if($application->reviewed_at)
            <dt>Reviewed</dt><dd>{{ \App\Support\BusinessTime::format($application->reviewed_at) }} · {{ $application->reviewer_name }}</dd>
            @if($application->review_note)<dt>Note to customer</dt><dd class="account-message">{{ $application->review_note }}</dd>@endif
        @endif
    </dl>
    @if($application->submitted_at)
        <div class="account-actions mt-3">
            <a class="btn btn-primary" href="{{ route('admin.credit-applications.document', $application) }}" target="_blank" rel="noopener">Open / Print signed PDF</a>
            <a class="btn btn-outline-primary" href="{{ route('admin.credit-applications.document', [$application, 'download' => 1]) }}">Download signed PDF</a>
        </div>
    @endif
</section>
@if($application->status === 'submitted')
    <section class="panel panel-content">
        <h2>Review application</h2>
        <p>Set any approved credit limit and payment terms in the customer’s Manager Settings.</p>
        <form method="post" action="{{ route('admin.credit-applications.review', $application) }}">
            @csrf @method('PATCH')
            <label for="application-review-note">Note to customer</label>
            <textarea class="form-control mb-2" id="application-review-note" name="review_note" rows="3" maxlength="1000" aria-describedby="credit-review-help">{{ old('review_note') }}</textarea>
            <p id="credit-review-help" class="text-muted">A note is required for a rejection.</p>
            <div class="account-actions">
                <button type="submit" class="btn btn-primary" name="status" value="approved">Approve application</button>
                <button type="submit" class="btn btn-outline-danger" name="status" value="rejected">Reject application</button>
            </div>
        </form>
    </section>
@endif
@endsection
