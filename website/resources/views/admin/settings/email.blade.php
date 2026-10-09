@extends('admin.layout')
@section('title','Email Delivery Configuration')
@section('content')
<p><a class="btn btn-outline-primary" href="{{ route('admin.email.history') }}">Email History</a></p>
<div class="panel panel-content" style="max-width:1000px">
<h2>Outgoing email</h2>
<p>Save and enable email delivery to send new verification emails, POS account invitations and password-reset emails through your provider.</p>
<div class="alert {{ $settings->enabled?'alert-success':'alert-warning' }}" role="status">{{ $settings->enabled?'Email delivery is enabled. Use the test below to confirm delivery.':'Email delivery is not enabled here. Save valid credentials and enable delivery to send email.' }}</div>
<div class="alert {{ $settings->ready()?'alert-success':'alert-warning' }}">
@if($settings->ready())Email test passed at {{ $settings->tested_at->format('d M Y H:i') }}. New customers must verify their email. Provider acceptance does not guarantee inbox placement.
@else Email delivery has not passed a test for the current settings. New Individual customers use the temporary auto-verification fallback when enabled; Business approval remains required. Save changes and send a successful test to enable email verification.
@endif</div>
<p>Saving email settings clears the previous test confirmation. Existing customers are not changed.</p>
<form method="post" action="{{ route('admin.email.update') }}" autocomplete="off">@csrf @method('PUT')
<input type="hidden" name="revision" value="{{ $settings->revision }}">
<input type="hidden" name="enabled" value="0">
<label class="mb-4"><input type="checkbox" name="enabled" value="1" @checked(old('enabled',$settings->enabled))> Enable email delivery</label>
<div class="form-group"><label for="delivery-method">Delivery method</label><select class="form-control" id="delivery-method" name="delivery_method"><option value="smtp" @selected(old('delivery_method',$settings->delivery_method??'smtp')==='smtp')>SMTP — Google Workspace or other mail server</option><option value="resend" @selected(old('delivery_method',$settings->delivery_method)==='resend')>Resend API — HTTPS (port 443)</option></select></div>
<div class="row">
@foreach(['from_address'=>'Sender email','from_name'=>'Sender name'] as $field=>$label)
<div class="col-md-6 form-group"><label>{{ $label }}<input class="form-control" name="{{ $field }}" value="{{ old($field,$settings->$field) }}" type="{{ $field==='from_address'?'email':'text' }}" maxlength="255" required></label></div>
@endforeach
</div>
<fieldset data-method="resend"><div class="alert alert-info">Keep receiving mail in Google Workspace. Resend sends website emails over HTTPS instead of SMTP ports 587/465. Create a Resend account, verify your own sender domain using its DNS records, and create a sending API key. Keep your existing Google Workspace MX records. You cannot use an unverified gmail.com sender for customer delivery.</div>
<div class="form-group"><label for="email-api-key">Resend API key</label><input class="form-control" id="email-api-key" type="password" name="api_key" autocomplete="new-password" maxlength="1024" value=""><small>{{ $settings->api_key?'An encrypted API key is saved. Leave blank to keep it.':'Enter your Resend sending API key.' }} It is never displayed.</small></div><p><a href="https://resend.com/domains" target="_blank" rel="noopener">Verify sender domain</a> · <a href="https://resend.com/api-keys" target="_blank" rel="noopener">Create API key</a></p>
</fieldset>
<fieldset data-method="smtp"><div class="row">
@foreach(['host'=>['SMTP host','smtp.gmail.com'],'port'=>['SMTP port','587'],'username'=>['SMTP username','info@mammamiacucina.ca']] as $field=>$details)
<div class="col-md-6 mb-4"><label for="smtp-{{ $field }}">{{ $details[0] }}</label><input class="form-control" id="smtp-{{ $field }}" name="{{ $field }}" type="{{ $field==='port'?'number':'text' }}" value="{{ old($field,$settings->$field) }}" placeholder="{{ $details[1] }}" required></div>
@endforeach
<div class="col-md-6 mb-4"><label for="smtp-encryption">Encryption</label><select class="form-control" id="smtp-encryption" name="encryption"><option value="tls" @selected(old('encryption',$settings->encryption)==='tls')>STARTTLS / TLS (Google: port 587)</option><option value="ssl" @selected(old('encryption',$settings->encryption)==='ssl')>SSL / TLS (port 465)</option></select></div>
<div class="col-12 mb-4"><label for="smtp-password">SMTP password / Google app password</label><input class="form-control" id="smtp-password" name="password" type="password" autocomplete="new-password" maxlength="1024" value=""><small>{{ $settings->password?'A password is saved. Leave blank to keep it; enter a new one to replace it.':'Enter the mailbox app password.' }} The saved password is encrypted and is never displayed.</small></div>
</div></fieldset>
<script>(()=>{const s=document.getElementById('delivery-method');const update=()=>document.querySelectorAll('[data-method]').forEach(f=>{f.hidden=f.dataset.method!==s.value;f.disabled=f.hidden;});s.addEventListener('change',update);update();})();</script>
<button class="btn btn-primary" type="submit">Save Email Settings</button>
<a class="btn btn-outline-primary ml-2" href="{{ route('admin.settings.general') }}">General Settings</a>
</form>
</div>
<div class="panel panel-content" style="max-width:1000px">
<h2>Send a test email</h2><p>This sends using the saved settings above. Save changes first, then enter an inbox you can check.</p>
<form method="post" action="{{ route('admin.email.test') }}">@csrf
<div class="form-group"><label for="smtp-recipient">Recipient email</label><input class="form-control" id="smtp-recipient" type="email" name="recipient" value="{{ old('recipient') }}" required maxlength="255" placeholder="Your email address"></div>
<button class="btn btn-outline-primary" type="submit" @disabled(!$settings->enabled)>Send Test Email</button>
</form>
</div>
@endsection
