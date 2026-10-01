@php($approvalSettings=\App\Models\GeneralSetting::find(1))
@php($approvalEmail=$approvalSettings?->email ?: 'info@mammamiacucina.ca')
<div style="margin:20px 0">
<p><strong>Email:</strong> <a href="mailto:{{ $approvalEmail }}">{{ $approvalEmail }}</a></p>
@if($approvalSettings?->phone)<p><strong>Phone:</strong> <a href="tel:{{ preg_replace('/[^0-9+]/','',$approvalSettings->phone) }}">{{ $approvalSettings->phone }}</a></p>@endif
</div>
