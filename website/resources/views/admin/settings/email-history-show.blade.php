@extends('admin.layout')
@section('title','Email details')
@section('content')
<a href="{{ route('admin.email.history') }}" class="btn btn-outline-primary mb-3">← Email History</a>
<div class="panel panel-content">
<h2>{{ $email->subject }}</h2>
<p><strong>To:</strong> {{ $email->recipient }}<br>
<strong>Status:</strong> {{ ucfirst(str_replace('_',' ',$email->status)) }}<br>
<strong>Sent at:</strong> {{ $email->sent_at ?? 'Not confirmed' }} ({{ config('app.timezone') }})<br>
<strong>Message ID:</strong> <span style="overflow-wrap:anywhere">{{ $email->message_id ?? '—' }}</span></p>
<p class="text-muted">This is the saved message for this attempt. Accepted means the provider accepted it; inbox delivery is not confirmed. Failed, queued or logged-only entries are not proof of sending.</p>
@if($archive)
@if($archive['redacted'] ?? false)<div class="alert alert-info">Private verification and password-reset links are hidden in this saved copy. Sending uses the original links.</div>@endif
<div class="border rounded p-3" style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $archive['body'] }}</div>
@if(!empty($archive['attachment']))
<a href="{{ route('admin.email.history.attachment',$email) }}" class="btn btn-primary mt-3">Download attached receipt (PDF)</a>
@endif
@else
<div class="alert alert-info">Content is unavailable. Older messages were not retained; queued messages are saved when a delivery attempt begins.</div>
@endif
</div>
@endsection
