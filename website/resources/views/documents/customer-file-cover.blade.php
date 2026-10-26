<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Customer file #{{ $cover['id'] }}</title>
@include('documents.style')
@php
$nameLength = mb_strlen($cover['name']);
$nameSize = $nameLength > 180 ? 6.5 : ($nameLength > 120 ? 7.5 : ($nameLength > 80 ? 9 : ($nameLength > 45 ? 11 : ($nameLength > 30 ? 12 : 15))));
$headerSize = $nameLength > 100 ? 6 : ($nameLength > 55 ? 7.5 : 10);
$detailLength = mb_strlen(implode(' ', array_intersect_key($cover, array_flip(['contact', 'phone', 'email', 'address']))));
$detailSize = $detailLength > 400 ? 5 : ($detailLength > 250 ? 5.75 : 6.5);
$numberSize = strlen($cover['id']) > 12 ? 30 : (strlen($cover['id']) > 6 ? 40 : 54);
@endphp
<style>
@page{size:A4 portrait;margin:0}html,body{margin:0;padding:0;background:#fff;color:#080808;font-family:DejaVu Sans,Arial,sans-serif}
.file-cover{position:absolute;left:32mm;top:6mm;width:130mm;height:126mm}
.file-strip{border-collapse:collapse;table-layout:auto;width:130mm;border-top:.7mm solid #082d66;border-bottom:.7mm solid #082d66}
.file-strip td{padding:1.2mm 2.5mm;vertical-align:middle;font-weight:bold;word-wrap:break-word;line-height:1.15}
.file-strip .strip-name{color:#082d66;font-size:{{ $headerSize }}pt}
.file-strip .strip-number{background:#082d66;color:#fff;text-align:center;font-size:{{ strlen($cover['id']) > 7 ? 11 : 20 }}pt}
.file-body{position:absolute;left:0;top:14mm;width:130mm;height:112mm;border:.35mm solid #b98c45}
.file-rotated{position:absolute;left:0;top:111.3mm;width:111.3mm;height:129.3mm;transform:rotate(-90deg);transform-origin:0 0;text-align:center}
.file-brand{position:absolute;top:1mm;left:4mm;right:4mm;height:49mm}
.file-logo{display:block;width:35mm;height:35mm;margin:0 auto}
.file-company{font-family:DejaVu Serif,serif;font-size:17pt;font-weight:bold;line-height:1.05}
.file-company-subtitle{color:#b98c45;font-family:DejaVu Serif,serif;font-size:10pt;letter-spacing:3pt;line-height:1.25}
.file-rule{position:absolute;left:4mm;right:4mm;top:51mm;border-top:.2mm solid #b98c45}
.file-kind{position:absolute;top:55mm;left:4mm;right:4mm;font-family:DejaVu Serif,serif;font-weight:bold;font-size:9pt;letter-spacing:1.3pt;color:#257149}
.file-id-label{position:absolute;top:60.5mm;left:4mm;right:4mm;font-size:5.5pt;letter-spacing:1pt}
.file-number{position:absolute;top:63mm;left:4mm;right:4mm;font-family:DejaVu Serif,serif;font-weight:bold;font-size:{{ $numberSize }}pt;line-height:1}
.file-name{position:absolute;top:84mm;left:4mm;right:4mm;font-family:DejaVu Serif,serif;font-weight:bold;font-size:{{ $nameSize }}pt;line-height:1.15;word-wrap:break-word}
.file-rule-details{top:99mm}
.file-contact{position:absolute;top:102mm;left:5mm;right:5mm;text-align:left;font-family:DejaVu Serif,serif;font-size:{{ $detailSize }}pt;line-height:1.2;word-wrap:break-word}
.file-contact p{margin:0 0 .6mm}
.file-period{position:absolute;left:5mm;right:5mm;bottom:1.7mm;border-top:.2mm solid #b98c45;padding-top:.8mm;font-family:DejaVu Serif,serif;font-size:6.5pt}
.file-period table{width:100%;border-collapse:collapse;table-layout:fixed}.file-period td{width:50%;padding:0;vertical-align:top;word-wrap:break-word}.file-period .volume{text-align:right}
</style></head><body>
<main class="file-cover">
<table class="file-strip"><tr><td class="strip-name" style="width:74%">{{ $cover['name'] }}</td><td class="strip-number" style="width:26%">{{ $cover['id'] }}</td></tr></table>
<section class="file-body"><div class="file-rotated">
<div class="file-brand">@if($cover['brand']['logo'])<img class="file-logo" src="{{ $cover['brand']['logo'] }}" alt="Mamma Mia Cucina logo">@endif<div class="file-company">MAMMA MIA</div><div class="file-company-subtitle">CUCINA</div></div>
<div class="file-rule"></div>
<div class="file-kind">CUSTOMER FILE</div><div class="file-id-label">CUSTOMER ID</div>
<div class="file-number">#{{ $cover['id'] }}</div><div class="file-name">{{ $cover['name'] }}</div>
<div class="file-rule file-rule-details"></div>
<div class="file-contact"><p><strong>Contact:</strong> {{ $cover['contact'] ?: 'Not provided' }}</p><p><strong>Phone:</strong> {{ $cover['phone'] ?: 'Not provided' }}</p><p><strong>Email:</strong> {{ $cover['email'] ?: 'Not provided' }}</p><p><strong>Address:</strong> {{ $cover['address'] ?: 'Not provided' }}</p></div>
<div class="file-period"><table><tr><td>Year: [{{ $cover['year'] }}]</td><td class="volume">Volume: [{{ $cover['volume'] }}]</td></tr></table></div>
</div></section>
</main></body></html>
