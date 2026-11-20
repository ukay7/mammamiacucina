@extends('admin.layout')
@section('title','Recipient Consent & Email History')
@section('content')
@include('admin.notifications.style')
@php($canManage=auth()->user()->hasAdminPermission('notifications.consent'))
<div class="nt-heading"><div class="nt-recipient-title"><h2>{{ $subscription->address }}</h2><span class="nt-badge" data-state="{{ $subscription->status }}">{{ $subscription->status_label }}</span>
@if($subscription->unsubscribed_at)<p class="nt-muted mt-2">Last unsubscribe: {{ \App\Support\BusinessTime::format($subscription->unsubscribed_at,'d M Y H:i:s T') }}</p>@endif</div><a class="btn btn-outline-primary" href="{{ route('admin.notifications.recipients') }}">All recipients &amp; consent</a></div>
@if($subscription->status==='unsubscribed')<div class="alert alert-warning">Promotional emails, including test sends, are blocked. Only a fresh consent record can lift the unsubscribe. Order receipts and password resets continue.</div>@endif
@if($subscription->suppression && $subscription->suppression->reason!=='unsubscribed')<div class="alert alert-warning">Provider block: <strong>{{ $subscription->suppression->reason }}</strong>. Recording consent will not clear a bounce, complaint or provider block. Resolve it with the email provider first.</div>@endif
<div class="nt-consent-layout"><div>
<section class="panel nt-panel"><h2>Consent &amp; unsubscribe history</h2><p class="nt-muted">Every change is retained. Evidence and uploaded files can be viewed by staff with permission to manage email consent.</p>
<ol class="nt-consent-history">
@forelse($history as $event)
<li><div class="nt-heading"><strong>{{ ['unsubscribed'=>'Unsubscribed','resubscribed'=>'Resubscribed with consent','consent_recorded'=>'Consent recorded'][$event->action] ?? $event->action }}</strong><small>{{ \App\Support\BusinessTime::format($event->created_at,'d M Y H:i:s T') }}</small></div>
<p class="nt-muted">{{ \App\Services\Notifications\Consents::SOURCES[$event->source] ?? ['email_link'=>'Recipient used email unsubscribe link','admin'=>'Admin recorded unsubscribe request','legacy'=>'Preserved from earlier email records'][$event->source] ?? $event->source }}@if($event->actor_name) · Recorded by {{ $event->actor_name }}@endif</p>
@if($event->consented_at)<p>Consent received: <strong>{{ \App\Support\BusinessTime::format($event->consented_at,'d M Y H:i:s T') }}</strong></p>@endif
@if($event->delivery)<p>Related email: @if(!$event->delivery->campaign?->trashed())<a href="{{ route('admin.notifications.delivery',[$event->delivery->campaign_id,$event->delivery]) }}">{{ $event->delivery->campaign?->number }} · {{ $event->delivery->campaign?->subject }}</a>@else{{ $event->delivery->campaign?->number }} · {{ $event->delivery->campaign?->subject }} (archived)@endif</p>@endif
@if($canManage && $event->evidence)<details><summary>View evidence / request</summary><div class="nt-message mt-2">{{ $event->evidence }}</div></details>@endif
@if($canManage && $event->files)<ul class="nt-files">@foreach($event->files as $file)<li><div class="nt-file-info"><a href="{{ route('admin.notifications.recipient.evidence',[$subscription,$event,$file['id']]) }}">{{ $file['name'] }}</a><small>{{ number_format($file['size']/1024,1) }} KB · Evidence</small></div></li>@endforeach</ul>@endif
</li>
@empty<li>No consent has been recorded here. Do not treat this as proof of permission to send marketing.</li>@endforelse
</ol>{{ $history->links() }}</section>
<section class="panel nt-panel"><h2>Email history</h2><div class="table-responsive"><table class="table nt-table"><thead><tr><th>Notification</th><th>Accepted</th><th>Status / outcome</th><th></th></tr></thead><tbody>
@forelse($deliveries as $delivery)<tr><td><strong>{{ $delivery->campaign?->number }}</strong>@if($delivery->is_test)<span class="nt-badge">Test</span>@endif<small>{{ $delivery->campaign?->subject }}</small></td><td>{{ \App\Support\BusinessTime::format($delivery->accepted_at) ?: '—' }}</td><td>{{ ucfirst(str_replace('_',' ',$delivery->status)) }}<small>{{ $delivery->outcome ?: 'No delivery confirmation' }}</small>@if($delivery->note)<small>{{ $delivery->note }}</small>@endif</td><td>@if(!$delivery->campaign?->trashed())<a href="{{ route('admin.notifications.delivery',[$delivery->campaign_id,$delivery]) }}">Timeline</a>@else Archived @endif</td></tr>
@empty<tr><td colspan="4">No email deliveries recorded for this address.</td></tr>@endforelse
</tbody></table></div>{{ $deliveries->links() }}</section>
</div>
@if($canManage)<aside>
<section class="panel nt-panel"><h2>{{ $subscription->status==='unsubscribed'?'Record consent & resubscribe':'Record explicit consent' }}</h2><p class="nt-muted">Use a request initiated by this recipient. Save their actual words or describe the evidence, and attach supporting files if available.</p>
<form method="post" enctype="multipart/form-data" action="{{ route('admin.notifications.recipient.consent',$subscription) }}" data-confirm="Record this recipient’s explicit consent? Old queued emails will not restart.">@csrf
<input type="hidden" name="version" value="{{ $subscription->version }}"><input type="hidden" name="request_key" value="{{ old('request_key',(string)Str::uuid()) }}">
<label>Consent source<select class="form-control" name="source" required>@foreach(\App\Services\Notifications\Consents::SOURCES as $value=>$label)<option value="{{ $value }}" @selected(old('source')===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Consent received (Toronto time)<input class="form-control" type="datetime-local" step="1" name="consented_at" value="{{ old('consented_at',\App\Support\BusinessTime::now()->format('Y-m-d\TH:i:s')) }}" required></label>
<label>Evidence / recipient’s request<textarea class="form-control" name="evidence" rows="6" minlength="20" maxlength="10000" required placeholder="Paste their request, including who sent it and where it was received.">{{ old('evidence') }}</textarea></label>
<label>Supporting evidence files (optional)<input class="form-control nt-evidence-input" type="file" name="evidence_files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"></label><p class="nt-muted">Up to 5 files, 10 MB combined. PDF, images, Word or Excel. Evidence stays private and is never attached to campaign emails. Choose files again if saving fails.</p>
<label class="nt-check"><input type="checkbox" name="confirmed" value="1" required> I checked the evidence. This recipient explicitly requested promotional emails, after any previous unsubscribe.</label>
<button type="submit" class="btn btn-primary">{{ $subscription->status==='unsubscribed'?'Save evidence & resubscribe':'Save consent evidence' }}</button>
</form></section>
@if($subscription->status!=='unsubscribed')<section class="panel nt-panel"><h2>Record an unsubscribe</h2><p class="nt-muted">Use this when the person asks to stop by email, phone or another channel.</p><form method="post" action="{{ route('admin.notifications.recipient.unsubscribe',$subscription) }}" data-confirm="Unsubscribe this address and stop its pending promotional emails?">@csrf<input type="hidden" name="version" value="{{ $subscription->version }}"><label>Request details<textarea class="form-control" name="notes" rows="3" minlength="10" maxlength="10000" required placeholder="How and when did the recipient ask to stop?">{{ old('notes') }}</textarea></label><label class="nt-check"><input type="checkbox" name="confirmed" value="1" required> Record this unsubscribe request.</label><button class="btn btn-outline-danger">Unsubscribe address</button></form></section>@endif
</aside>@endif
</div>
@endsection
