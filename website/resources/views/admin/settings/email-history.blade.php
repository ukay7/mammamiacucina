@extends('admin.layout')
@section('title','Email History')
@section('content')
<div class="panel panel-content">
<h2>Email History</h2>
<p>Accepted means the sending server accepted the message, not confirmed inbox delivery. Logged only means no external email was sent. Times use {{ config('app.timezone') }}.</p>
<p>Verification and reset links contain private tokens and are not stored in this history. Records begin when this feature is installed.</p>
<a href="{{ route('admin.email.edit') }}" class="btn btn-outline-primary">SMTP configuration</a>
<form method="get" class="my-3"><label for="email-search">Search recipient</label><input id="email-search" class="form-control" name="search" value="{{ $search }}" maxlength="255"><button class="btn btn-primary mt-2">Search</button></form>
<div class="table-responsive"><table class="table"><thead><tr><th>Attempted at</th><th>Recipient</th><th>Email / subject</th><th>Status</th><th>Sent at</th><th>Message ID</th></tr></thead><tbody>
@forelse($emails as $email)<tr><td>{{ $email->created_at }}</td><td>{{ $email->recipient }}</td><td>{{ str_replace('_',' ',$email->type) }}<br>{{ $email->subject }}</td><td><span class="{{ $email->status==='failed'?'text-danger':'' }}">{{ ucfirst(str_replace('_',' ',$email->status)) }}</span><br><small>{{ $email->mailer }}</small></td><td>{{ $email->sent_at ?? '—' }}</td><td style="overflow-wrap:anywhere">{{ $email->message_id ?? '—' }}</td></tr>@empty<tr><td colspan="6">No email attempts recorded yet.</td></tr>@endforelse
</tbody></table></div>{{ $emails->links() }}</div>
@endsection
