<div class="mmc-contact-details">
@if($siteSettings?->email && ($siteSettings->show_email ?? true))<p><a href="mailto:{{ $siteSettings->email }}"><i class="fa fa-envelope" aria-hidden="true"></i> {{ $siteSettings->email }}</a></p>@endif
@if($siteSettings?->phone && ($siteSettings->show_phone ?? true))<p><a href="tel:{{ preg_replace('/[^0-9+]/','',$siteSettings->phone) }}"><i class="fa fa-phone" aria-hidden="true"></i> {{ $siteSettings->phone }}</a></p>@endif
@foreach($siteSettings?->contactLinks(true) ?? [] as $link)
<p><a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer">@include('partials.contact-link-icon') {{ $link['label'] }}</a></p>
@endforeach
</div>
