@extends('admin.layout')
@section('title', $staff ? 'Customer Support' : 'Customer Service')
@include('customer.account-style')
@section('content')
<div @class(['account-two-col' => ! $staff])>
@unless($staff)
    <section class="panel panel-content">
        <div class="account-section-heading"><h2>New support request</h2></div>
        <form method="post" action="{{ route('customer.support.store') }}">
            @csrf
            <div class="form-group"><label for="support-subject">Subject</label><input class="form-control" id="support-subject" name="subject" value="{{ old('subject') }}" maxlength="150" required></div>
            <div class="form-group"><label for="support-body">Message</label><textarea class="form-control" id="support-body" name="body" rows="6" maxlength="6000" required>{{ old('body') }}</textarea></div>
            <button class="btn btn-primary" type="submit">Submit request</button>
        </form>
    </section>
@endunless
<section class="panel panel-content">
    <div class="account-section-heading"><h2>{{ $staff ? 'Support inbox' : 'My conversations' }}</h2></div>
    <form method="get" class="account-filters">
        <label>Status<select class="form-control" name="status"><option value="">All requests</option>@foreach(\App\Models\SupportTicket::STATUSES as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select></label>
        <button class="btn btn-outline-primary" type="submit">Filter</button>
    </form>
    <div class="table-responsive"><table class="table account-table"><thead><tr><th>Request</th>@if($staff)<th>Customer</th>@endif<th>Status</th><th>Last activity</th></tr></thead>
        <tbody>@forelse($tickets as $ticket)
            <tr><td><a href="{{ route($staff ? 'admin.support.show' : 'customer.support.show', $ticket) }}">{{ $ticket->subject }}</a><small class="d-block text-muted">{{ $ticket->label }}</small></td>
                @if($staff)<td>{{ $ticket->customer->business_name ?: $ticket->customer->name }}<small class="d-block text-muted">Customer #{{ $ticket->customer_id }}</small></td>@endif
                <td><span class="account-state" data-state="{{ $ticket->status }}">{{ \App\Models\SupportTicket::STATUSES[$ticket->status] }}</span></td>
                <td>{{ \App\Support\BusinessTime::format($ticket->updated_at, 'd M Y H:i T') }}</td></tr>
        @empty<tr><td colspan="{{ $staff ? 4 : 3 }}">No requests match this filter.</td></tr>@endforelse</tbody>
    </table></div>{{ $tickets->links() }}
</section>
</div>
@endsection
