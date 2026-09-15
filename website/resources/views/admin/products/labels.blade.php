<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Product barcode labels | Mamma Mia Cucina</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f5f0e7;color:#352719;font:14px Arial,sans-serif}.label-toolbar{max-width:900px;margin:24px auto;padding:20px;display:flex;flex-wrap:wrap;align-items:center;gap:16px;background:#fff;border:1px solid #d9bd8d;border-radius:12px}.label-toolbar p{flex-basis:100%;margin:0;color:#665b4c}.label-toolbar button{background:#920b1f;color:white;border:0;border-radius:6px;padding:12px 18px;cursor:pointer}.label-toolbar input{width:65px;padding:10px;border:1px solid #cdbb9c}.labels{max-width:190mm;margin:25px auto;display:grid;grid-template-columns:repeat(3,1fr);gap:3mm}.product-label{background:#fff;color:#000;padding:3mm;border:1px dashed #bbb;min-width:0;min-height:35mm;text-align:center;break-inside:avoid;display:flex;flex-direction:column;align-items:center;justify-content:center}.product-label small{font-size:9px;letter-spacing:1px}.product-label strong{font-size:11px;line-height:1.3;min-height:27px;margin-top:3px;overflow-wrap:anywhere}.product-label svg{width:100%;height:auto;max-height:21mm}.barcode-number{font:11px monospace;letter-spacing:.5px;margin-top:2px}.barcode-failed{color:#a00000}@page{size:A4;margin:10mm}@media print{body{background:#fff}.label-toolbar{display:none}.labels{margin:0;max-width:none}.product-label{border-color:transparent}}@media screen and (max-width:600px){.labels{grid-template-columns:repeat(2,1fr);margin:15px}.label-toolbar{margin:15px}}
</style></head><body>
<form class="label-toolbar" method="get">
@foreach($products as $product)<input type="hidden" name="product_ids[]" value="{{ $product->id }}">@endforeach
<label>Copies per product <input type="number" min="1" max="30" name="copies" value="{{ $copies }}" required></label>
<button type="submit">Update labels</button><button type="button" id="print-labels" disabled>Print / Save as PDF</button>
<p>A4 label sheet · Print at 100% scale, with browser headers and footers off. Each label includes the product name and barcode number.</p>
<p id="label-status" role="status">Preparing barcodes…</p>
</form>
<main class="labels">@foreach($products as $product)@for($i=0;$i<$copies;$i++)
<article class="product-label"><small>MAMMA MIA CUCINA</small><strong>{{ $product->premium_marketing_name }}</strong><svg class="product-barcode" data-code="{{ $product->barcode_number }}" role="img" aria-label="Barcode {{ $product->barcode_number }}"></svg><span class="barcode-number">{{ $product->barcode_number }}</span><noscript>{{ $product->barcode_number }} — enable JavaScript to generate the barcode.</noscript></article>
@endfor @endforeach</main>
<script src="{{ asset('admin-assets/scanner-libs/JsBarcode.code128-3.11.6.min.js') }}"></script>
<script>
try {
    document.querySelectorAll('.product-barcode').forEach(svg => JsBarcode(svg, svg.dataset.code, {format:'CODE128',width:2,height:48,margin:16,displayValue:false,fontSize:14,textMargin:6}));
    document.querySelector('#label-status').textContent = 'Labels ready.';
    document.querySelector('#print-labels').disabled = false;
    document.querySelector('#print-labels').onclick = () => window.print();
} catch (error) {
    document.querySelector('#label-status').textContent = 'Barcodes could not load. Refresh this page before printing.';
    document.querySelector('#label-status').classList.add('barcode-failed');
}
</script></body></html>