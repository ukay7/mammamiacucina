@extends('admin.layout')
@section('title', 'Credit Application Templates')
@include('customer.account-style')
@section('content')
<p><a href="{{ route('admin.credit-applications.index') }}">← Credit Applications</a></p>
<section class="panel panel-content">
    <div class="account-section-heading"><h2>Template versions</h2>
        <a class="btn btn-primary" href="{{ route('admin.credit-applications.templates.create') }}">New template draft</a>
    </div>
    <div class="table-responsive"><table class="table account-table">
        <thead><tr><th scope="col">Version</th><th scope="col">Title</th><th scope="col">Status</th><th scope="col">Published</th><th scope="col">Action</th></tr></thead>
        <tbody>
            @forelse($templates as $template)
                <tr><td>{{ $template->version }}</td><td>{{ $template->title }}</td>
                    <td>{{ $template->retired_at ? 'Retired' : ($template->published_at ? 'Published' : 'Draft') }}</td>
                    <td>{{ \App\Support\BusinessTime::format($template->published_at) ?? '—' }}</td>
                    <td><a href="{{ route('admin.credit-applications.templates.show', $template) }}" aria-label="View template {{ $template->version }}">View template</a></td></tr>
            @empty
                <tr><td colspan="5">No templates have been saved.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    {{ $templates->links() }}
</section>
@endsection
