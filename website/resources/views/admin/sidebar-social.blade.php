@foreach($siteSettings?->contactLinks(true) ?? [] as $link)
<li><a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer">@include('partials.contact-link-icon')<span>{{ $link['label'] }}</span></a></li>
@endforeach
