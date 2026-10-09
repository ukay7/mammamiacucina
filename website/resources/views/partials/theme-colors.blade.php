@if($themeSettings && $themeSettings->$themeScope)
<style id="mmc-custom-theme">
:root { @foreach($themeSettings->colors($themeScope) as $key=>$value) --theme-{{ $key }}: {{ $value }}; @endforeach }
</style>
<link rel="stylesheet" href="{{ asset('assets/css/theme-'.$themeScope.'.css') }}?v={{ filemtime(public_path('assets/css/theme-'.$themeScope.'.css')) }}">
@endif
