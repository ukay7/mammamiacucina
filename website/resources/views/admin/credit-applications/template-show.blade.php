@extends('admin.layout')
@section('title', 'Credit Application Template '.$template->version)
@include('customer.account-style')
@section('content')
<p><a href="{{ route('admin.credit-applications.templates.index') }}">← Template versions</a></p>
<section class="panel panel-content">
    <div class="account-section-heading">
        <div><h2>{{ $template->title }}</h2><p>{{ $template->retired_at ? 'Retired' : ($template->published_at ? 'Published' : 'Draft') }}</p></div>
        @unless($template->published_at)<a class="btn btn-outline-primary" href="{{ route('admin.credit-applications.templates.edit', $template) }}">Edit draft</a>@endunless
    </div>
    <div class="credit-document-terms">
        <h2>Credit application terms</h2><p class="credit-document-text">{{ $template->application_terms }}</p>
        <h2>Personal guarantee</h2><p class="credit-document-text">{{ $template->guarantee_terms }}</p>
        <h2>Electronic-signature consent statement</h2><p class="credit-document-text">{{ $template->consent_statement }}</p>
    </div>
</section>
<section class="panel panel-content">
    @if($template->published_at)
        <h2>Publication record</h2>
        <p>Published {{ \App\Support\BusinessTime::format($template->published_at) }}.</p>
        <p>Legal approval reference <strong>{{ $template->legal_approval_reference }}</strong></p>
        @if($template->retired_at)
            <p>Retired {{ \App\Support\BusinessTime::format($template->retired_at) }}.</p>
        @else
            <form method="post" action="{{ route('admin.credit-applications.templates.retire', $template) }}" data-confirm="Retire this template and pause new signatures?">
                @csrf <button type="submit" class="btn btn-outline-danger">Retire template</button>
            </form>
        @endif
    @else
        <h2>Publish after legal approval</h2>
        <p>Customers sign by typing their name, accepting the consent statement and confirming their account password. Publication enables this form for customers and retires any earlier active version.</p>
        <form method="post" action="{{ route('admin.credit-applications.templates.publish', $template) }}" data-confirm="Publish this version for customers to sign?">
            @csrf
            <input type="hidden" name="revision" value="{{ $template->revision }}">
            <div class="form-group">
                <label for="lawyer-reference">Lawyer approval reference</label>
                <input class="form-control" id="lawyer-reference" name="legal_approval_reference" maxlength="255" value="{{ old('legal_approval_reference') }}" required>
            </div>
            <label class="d-block credit-application-consent"><input type="checkbox" name="approved" value="1" required>
                <span>I confirm the company’s lawyer approved this wording and the electronic-signature process.</span></label>
            <button type="submit" class="btn btn-primary">Publish approved template</button>
        </form>
    @endif
</section>
@endsection
