@extends('admin.layout')
@section('title', 'Credit Applications')
@include('customer.account-style')
@section('content')
<section class="panel panel-content">
    <div class="account-section-heading">
        <div>
            <h2>Send an invitation</h2>
            <p class="mb-0">{{ $active ? 'Using published template '.$active->version : 'Publish a lawyer-approved template to enable invitations.' }}</p>
        </div>
        @if(auth()->user()->isSuper())<a class="btn btn-outline-primary" href="{{ route('admin.credit-applications.templates.index') }}">Manage templates</a>@endif
    </div>
    @if($active)
        <form class="account-filters mb-0" method="post" action="{{ route('admin.credit-applications.invite') }}" data-confirm="Email the application invitation to this customer’s verified address?">
            @csrf
            <label for="invite-customer-id">Customer number<input class="form-control" id="invite-customer-id" type="number" min="1" name="customer_id" value="{{ $customer?->id ?? old('customer_id') }}" required></label>
            <button class="btn btn-primary" type="submit">Send invitation</button>
        </form>
    @endif
</section>
<section class="panel panel-content">
    <div class="account-section-heading"><h2>{{ $customer ? 'Applications for '.($customer->business_name ?: $customer->name).' (#'.$customer->id.')' : 'Applications' }}</h2></div>
    <form class="account-filters" method="get" action="{{ route('admin.credit-applications.index') }}">
        <label for="filter-customer-id">Customer number<input class="form-control" id="filter-customer-id" type="number" min="1" name="customer_id" value="{{ $customer?->id }}"></label>
        <label for="filter-application-status">Status<select class="form-control" id="filter-application-status" name="status">
            <option value="">All statuses</option>
            @foreach(\App\Models\CreditApplication::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select></label>
        <button type="submit" class="btn btn-outline-primary">Filter</button>
        @if($customer || $status)<a href="{{ route('admin.credit-applications.index') }}">Clear</a>@endif
    </form>
    <div class="table-responsive"><table class="table account-table">
        <thead><tr><th scope="col">Application</th><th scope="col">Customer</th><th scope="col">Status</th><th scope="col">Invitation</th><th scope="col">Submitted</th><th scope="col">Action</th></tr></thead>
        <tbody>
            @forelse($applications as $application)
                <tr>
                    <td><strong>{{ $application->reference }}</strong><small class="d-block">Template {{ $application->template->version }}</small></td>
                    <td>{{ $application->customer->business_name ?: $application->customer->name }} <small class="d-block">#{{ $application->customer_id }}</small></td>
                    <td><span class="account-state" data-state="{{ $application->status }}">{{ $application->status_label }}</span></td>
                    <td>{{ $application->invitationEmail ? ucfirst($application->invitationEmail->status) : '—' }}</td>
                    <td>{{ \App\Support\BusinessTime::format($application->submitted_at) ?? '—' }}</td>
                    <td><a href="{{ route('admin.credit-applications.show', $application) }}" aria-label="View {{ $application->reference }}">View application</a></td>
                </tr>
            @empty
                <tr><td colspan="6">No applications match these filters.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    {{ $applications->links() }}
</section>
@endsection
