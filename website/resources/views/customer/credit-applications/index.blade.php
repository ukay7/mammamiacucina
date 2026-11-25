@extends('admin.layout')
@section('title', 'Credit Applications')
@include('customer.account-style')
@section('content')
<p><a href="{{ route('customer.credit') }}">← Credit &amp; Discounts</a></p>
<section class="panel panel-content account-hero">
    <div>
        <h2>Apply for credit</h2>
        <p>{{ $underReview ? 'Your signed application is awaiting review.' : ($active ? 'Complete the credit application and sign the personal guarantee online.' : 'New credit applications are currently unavailable.') }}</p>
    </div>
    @if(! $underReview && $active)
        @if($awaiting && $awaiting->template_id === $active->id)
            <a class="btn btn-primary" href="{{ route('customer.credit-applications.show', $awaiting) }}">Continue application</a>
        @else
            <form method="post" action="{{ route('customer.credit-applications.start') }}" data-confirm="Start an application and email a link to your verified address?">
                @csrf
                <button class="btn btn-primary" type="submit">{{ $awaiting ? 'Start with current template' : 'Start credit application' }}</button>
            </form>
        @endif
    @endif
</section>
<section class="panel panel-content">
    <h2>Your applications</h2>
    <div class="table-responsive"><table class="table account-table">
        <thead><tr><th scope="col">Application</th><th scope="col">Version</th><th scope="col">Status</th><th scope="col">Submitted</th><th scope="col">Actions</th></tr></thead>
        <tbody>
            @forelse($applications as $application)
                <tr>
                    <td><strong>{{ $application->reference }}</strong><small class="d-block">{{ $application->template->title }}</small></td>
                    <td>{{ $application->template->version }}</td>
                    <td><span class="account-state" data-state="{{ $application->status }}">{{ $application->status_label }}</span></td>
                    <td>{{ \App\Support\BusinessTime::format($application->submitted_at) ?? '—' }}</td>
                    <td>
                        <div class="account-actions">
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('customer.credit-applications.show', $application) }}">View</a>
                            @if($application->submitted_at)
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('customer.documents.file', ['credit-application', $application->id, 'download' => 1]) }}">Download signed PDF</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">You haven’t started a credit application.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    {{ $applications->links() }}
</section>
@endsection
