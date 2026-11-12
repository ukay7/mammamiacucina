@if(filled($doc['brand']['tax_number']??null))
<div class="doc-registration">GST/HST: {{ $doc['brand']['tax_number'] }}</div>
@endif
