@extends('admin.layout')
@section('title','Email / SMTP Configuration')
@section('content')
<p><a class="btn btn-outline-primary" href="{{ route('admin.email.history') }}">Email History</a></p>
<div class="panel panel-content" style="max-width:1000px">
<h2>Outgoing email</h2>
<p>Save and enable SMTP to send new verification emails, POS account invitations and password-reset emails through your provider.</p>
<div class="alert {{ $settings->enabled?'alert-success':'alert-warning' }}" role="status">{{ $settings->enabled?'SMTP is enabled. Use the test below to confirm delivery.':'SMTP is not enabled here. Save valid credentials and enable SMTP to send email.' }}</div>
<div class="alert {{ $settings->ready()?'alert-success':'alert-warning' }}">
@if($settings->ready())SMTP test passed at {{ $settings->tested_at->format('d M Y H:i') }}. New customers must verify their email. SMTP acceptance does not guarantee inbox placement.
@else SMTP has not passed a test for the current settings. New Individual customers use the temporary auto-verification fallback when enabled; Business approval remains required. Save changes and send a successful test to enable email verification.
@endif</div>
<p>Saving SMTP settings clears the previous test confirmation. Existing customers are not changed.</p>
<form method="post" action="{{ route('admin.email.update') }}" autocomplete="off">@csrf @method('PUT')
<input type="hidden" name="revision" value="{{ $settings->revision }}">
<input type="hidden" name="enabled" value="0">
<label class="mb-4"><input type="checkbox" name="enabled" value="1" @checked(old('enabled',$settings->enabled))> Enable SMTP email delivery</label>
<div class="row">
@foreach(['host'=>['SMTP host','smtp.gmail.com'],'port'=>['SMTP port','587'],'username'=>['SMTP username','info@mammamiacucina.ca'],'from_address'=>['Sender email','info@mammamiacucina.ca'],'from_name'=>['Sender name','Mamma Mia Cucina']] as $field=>$details)
<div class="col-md-6 mb-4"><label for="smtp-{{ $field }}">{{ $details[0] }}</label><input class="form-control" id="smtp-{{ $field }}" name="{{ $field }}" type="{{ $field==='port'?'number':($field==='from_address'?'email':'text') }}" value="{{ old($field,$settings->$field) }}" placeholder="{{ $details[1] }}" required @if($field==='port') min="1" max="65535" @else maxlength="255" @endif></div>
@endforeach
<div class="col-md-6 mb-4"><label for="smtp-encryption">Encryption</label><select class="form-control" id="smtp-encryption" name="encryption"><option value="tls" @selected(old('encryption',$settings->encryption)==='tls')>STARTTLS / TLS (Google: port 587)</option><option value="ssl" @selected(old('encryption',$settings->encryption)==='ssl')>SSL / TLS (port 465)</option></select></div>
<div class="col-12 mb-4"><label for="smtp-password">SMTP password / Google app password</label><input class="form-control" id="smtp-password" name="password" type="password" autocomplete="new-password" maxlength="1024" value="" aria-describedby="smtp-password-help"><small id="smtp-password-help" class="d-block mt-2">{{ $settings->password?'A password is saved. Leave blank to keep it; enter a new one to replace it.':'For Google Workspace, enter the 16-character app password, not your normal Google password.' }} The saved password is encrypted and is never displayed.</small></div>
</div>
<p>Google Workspace: enable 2-Step Verification for the sending mailbox, then create an app password named “Mamma Mia Website”. <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener noreferrer">Open Google App Passwords</a>.</p>
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
