@extends('admin.layout')
@section('title','Email History')
@section('content')
<div class="panel panel-content">
<h2>Email History</h2>
<p>Accepted means the sending server accepted the message, not confirmed inbox delivery. Logged only means no external email was sent. Times use {{ config('app.timezone') }}.</p>
<p>Queued customer emails are delivered by the server scheduler. Retry means another attempt is scheduled; Needs review means check provider logs before sending again. Message copies and attached receipts are saved encrypted for new delivery attempts. Private verification/reset links are hidden; original queue data is removed after acceptance.</p>
<a href="{{ route('admin.email.edit') }}" class="btn btn-outline-primary">SMTP configuration</a>
<form method="get" class="my-3"><label for="email-search">Search recipient</label><input id="email-search" class="form-control" name="search" value="{{ $search }}" maxlength="255"><button class="btn btn-primary mt-2">Search</button></form>
<div class="table-responsive"><table class="table"><thead><tr><th>Attempted at</th><th>Recipient</th><th>Email / subject</th><th>Status</th><th>Sent at</th><th>Message ID</th><th>Content</th></tr></thead><tbody>
@forelse($emails as $email)<tr><td>{{ $email->created_at }}</td><td>{{ $email->recipient }}</td><td>{{ str_replace('_',' ',$email->type) }}<br>{{ $email->subject }}</td><td><span class="{{ $email->status==='failed'?'text-danger':'' }}">{{ ucfirst(str_replace('_',' ',$email->status)) }}</span><br><small>{{ $email->mailer }}</small><br><small>Attempts: {{ $email->attempts }} @if($email->available_at && in_array($email->status,['queued','retry'])) · Next: {{ $email->available_at }} @endif</small><br><small>{{ $email->delivery_note }}</small></td><td>{{ $email->sent_at ?? '—' }}</td><td style="overflow-wrap:anywhere">{{ $email->message_id ?? '—' }}</td><td>@if($email->archived_at)<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.email.history.show',$email) }}">View Email</a>@else<span class="text-muted">Not retained yet</span>@endif</td></tr>@empty<tr><td colspan="7">No email attempts recorded yet.</td></tr>@endforelse
</tbody></table></div>{{ $emails->links() }}</div>
@endsection
