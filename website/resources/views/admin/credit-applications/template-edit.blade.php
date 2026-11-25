@extends('admin.layout')
@section('title', $template->exists ? 'Edit Template '.$template->version : 'New Credit Application Template')
@include('customer.account-style')
@section('content')
<p><a href="{{ route('admin.credit-applications.templates.index') }}">← Template versions</a></p>
<section class="panel panel-content">
    <p>Paste the wording for the application, personal guarantee, and electronic-signature consent. Save it as a draft for legal review.</p>
    <form method="post" action="{{ $template->exists ? route('admin.credit-applications.templates.update', $template) : route('admin.credit-applications.templates.store') }}">
        @csrf
        @if($template->exists)@method('PUT')<input type="hidden" name="revision" value="{{ $template->revision }}">@endif
        <div class="form-group"><label for="template-title">Document title</label><input class="form-control" id="template-title" name="title" value="{{ old('title', $template->title) }}" maxlength="100" required></div>
        @foreach(['application_terms' => 'Credit application terms', 'guarantee_terms' => 'Personal guarantee', 'consent_statement' => 'Electronic-signature consent statement'] as $field => $label)
            <div class="form-group">
                <label for="{{ $field }}">{{ $label }}</label>
                <textarea class="form-control" id="{{ $field }}" name="{{ $field }}" rows="{{ $field === 'consent_statement' ? 4 : 9 }}" maxlength="{{ $field === 'consent_statement' ? 2000 : 12000 }}" required>{{ old($field, $template->$field) }}</textarea>
            </div>
        @endforeach
        <button type="submit" class="btn btn-primary">Save draft</button>
    </form>
</section>
@endsection
