<div class="mmc-contact-details">
@if($siteSettings?->email)<p><a href="mailto:{{ $siteSettings->email }}"><i class="fa fa-envelope" aria-hidden="true"></i> {{ $siteSettings->email }}</a></p>@endif
@if($siteSettings?->phone)<p><a href="tel:{{ preg_replace('/[^0-9+]/','',$siteSettings->phone) }}"><i class="fa fa-phone" aria-hidden="true"></i> {{ $siteSettings->phone }}</a></p>@endif
@if($siteSettings?->whatsapp_number)<p><a href="https://wa.me/{{ preg_replace('/\D/','',$siteSettings->whatsapp_number) }}" target="_blank" rel="noopener noreferrer"><i class="fa fa-whatsapp" aria-hidden="true"></i> WhatsApp {{ $siteSettings->whatsapp_number }}</a></p>@endif
@if($siteSettings?->website_url)<p><a href="{{ $siteSettings->website_url }}" target="_blank" rel="noopener noreferrer"><i class="fa fa-globe" aria-hidden="true"></i> {{ preg_replace('~^https?://~','',$siteSettings->website_url) }}</a></p>@endif
@if($siteSettings?->facebook_url || $siteSettings?->instagram_url || $siteSettings?->twitter_url)
<div class="mmc-contact-socials">
@foreach(['instagram_url'=>['Instagram','instagram'],'facebook_url'=>['Facebook','facebook'],'twitter_url'=>['Twitter / X','twitter']] as $field=>$social)
@if($siteSettings?->$field)<a href="{{ $siteSettings->$field }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $social[0] }}"><i class="fa fa-{{ $social[1] }}" aria-hidden="true"></i> {{ $social[0] }}</a>@endif
@endforeach
</div>
@endif
</div>
