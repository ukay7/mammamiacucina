@extends('admin.layout')
@section('title','Product Import Review')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/product-import.css') }}?v={{ filemtime(public_path('admin-assets/product-import.css')) }}">
<section class="dna-summary">
    <span class="dna-eyebrow">PRODUCT DNA · REVIEW BEFORE APPLYING</span>
    <h2>{{ $import->original_filename }}</h2>
    <div class="dna-counts">
        <div><strong>{{ $import->total_rows }}</strong><span>Product rows</span></div>
        <div><strong>{{ $import->rows()->whereIn('status',['insert','created'])->count() }}</strong><span>New products</span></div>
        <div><strong>{{ $import->rows()->whereIn('status',['update','updated'])->count() }}</strong><span>Updates</span></div>
        <div><strong>{{ $import->unchanged_count }}</strong><span>Unchanged</span></div>
        <div><strong>{{ $import->failed_count }}</strong><span>Invalid</span></div>
    </div>
    <p>Matched by <strong>Internal code</strong>. Red cells show changes; green rows identify new products. Blank cells keep current values. Images, status, ingredients and stock are preserved.</p>
    <p>Business pricing comes from the file. Individual pricing uses a 150% markup on unit cost, rounded per piece before multiplying by carton quantity. New products start active with no image or opening stock.</p>
    @if($import->status==='preview')
        <div class="alert alert-info">Preview only — no products have been changed. Review warnings and values before confirming.</div>
    @elseif($import->status==='invalid')
        <div class="alert alert-danger">Nothing has been imported. Correct the invalid rows and upload again; the entire batch must pass validation.</div>
    @elseif($import->status==='completed')
        <div class="alert alert-success">Import complete: {{ $import->created_count }} created · {{ $import->updated_count }} updated · {{ $import->unchanged_count }} unchanged. The comparison below is the saved review history.</div>
    @else
        <div class="alert alert-secondary">Cancelled — no product data was changed.</div>
    @endif
    <div class="dna-actions">
    @if(auth()->user()->hasAdminPermission('imports.manage'))
        @if($import->status==='preview')<form method="post" action="{{ route('admin.imports.commit',$import) }}" data-confirm="Apply all reviewed product changes and create the new products?">@csrf<button class="btn btn-primary">Confirm &amp; Import Products</button></form>@endif
        @if(in_array($import->status,['preview','invalid']))<form method="post" action="{{ route('admin.imports.cancel',$import) }}">@csrf<button class="btn btn-outline-secondary">Cancel import</button></form>@endif
    @endif
    <a href="{{ route('admin.imports.index') }}">Back to uploader</a>
    @if($import->stored_path)<a href="{{ route('admin.imports.download',$import) }}">Original workbook</a>@endif
    </div>
</section>
<section class="dna-grid-panel">
<form method="get" action="{{ route('admin.imports.show',$import) }}" class="dna-toolbar">
    <div><label for="dna-search">Find a product</label><input class="form-control" id="dna-search" name="search" value="{{ request('search') }}" maxlength="120" placeholder="Name, internal code or supplier SKU"></div>
    <div><label for="dna-status">Import status</label><select class="form-control" id="dna-status" name="status"><option value="">All products</option>@foreach(['new'=>'New products','updates'=>'Updates','unchanged'=>'Unchanged','invalid'=>'Invalid'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="dna-size">Rows per page</label><select class="form-control" id="dna-size" name="per_page">@foreach([25,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',25)===$size)>{{ $size }}</option>@endforeach</select></div>
    <button class="btn btn-primary" type="submit">Apply filters</button><a href="{{ route('admin.imports.show',$import) }}">Reset</a>
</form>
<div class="dna-grid-caption"><span><strong>Current value / After import</strong> · Red = changed · Green = new · Ignored fields stay unchanged</span><span>{{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }} matching products</span></div>
@php
    $headers=array_merge(config('product_import.headers'),['Individual selling price, CAD (150% markup)','Assigned website category']);
@endphp
<div class="dna-grid-scroll" tabindex="0" role="region" aria-label="Product comparison table. Scroll horizontally to see all fields.">
<table class="dna-grid"><thead><tr><th scope="col" class="dna-product-column">Product</th><th scope="col">Import status / notes</th>@foreach($headers as $header)<th scope="col">{{ $header }}</th>@endforeach</tr></thead><tbody>
@forelse($rows as $row)
@php
    $plan=$row->normalized_data;
    $diff=collect($plan['diff']??[])->keyBy('label');
    $isNew=in_array($row->status,['insert','created']);
@endphp
<tr class="{{ $isNew?'dna-new':'' }} {{ $row->status==='invalid'?'dna-invalid':'' }}">
    <th scope="row" class="dna-product-column"><strong>{{ $plan['fields']['premium_marketing_name'] ?? ($plan['source']['Mamma Mia name'] ?: 'Unnamed product') }}</strong><small>{{ $plan['source']['Internal code'] ?: 'Missing code' }}</small><small>{{ $row->source_sheet }} · Row {{ $row->row_number }}</small></th>
    <td class="dna-status-cell"><span class="dna-badge">{{ ['insert'=>'New product','update'=>'Will update','unchanged'=>'No changes','invalid'=>'Needs correction','created'=>'Created','updated'=>'Updated'][$row->status] ?? $row->status }}</span>
    @if($row->errors || !empty($plan['warnings']))<details><summary>{{ count($row->errors??[])+count($plan['warnings']??[]) }} notices</summary>@foreach($row->errors??[] as $error)<p class="text-danger">{{ $error }}</p>@endforeach @foreach($plan['warnings']??[] as $warning)<p class="text-warning">{{ $warning }}</p>@endforeach</details>@endif</td>
    @foreach($headers as $header)
    @php
        $field=$diff->get($header);
        $before=$field['before']??null;
        $after=$field['after']??($field ? null : ($plan['source'][$header]??null));
        $changed=$field['changed']??false;
        $display=static fn($v)=>\App\Support\NumberDisplay::field($v,$header);
    @endphp
    <td class="{{ $changed?'dna-changed':'' }}" @if(!empty($field['note'])) title="{{ $field['note'] }}" @endif><div class="dna-cell-values"><span class="dna-before">{{ $display($before) }}</span><span class="dna-slash"> / </span><span class="dna-after">{{ $display($after) }}</span></div>@if(mb_strlen((string)$before)+mb_strlen((string)$after)>160)<details class="dna-full-value"><summary>Full value</summary><div>{{ $display($before) }} / {{ $display($after) }}</div></details>@endif
    @if($changed)<small>{{ $isNew?'New':'Changed' }}</small>@elseif(in_array($header,config('product_import.ignored')))<small>Ignored</small>@endif</td>
    @endforeach
</tr>
@empty<tr><td colspan="{{ count($headers)+2 }}">No products match these filters. <a href="{{ route('admin.imports.show',$import) }}">Show all products</a></td></tr>@endforelse
</tbody></table>
</div>
<div class="dna-grid-footer"><span>Only this page’s rows are loaded. Scroll sideways for all columns.</span>{{ $rows->links('pagination::bootstrap-4') }}</div>
<p class="dna-import-scope">Confirm &amp; Import applies the entire reviewed workbook, including rows outside the current page or filter.</p>
</section>
@endsection
