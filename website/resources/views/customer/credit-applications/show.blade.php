@extends('admin.layout')
@section('title', 'Credit Application '.$application->reference)
@include('customer.account-style')
@section('content')
<p><a href="{{ route('customer.credit-applications.index') }}">← Credit Applications</a></p>
<section class="panel panel-content account-hero">
    <div><h2>{{ $template->title }}</h2><p>Template {{ $template->version }} @if($application->submitted_at) · Submitted {{ \App\Support\BusinessTime::format($application->submitted_at) }} @endif</p></div>
    <span class="account-state" data-state="{{ $application->status }}">{{ $application->status_label }}</span>
</section>
@if($application->submitted_at)
    <section class="panel panel-content">
        <h2>Signed document</h2>
        @if($application->reviewed_at)<p>Reviewed {{ \App\Support\BusinessTime::format($application->reviewed_at) }}.</p>@endif
        @if($application->review_note)<p class="account-message">{{ $application->review_note }}</p>@endif
        <div class="account-actions">
            <a class="btn btn-primary" target="_blank" rel="noopener" href="{{ route('customer.documents.file', ['credit-application', $application->id]) }}">Open / Print signed PDF</a>
            <a class="btn btn-outline-primary" href="{{ route('customer.documents.file', ['credit-application', $application->id, 'download' => 1]) }}">Download signed PDF</a>
        </div>
    </section>
@elseif(! $editable)
    <p class="alert alert-warning">This form version is closed. Check Credit Applications for the current form.</p>
@elseif($preview)
    <section class="panel panel-content credit-application-review">
        <div class="account-section-heading">
            <h2>Review your completed form</h2>
            <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="{{ route('customer.credit-applications.draft', $application) }}">Open unsigned PDF</a>
        </div>
        @include('documents.credit-application-content', $preview)
    </section>
    <section class="panel panel-content">
        <h2>Sign and submit</h2>
        <p>Sign with your own name as the guarantor and confirm your account password.</p>
        <form method="post" action="{{ route('customer.credit-applications.submit', $application) }}" data-confirm="Submit the signed application for review?">
            @csrf
            <input type="hidden" name="review_token" value="{{ $application->review_token }}">
            <label class="d-block credit-application-consent">
                <input type="checkbox" name="consent" value="1" required>
                <span>{{ $preview['template']['consent_statement'] }}</span>
            </label>
            <div class="row">
                <div class="col-md-6 mb-3"><label for="signature">Electronic signature (full name)</label>
                    <input class="form-control" id="signature" name="signature" maxlength="150" autocomplete="name" required></div>
                <div class="col-md-6 mb-3"><label for="credit-password">Current password</label>
                    <input class="form-control" id="credit-password" name="password" type="password" autocomplete="current-password" maxlength="255" required></div>
            </div>
            <div class="account-actions">
                <button type="submit" class="btn btn-primary">Sign and submit application</button>
                <a class="btn btn-outline-primary" href="{{ route('customer.credit-applications.show', $application) }}">Edit details</a>
            </div>
        </form>
    </section>
@else
    @php
        $details = $draft['details'] ?? [];
        $address = implode(', ', array_filter([$customer->address, $customer->city, $customer->province, $customer->postal_code, $customer->country]));
    @endphp
    <form method="post" action="{{ route('customer.credit-applications.prepare', $application) }}" data-credit-application-draft>
        @csrf
        <section class="panel panel-content">
            <h2>Applicant details</h2>
            <div class="row">
                <div class="col-md-6 mb-3"><label for="applicant-name">Legal business or applicant name</label>
                    <input class="form-control" id="applicant-name" name="applicant_name" value="{{ old('applicant_name', $details['applicant_name'] ?? ($customer->business_name ?: $customer->name)) }}" maxlength="150" required></div>
                <div class="col-md-6 mb-3"><label for="contact-name">Contact name</label>
                    <input class="form-control" id="contact-name" name="contact_name" value="{{ old('contact_name', $details['contact_name'] ?? $customer->name) }}" maxlength="150" required></div>
                <div class="col-md-6 mb-3"><label for="contact-phone">Contact phone</label>
                    <input class="form-control" id="contact-phone" name="contact_phone" type="tel" value="{{ old('contact_phone', $details['contact_phone'] ?? $customer->phone) }}" maxlength="40" required></div>
                <div class="col-md-6 mb-3"><label for="requested-limit">Requested credit limit (CAD)</label>
                    <input class="form-control" id="requested-limit" name="requested_limit" type="number" min="0.01" max="9999999.99" step="0.01" value="{{ old('requested_limit', $details['requested_limit'] ?? '') }}" required></div>
                <div class="col-12"><label for="billing-address">Billing address</label>
                    <textarea class="form-control" id="billing-address" name="billing_address" rows="3" maxlength="400" required>{{ old('billing_address', $details['billing_address'] ?? $address) }}</textarea></div>
            </div>
        </section>
        <section class="panel panel-content">
            <h2>Guarantor details</h2>
            <div class="row">
                <div class="col-md-6 mb-3"><label for="guarantor-name">Your full legal name</label>
                    <input class="form-control" id="guarantor-name" name="guarantor_name" value="{{ old('guarantor_name', $details['guarantor_name'] ?? $customer->name) }}" maxlength="150" required></div>
                <div class="col-md-6 mb-3"><label for="signing-title">Title or role (optional)</label>
                    <input class="form-control" id="signing-title" name="signing_title" value="{{ old('signing_title', $details['signing_title'] ?? '') }}" maxlength="100"></div>
                <div class="col-12 mb-3"><label for="guarantor-address">Guarantor’s mailing address</label>
                    <textarea class="form-control" id="guarantor-address" name="guarantor_address" rows="3" maxlength="400" required>{{ old('guarantor_address', $details['guarantor_address'] ?? '') }}</textarea></div>
            </div>
            <button type="submit" class="btn btn-primary">Review application and guarantee</button>
        </section>
    </form>
@endif
@endsection
