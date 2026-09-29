@extends('admin.layout')
@section('title',$page->exists?'Edit catalogue page':'Add catalogue page')
@section('content')
<style>.catalogue-editor{max-width:1100px}.catalogue-editor-grid{display:grid;grid-template-columns:1fr 1fr;gap:30px}.catalogue-editor label{display:block;margin:16px 0 6px}.catalogue-editor .panel-content{padding:24px}@media(max-width:700px){.catalogue-editor-grid{grid-template-columns:1fr}}</style>
<form class="catalogue-editor" method="post" enctype="multipart/form-data" action="{{ $page->exists?route('admin.catalogue.update',$page):route('admin.catalogue.store') }}">
@csrf @if($page->exists) @method('PUT') @endif
<input type="hidden" name="revision" value="{{ old('revision',$page->revision) }}">
<div style="display:flex;justify-content:flex-end;gap:10px;margin-bottom:20px"><a class="btn btn-outline-primary" href="{{ route('admin.catalogue.index') }}">Close</a><button class="btn btn-primary">Save page</button></div>
<div class="panel"><div class="panel-content catalogue-editor-grid"><div>
<label for="title">Page title</label><input class="form-control" id="title" name="title" required maxlength="255" value="{{ old('title',$page->title) }}">
<label for="category">Product category (optional)</label><select class="form-control" name="category_id" id="category"><option value="">Use a catalogue-only label</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id',$page->category_id)==$category->id)>{{ $category->name }}</option>@endforeach</select>
<label for="custom-label">Catalogue-only label</label><input class="form-control" name="custom_label" id="custom-label" maxlength="255" value="{{ old('custom_label',$page->custom_label) }}"><small>Required when no product category is selected. For example: Cover, About Us, or Contact Us. This does not create a product category.</small>
<label for="sort-order">Display order</label><input class="form-control" type="number" min="0" max="1000000" id="sort-order" name="sort_order" required value="{{ old('sort_order',$page->sort_order) }}"><small>Lower numbers appear first. Use gaps such as 10, 20, 30 to insert pages between them.</small>
<label for="published">Visibility</label><select class="form-control" id="published" name="is_active"><option value="1" @selected(old('is_active',$page->is_active))>Published</option><option value="0" @selected(!old('is_active',$page->is_active))>Draft</option></select>
</div><div>
@if($page->exists)<img src="{{ route('admin.catalogue.preview',[$page,'v'=>$page->revision]) }}" alt="{{ $page->title }}" style="width:100%;height:350px;object-fit:contain;background:#eee5d5">@endif
<label for="image">Page image {{ $page->exists?'(leave empty to keep current image)':'' }}</label><input class="form-control" id="image" type="file" name="image" accept="image/png,image/jpeg,image/webp" @required(!$page->exists)><small>PNG, JPG or WebP, up to 10 MB. Use a portrait page with readable text.</small>
</div></div></div></form>
@endsection
