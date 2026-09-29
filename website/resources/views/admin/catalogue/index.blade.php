@extends('admin.layout')
@section('title','Catalogue')
@section('content')
<div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:24px"><p>Upload one image per page. Pages appear in display order; the same category can include several pages.</p><div><a class="btn btn-outline-primary" href="{{ route('theme.catalogue') }}" target="_blank" rel="noopener">View catalogue</a> <a class="btn btn-primary" href="{{ route('admin.catalogue.create') }}">Add page</a></div></div>
<div class="panel"><div class="panel-content table-responsive">
<table class="table"><thead><tr><th>Page</th><th>Section</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($pages as $page)
<tr><td><div style="display:flex;align-items:center;gap:15px"><img src="{{ route('admin.catalogue.preview',[$page,'thumb'=>1,'v'=>$page->revision]) }}" alt="" loading="lazy" style="width:56px;height:76px;object-fit:contain;background:#eee5d5"><strong>{{ $page->title }}</strong></div></td><td>{{ $page->label() }}<small style="display:block">{{ $page->category_id?'Product category':'Catalogue-only label' }}</small></td><td>{{ $page->sort_order }}</td><td>{{ $page->is_active?'Published':'Draft' }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.catalogue.edit',$page) }}">Edit</a><form style="display:inline" method="post" action="{{ route('admin.catalogue.destroy',$page) }}" data-confirm="Delete this catalogue page? Products and categories will remain unchanged.">@csrf @method('DELETE')<input type="hidden" name="revision" value="{{ $page->revision }}"><button class="btn btn-sm btn-outline-primary">Delete</button></form></td></tr>
@empty<tr><td colspan="5">No catalogue pages yet. Add your first page above.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
