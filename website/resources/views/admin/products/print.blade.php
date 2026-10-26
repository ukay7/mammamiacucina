<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Product Catalogue Export</title>
@include('documents.style')
<style>
@page{size:A4 landscape;margin:10mm}.document,.doc-toolbar{max-width:277mm}.doc-toolbar{flex-wrap:wrap;padding:0 12px}.doc-lines{table-layout:auto;font-size:10px}.doc-lines th:nth-child(n){width:auto}.doc-lines td{overflow-wrap:anywhere;max-width:260px}.doc-lines td.catalogue-nowrap{white-space:nowrap}.catalogue-image-cell{width:78px;text-align:center}.catalogue-product-image{display:block;width:64px;height:64px;object-fit:contain;margin:auto}.catalogue-product-image[hidden]{display:none}.catalogue-missing-image{font-size:9px;color:#76634c}.catalogue-scroll{overflow-x:auto}.doc-toolbar button:disabled{opacity:.65;cursor:wait}@media print{.catalogue-scroll{overflow:visible}.document{max-width:none}.doc-lines{font-size:9px}}
</style></head><body>
<div class="doc-toolbar"><button type="button" id="print-catalogue">{{ $pdf?'Save as PDF':'Print catalogue' }}</button><span id="catalogue-print-status" role="status">@if($pdf)Choose “Save as PDF” as the destination. @endif All products matching the selected filters are included.</span></div>
<main class="document">
@include('documents.header')
<table class="doc-info"><tr><td><h2>Product catalogue</h2><div>All products matching your selected filters.</div></td><td><h2>Pricing</h2><div>Current published prices, as of {{ app(\App\Services\DocumentTheme::class)->dateLabel($doc['date']) }}.</div></td></tr></table>
<div class="catalogue-scroll"><table class="doc-lines"><thead><tr>@foreach($headers as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>
@forelse($rows as $row)<tr>@foreach($row as $index=>$cell)
<td @class(['catalogue-image-cell'=>$selectedColumns[$index]==='image','catalogue-nowrap'=>in_array($selectedColumns[$index],['status','qr_code','product_code','published_price','business_price'])])>
@if($selectedColumns[$index]==='image')
    @if($cell)<img class="catalogue-product-image" src="{{ $cell }}" alt="Product image" width="64" height="64" loading="eager" decoding="async"><span class="catalogue-missing-image" hidden>Image unavailable</span>@else<span class="catalogue-missing-image">No image</span>@endif
@else{{ \App\Support\NumberDisplay::field($cell,$selectedColumns[$index]) }}@endif
</td>@endforeach</tr>@empty<tr><td colspan="{{ count($headers) }}">No products match the selected filters.</td></tr>@endforelse
</tbody></table></div>
<footer class="doc-footer"><strong>Thank you for choosing {{ $doc['brand']['name'] }}.</strong><p>Product details and published prices shown above reflect this export. This catalogue is not an invoice or proof of payment.</p></footer>
</main>
<script src="{{ asset('admin-assets/product-print.js') }}?v={{ filemtime(public_path('admin-assets/product-print.js')) }}"></script>
</body></html>
