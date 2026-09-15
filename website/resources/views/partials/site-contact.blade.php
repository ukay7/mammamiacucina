@if($siteSettings?->email)<p><a href="mailto:{{ $siteSettings->email }}">{{ $siteSettings->email }}</a></p>@endif
@if($siteSettings?->phone)<p><a href="tel:{{ preg_replace('/[^0-9+]/','',$siteSettings->phone) }}">{{ $siteSettings->phone }}</a></p>@endif
