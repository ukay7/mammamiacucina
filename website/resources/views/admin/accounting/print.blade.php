<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body{max-width:1200px;margin:30px auto;padding:0 20px;color:#30271e;font:12px/1.5 Arial,sans-serif}
        h1{border-bottom:2px solid #b98c45;padding-bottom:8px}table{width:100%;border-collapse:collapse}
        th,td{padding:6px;text-align:left;border-bottom:1px solid #e0ccb1}th{background:#f7f0e6}
        @page{size:A4 landscape;margin:12mm}@media print{body{margin:0;padding:0}button{display:none}tr{break-inside:avoid}}
    </style>
</head>
<body>
    <button type="button" onclick="window.print()">Print / Save PDF</button>
    <h1>{{ $title }}</h1>
    @if(in_array('from', $active))<p>{{ $filters['from'] }} to {{ $filters['to'] }}</p>
    @elseif(in_array('to', $active))<p>As of {{ $filters['to'] }}</p>@endif
    <p>{{ $description }}</p>
    @include('admin.accounting.table')
</body>
</html>
