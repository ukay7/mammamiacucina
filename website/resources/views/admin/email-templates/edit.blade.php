@extends('admin.layout')
@section('title',$definition['label'].' template')
@section('content')
<a class="btn btn-outline-primary mb-3" href="{{ route('admin.email.templates.index') }}">All email templates</a>
<form class="panel panel-content" method="post" action="{{ route('admin.email.templates.update',$template->key) }}">@csrf @method('PUT')
<input type="hidden" name="revision" value="{{ $template->revision }}">
<p>Available placeholders: @foreach($definition['tokens'] as $token)<code>&#123;&#123;{{ $token }}&#125;&#125;</code> @endforeach</p>
@if($definition['required'])<p>Keep <code>&#123;&#123;{{ $definition['required'] }}&#125;&#125;</code> in the message. Actual links are generated securely when sending.</p>@endif
<div class="form-group"><label for="email-subject">Subject</label><input id="email-subject" class="form-control" name="subject" maxlength="255" value="{{ old('subject',$template->subject) }}" required></div>
<div class="form-group"><label for="email-body">Message (plain text)</label><textarea id="email-body" class="form-control" name="body" rows="14" maxlength="15000" required>{{ old('body',$template->body) }}</textarea></div>
<button class="btn btn-primary">Save template</button>
<h3 class="mt-4">Sample preview</h3><p>No email is sent by this preview.</p><strong id="preview-subject"></strong><pre id="preview-body" style="white-space:pre-wrap;background:#fff9ed;padding:20px;border:1px solid #dec8a5;margin-top:12px"></pre>
</form>
<script>
(()=>{const values={name:'Sample Customer',site_name:'Mamma Mia Cucina',expires_minutes:'60',verification_url:'https://example.test/verify/sample',reset_url:'https://example.test/reset/sample'};const render=s=>s.replace(/\{\{(\w+)\}\}/g,(match,key)=>values[key]??match);const update=()=>{document.getElementById('preview-subject').textContent=render(document.getElementById('email-subject').value);document.getElementById('preview-body').textContent=render(document.getElementById('email-body').value);};document.getElementById('email-subject').addEventListener('input',update);document.getElementById('email-body').addEventListener('input',update);update();})();
</script>
@endsection
