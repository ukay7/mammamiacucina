<table class="doc-header"><tr>
<td class="doc-logo-cell">@if($doc['brand']['logo'])<img class="doc-logo" src="{{ $doc['brand']['logo'] }}" alt="Mamma Mia Cucina logo">@endif</td>
<td class="doc-company-details">
    <div class="doc-company">{{ $doc['brand']['name'] }}</div>
    @if(filled($doc['brand']['phone']??null) || filled($doc['brand']['email']??null))
    {{-- Printed documents and sandboxed previews cannot run Cloudflare's email decoder. Keep these HTML comments. --}}
    <div class="doc-contact-line">Contact Us: @if(filled($doc['brand']['phone']??null))<span>{{ $doc['brand']['phone'] }}</span>@endif @if(filled($doc['brand']['phone']??null) && filled($doc['brand']['email']??null))<span class="doc-contact-separator"> | </span>@endif @if(filled($doc['brand']['email']??null))<span><!--email_off-->{{ $doc['brand']['email'] }}<!--/email_off--></span>@endif</div>
    @endif
    @if(filled($doc['brand']['mailing_address']??null))<div class="doc-contact-line doc-address">Mailing Address: {{ $doc['brand']['mailing_address'] }}</div>@endif
    @if(filled($doc['brand']['warehouse_address']??$doc['brand']['address']??null))<div class="doc-contact-line doc-address">Warehouse: {{ $doc['brand']['warehouse_address']??$doc['brand']['address'] }}</div>@endif
</td>
<td class="doc-meta">
    <strong>{{ mb_strtoupper((string)$doc['title']) }}</strong>
    @if(filled($doc['number']??null))<div class="doc-reference">{{ mb_strtoupper((string)$doc['number']) }}</div>@endif
    <div class="doc-date">{{ app(\App\Services\DocumentTheme::class)->dateLabel($doc['date']) }}</div>
</td>
</tr></table>
