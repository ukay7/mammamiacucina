@extends('admin.layout')
@section('title','Email Recipients & Consent')
@section('content')
@include('admin.notifications.style')
<div class="nt-heading"><p>See who received your campaigns, who unsubscribed, and the consent recorded for each email address.</p><div class="nt-actions"><a class="btn btn-outline-primary" href="{{ route('admin.notifications.index') }}">Notifications</a><a class="btn btn-outline-primary" href="{{ route('admin.notifications.recipients.export',request()->only('q','status','campaign')) }}">Export CSV</a></div></div>
<div class="nt-stats nt-consent-stats">
@foreach(['all'=>'Email addresses','unsubscribed'=>'Unsubscribed','subscribed'=>'Subscribed','unknown'=>'Consent not recorded'] as $key=>$label)
<div class="panel"><span>{{ $label }}</span><strong>{{ number_format($key==='all'?$stats->sum():$stats->get($key,0)) }}</strong></div>
@endforeach
</div>
<div class="nt-health">These preferences control promotional emails. Order receipts and password-reset emails continue. “Consent not recorded” is not permission to send; record valid consent before including an address in marketing.</div>
@if(auth()->user()->hasAdminPermission('notifications.consent'))
<form class="panel nt-panel nt-filters" method="post" action="{{ route('admin.notifications.recipients.create') }}">@csrf
<label>Find or add an email address<input class="form-control" type="email" name="address" value="{{ old('address') }}" required maxlength="255" placeholder="recipient@example.com"></label><button class="btn btn-primary">Open consent record</button><p class="nt-muted mb-0">This does not create a customer or send an email.</p></form>
@endif
<form class="panel nt-panel nt-filters" method="get">
<label>Email, business or recipient<input class="form-control" name="q" maxlength="150" value="{{ request('q') }}" placeholder="Search recipients"></label>
<label>Subscription status<select class="form-control" name="status"><option value="">All statuses</option>@foreach(['unsubscribed'=>'Unsubscribed','subscribed'=>'Subscribed','unknown'=>'Consent not recorded','blocked'=>'Provider blocked'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
@if(request('campaign'))<input type="hidden" name="campaign" value="{{ request('campaign') }}"><span>{{ $selectedCampaign?->number ?? 'Campaign' }}</span>@endif
<button class="btn btn-primary">Filter</button><a class="btn btn-outline-primary" href="{{ route('admin.notifications.recipients') }}">Clear</a>
</form>
<div class="panel nt-panel"><div class="table-responsive"><table class="table nt-table nt-recipient-table"><thead><tr><th>Recipient</th><th>Campaign emails accepted</th><th>Last accepted</th><th>Current status</th><th>Last unsubscribe</th><th></th></tr></thead><tbody>
@forelse($recipients as $s)
@php($customer=$s->latestEmail?->customer)
<tr><td><a href="{{ route('admin.notifications.recipient',$s) }}"><strong>{{ $s->address }}</strong></a>
<small>{{ $customer?->business_name ?: $s->latestEmail?->recipient_name }} <x-business-customer-icon :customer="$customer" /></small>@if($customer?->business_name)<small>{{ $customer->name }}</small>@endif</td>
<td>{{ number_format($s->accepted_count) }}</td><td>{{ \App\Support\BusinessTime::format($s->last_sent_at) ?: '—' }}</td>
<td><span class="nt-badge" data-state="{{ $s->status }}">{{ $s->status_label }}</span>@if($s->suppression && $s->suppression->reason!=='unsubscribed')<small class="text-danger">Provider block: {{ $s->suppression->reason }}</small>@endif</td>
<td>{{ \App\Support\BusinessTime::format($s->unsubscribed_at) ?: '—' }}@if($s->latestUnsubscribe?->delivery?->campaign)<small>Via {{ $s->latestUnsubscribe->delivery->campaign->number }}</small>@elseif($s->latestUnsubscribe)<small>{{ $s->latestUnsubscribe->source==='admin'?'Recorded by admin':'Earlier unsubscribe' }}</small>@endif</td>
<td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.notifications.recipient',$s) }}">History &amp; consent</a></td></tr>
@empty<tr><td colspan="6" class="text-center py-4">No recipients match these filters.</td></tr>@endforelse
</tbody></table></div>{{ $recipients->links() }}<p class="nt-muted">Accepted means the email provider accepted the message. Test emails are excluded from these counts. An unsubscribe remains effective until fresh consent is recorded; it never expires automatically.</p></div>
@endsection
