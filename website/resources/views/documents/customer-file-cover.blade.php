<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Customer file #{{ $cover['id'] }}</title>
@include('documents.style')
@php
$nameLength = mb_strlen($cover['name']);
$nameSize = $nameLength > 180 ? 5.5 : ($nameLength > 120 ? 7.5 : ($nameLength > 80 ? 8.5 : ($nameLength > 45 ? 11 : ($nameLength > 30 ? 14 : 18))));
$headerSize = $nameLength > 100 ? 6 : ($nameLength > 55 ? 7.5 : 11.5);
$detailLength = mb_strlen(implode(' ', array_intersect_key($cover, array_flip(['contact', 'phone', 'email', 'address']))));
$detailSize = $detailLength > 400 ? 5.5 : ($detailLength > 250 ? 6.5 : ($detailLength > 160 ? 7 : 8));
$numberSize = strlen($cover['id']) > 12 ? 30 : (strlen($cover['id']) > 6 ? 40 : ($nameLength > 45 ? 48 : 54));
@endphp
<style>
@page{size:A4 portrait;margin:0}html,body{margin:0;padding:0;background:#fff;color:#080808;font-family:DejaVu Sans,Arial,sans-serif}
.file-cover{position:absolute;left:32mm;top:6mm;width:130mm;height:126mm}
.file-strip{position:absolute;left:0;top:0;width:130mm;height:13.2mm;background:#022159}
.file-strip .strip-name{position:absolute;left:6mm;top:1.1mm;width:90mm;height:11mm;border:.25mm solid #022159;background:#fff;color:#022159;font-size:{{ $headerSize }}pt}
.strip-name-inner{position:absolute;left:4mm;right:4mm;top:50%;transform:translateY(-50%);font-weight:bold;line-height:1.15;word-wrap:break-word}
.file-strip .strip-number{position:absolute;right:0;top:0;width:34mm;height:13.2mm;color:#fff;text-align:center;font-size:{{ strlen($cover['id']) > 7 ? 11 : 24 }}pt}
.strip-number-inner{position:absolute;left:0;right:0;top:50%;transform:translateY(-50%);font-weight:bold;line-height:1.15;text-align:center}
.file-body{position:absolute;left:0;top:14mm;width:130mm;height:112mm;border:.35mm solid #b98c45}
.file-rotated{position:absolute;left:0;top:111.3mm;width:111.3mm;height:129.3mm;transform:rotate(-90deg);transform-origin:0 0;text-align:center}
.file-brand{position:absolute;top:1mm;left:4mm;right:4mm;height:49mm;overflow:hidden}
.file-logo{position:absolute;left:50%;top:-4mm;width:46mm;height:46mm;margin-left:-23mm}
.file-company{position:absolute;top:34mm;left:0;right:0;font-family:DejaVu Serif,serif;font-size:21pt;font-weight:bold;line-height:1.05}
.file-company-subtitle{position:absolute;top:44mm;left:0;right:0;color:#b98c45;font-family:DejaVu Serif,serif;font-size:11.5pt;letter-spacing:2.5pt;line-height:1.25}
.file-rule{position:absolute;left:4mm;right:4mm;top:54.5mm;border-top:.2mm solid #b98c45}
.file-rule span{position:absolute;top:-1.3mm;left:49%;width:2mm;line-height:2mm;background:white;color:#b98c45;font:6pt DejaVu Sans,sans-serif;text-align:center}
.file-kind{position:absolute;top:57.5mm;left:4mm;right:4mm;font-family:DejaVu Serif,serif;font-weight:bold;font-size:10pt;letter-spacing:1.3pt;color:#257149}
.file-id-label{position:absolute;top:62.5mm;left:4mm;right:4mm;font-size:6pt;letter-spacing:.5pt}
.file-number{position:absolute;top:63mm;left:4mm;right:4mm;font-family:DejaVu Serif,serif;font-weight:bold;font-size:{{ $numberSize }}pt;line-height:1}
.file-name{position:absolute;top:{{ $nameLength > 45 ? 83.5 : 87 }}mm;left:4mm;right:4mm;font-family:DejaVu Serif,serif;font-weight:bold;font-size:{{ $nameSize }}pt;line-height:1.15;word-wrap:break-word}
.file-rule-details{top:97.5mm}
.file-contact{position:absolute;top:100mm;left:5mm;right:5mm;text-align:left;font-family:DejaVu Serif,serif;font-size:{{ $detailSize }}pt;line-height:1.2;word-wrap:break-word}
.file-contact p{margin:0 0 .6mm}
.file-period{position:absolute;left:5mm;right:5mm;bottom:1.7mm;border-top:.2mm solid #b98c45;padding-top:.8mm;font-family:DejaVu Serif,serif;font-size:7.5pt}
.file-period table{width:100%;border-collapse:collapse;table-layout:fixed}.file-period td{width:50%;padding:0;vertical-align:top;word-wrap:break-word}.file-period .volume{text-align:right}
</style></head><body>
<main class="file-cover">
<div class="file-strip"><div class="strip-name"><div class="strip-name-inner">{{ $cover['name'] }}</div></div><div class="strip-number"><div class="strip-number-inner">{{ $cover['id'] }}</div></div></div>
<section class="file-body"><div class="file-rotated">
<div class="file-brand">@if($cover['brand']['logo'])<img class="file-logo" src="{{ $cover['brand']['logo'] }}" alt="Mamma Mia Cucina logo">@endif<div class="file-company">MAMMA MIA</div><div class="file-company-subtitle">CUCINA</div></div>
<div class="file-rule"><span aria-hidden="true">◇</span></div>
<div class="file-kind">CUSTOMER FILE</div><div class="file-id-label">CUSTOMER ID</div>
<div class="file-number">#{{ $cover['id'] }}</div><div class="file-name">{{ $cover['name'] }}</div>
<div class="file-rule file-rule-details"><span aria-hidden="true">◇</span></div>
<div class="file-contact"><p><strong>Contact:</strong> {{ $cover['contact'] ?: 'Not provided' }}</p><p><strong>Phone:</strong> {{ $cover['phone'] ?: 'Not provided' }}</p><p><strong>Email:</strong> {{ $cover['email'] ?: 'Not provided' }}</p><p><strong>Address:</strong> {{ $cover['address'] ?: 'Not provided' }}</p></div>
<div class="file-period"><table><tr><td>Year: [{{ $cover['year'] }}]</td><td class="volume">Volume: [{{ $cover['volume'] }}]</td></tr></table></div>
</div></section>
</main></body></html>
