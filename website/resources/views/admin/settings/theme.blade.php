@extends('admin.layout')
@section('title','Theme Settings')
@section('content')
<style>
.theme-intro{padding:28px;border-radius:12px;background:linear-gradient(120deg,#20180e,#58432a);color:#fff4df;margin-bottom:24px}.theme-intro h2{color:#fff4df}.theme-columns{display:grid;grid-template-columns:1fr 1fr;gap:24px}.theme-fields{display:grid;grid-template-columns:1fr 1fr;gap:14px}.theme-input{display:flex;gap:8px}.theme-input input[type=color]{width:48px;min-width:48px;height:38px;border:0;padding:0;background:none}.theme-preview{margin-top:24px;border:1px solid #ccb891;border-radius:12px;overflow:hidden;background:var(--p-background);color:var(--p-text)}.theme-preview header,.theme-preview footer{padding:18px;background:var(--p-header);color:var(--p-nav_text)}.theme-preview footer{background:var(--p-footer)}.theme-preview main{padding:22px}.theme-preview article{background:var(--p-surface);padding:18px;border:1px solid var(--p-accent);border-radius:8px}.theme-preview h3{color:var(--p-heading)}.theme-preview a{color:var(--p-link)}.theme-preview button{background:var(--p-primary);color:var(--p-button_text);border:1px solid var(--p-accent);border-radius:24px;padding:10px 24px}.theme-preview button:hover{background:var(--p-hover)}.theme-warning{padding:12px;margin-top:12px;background:#fff0cc;color:#653c00}.theme-actions{display:flex;gap:12px;flex-wrap:wrap;margin:24px 0}@media(max-width:900px){.theme-columns{grid-template-columns:1fr}}
</style>
<div class="theme-intro"><h2>Make Mamma Mia your own</h2><p>Choose website and administration colours independently. Preview changes below, then save when you are happy.</p></div>
<form method="post" action="{{ route('admin.theme.update') }}" id="theme-form">@csrf @method('PUT')
<input type="hidden" name="revision" value="{{ $theme->revision }}">
<div class="theme-columns">
@foreach(['website'=>'Website','admin'=>'Admin portal'] as $scope=>$title)
<section class="panel panel-content" data-theme-scope="{{ $scope }}"><h2>{{ $title }}</h2>
<label>Start with a palette <select class="form-control mb-4" data-preset><option value="">Custom / current</option><option value="classic">Classic Mamma Mia</option><option value="forest">Italian forest</option><option value="navy">Midnight blue</option><option value="terracotta">Warm terracotta</option></select></label>
<div class="theme-fields">
@foreach(config('theme_colors.colors') as $key=>$default)
<label>{{ ucwords(str_replace('_',' ',$key)) }}<span class="theme-input"><input type="color" aria-label="{{ $title.' '.$key.' colour picker' }}" data-picker="{{ $key }}" value="{{ old($scope.'.'.$key,$theme->colors($scope)[$key]) }}"><input class="form-control" data-color="{{ $key }}" name="{{ $scope }}[{{ $key }}]" value="{{ old($scope.'.'.$key,$theme->colors($scope)[$key]) }}" pattern="#[0-9a-fA-F]{6}" maxlength="7" required aria-label="{{ $title.' '.$key.' HEX code' }}"></span></label>
@endforeach
</div>
<div class="theme-preview"><header>MAMMA MIA CUCINA · {{ $title }}</header><main><article><h3>A taste of your new theme</h3><p>Readable text, welcoming colours, and a style that feels like you.</p><p><a href="#" onclick="return false">Sample link</a></p><button type="button">Sample button</button></article></main><footer>Footer · Contact us</footer></div>
<div data-contrast role="status" class="theme-warning"></div>
</section>
@endforeach
</div>
<div class="theme-actions"><button class="btn btn-primary" name="action" value="save">Save Theme</button><button class="btn btn-outline-primary" name="action" value="reset" formnovalidate data-confirm="Restore the original website and admin colours?">Reset to Original</button><a class="btn btn-outline-primary" href="{{ route('theme.index') }}" target="_blank" rel="noopener">View website</a></div>
<p><strong>Theme notes:</strong> Saving updates shared website and admin colours. Images, logos, banner artwork, printed documents and status colours keep their own colours. Preview is a sample; check the website after saving. Low contrast warnings help you keep text readable. Reset restores the original design. Unsaved changes affect only these previews.</p>
</form>
<script>window.mmcThemeDefaults=@json(config('theme_colors.colors'));</script>
<script src="{{ asset('admin-assets/theme-settings.js') }}?v={{ filemtime(public_path('admin-assets/theme-settings.js')) }}"></script>
@endsection
