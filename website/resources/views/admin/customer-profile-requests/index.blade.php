@extends('admin.layout')
@section('title', 'Profile Change Requests')
@include('customer.account-style')
@section('content')
<section class="panel panel-content">
    <nav class="account-actions mb-3" aria-label="Filter profile requests">
        @foreach(\App\Models\CustomerProfileRequest::STATUSES as $value => $label)
            <a class="btn {{ $status === $value ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('admin.profile-requests.index', ['status' => $value]) }}" @if($status === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="table-responsive"><table class="table account-table">
        <thead><tr><th scope="col">Request</th><th scope="col">Customer</th><th scope="col">Submitted</th><th scope="col">Action</th></tr></thead>
        <tbody>@forelse($changes as $change)
            <tr><td>#{{ $change->id }}</td>
                <td>{{ $change->customer->business_name ?: $change->customer->name }} · #{{ $change->customer_id }}</td>
                <td>{{ \App\Support\BusinessTime::format($change->created_at) }}</td>
                <td><a href="{{ route('admin.profile-requests.show', $change) }}" aria-label="View request #{{ $change->id }}">View request</a></td></tr>
        @empty<tr><td colspan="4">No {{ $status }} profile change requests.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $changes->links() }}
</section>
@endsection
