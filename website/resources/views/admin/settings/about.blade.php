@extends('admin.layout')
@section('title','About Us')
@section('content')
<style>.about-editor{max-width:1200px}.about-editor-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:22px}.about-editor .panel-content{padding:24px}.about-editor label{display:block;margin:14px 0 6px}.about-edit-grid{display:grid;grid-template-columns:1.4fr 1fr;gap:28px}.about-image-preview{width:100%;max-height:260px;object-fit:contain;background:#f3eadb;border-radius:8px}.about-item-editor{padding:18px;border:1px solid #dfcba5;border-radius:8px;margin:16px 0;background:#fffaf1}.about-item-head{display:flex;justify-content:space-between;gap:12px;align-items:center}.about-item-actions{display:flex;gap:6px}.about-help{font-size:13px;color:#746653}@media(max-width:750px){.about-edit-grid{grid-template-columns:1fr}.about-editor-head{align-items:start;flex-direction:column}.about-item-head{align-items:start;flex-wrap:wrap}}</style>
<div class="about-editor">
<div class="about-editor-head"><p>Edit the introduction, Italian quote and numbered highlights.</p><div><a class="btn btn-outline-primary" href="{{ route('theme.about') }}" target="_blank" rel="noopener">View page</a> <button class="btn btn-primary" type="submit" form="about-form">Save changes</button></div></div>
<form id="about-form" method="post" enctype="multipart/form-data" action="{{ route('admin.about.update') }}">@csrf @method('PUT')
<input type="hidden" name="revision" value="{{ old('revision',$about->revision) }}">
<div class="panel"><div class="panel-content"><h2>Introduction</h2><div class="about-edit-grid"><div>
<label for="about-eyebrow">Small heading</label><input class="form-control" id="about-eyebrow" name="eyebrow" maxlength="100" value="{{ old('eyebrow',$about->eyebrow) }}">
<label for="about-heading">Heading</label><textarea class="form-control" id="about-heading" name="heading" maxlength="255" rows="2" required>{{ old('heading',$about->heading) }}</textarea>
<label for="about-description">Description</label><textarea class="form-control" id="about-description" name="description" maxlength="15000" rows="8" required>{{ old('description',$about->description) }}</textarea><p class="about-help">Use a blank line between paragraphs.</p>
<label for="about-button">Button name</label><input class="form-control" id="about-button" name="button_name" maxlength="80" required value="{{ old('button_name',$about->button_name) }}">
<label for="about-destination">Button destination</label><select class="form-control" id="about-destination" name="button_page">@foreach(['product-grid'=>'Products','contact'=>'Contact Us','gallery'=>'Gallery'] as $value=>$label)<option value="{{ $value }}" @selected(old('button_page',$about->button_page)===$value)>{{ $label }}</option>@endforeach</select>
</div><div><label for="about-image">Image</label><img class="about-image-preview" src="{{ $about->imageUrl() }}" alt="Current About Us image">
<input class="form-control mt-3" id="about-image" name="image" type="file" accept="image/png,image/jpeg,image/webp"><p class="about-help">PNG, JPG or WebP, up to 5 MB. Leave empty to keep the current image.</p>
<label for="about-image-alt">Image description</label><input class="form-control" id="about-image-alt" name="image_alt" maxlength="255" value="{{ old('image_alt',$about->image_alt) }}" required>
</div></div></div></div>
<div class="panel"><div class="panel-content"><h2>Italian quote section</h2>
<label for="about-quote">Quote</label><textarea class="form-control" id="about-quote" name="closing_sentence" rows="3" maxlength="2000">{{ old('closing_sentence',$about->closing_sentence) }}</textarea>
<p class="about-help">Use a new line where you want the quote to break. Leave blank to hide this section.</p>
<label for="about-supporting">Supporting text</label><textarea class="form-control" id="about-supporting" name="quote_supporting_text" rows="4" maxlength="2000">{{ old('quote_supporting_text',$about->quote_supporting_text) }}</textarea>
<p class="about-help">Displayed below the gold divider. Each new line is preserved.</p></div></div>
<div class="panel"><div class="panel-content"><div class="about-editor-head"><div><h2>Highlights</h2><p class="about-help">Three items fit across on desktop. Additional items scroll left and right. Save to publish changes.</p></div><button class="btn btn-outline-primary" type="button" data-add-about-item>Add item</button></div>
<div data-about-items>
@foreach(session()->hasOldInput()?old('items',[]):$about->items as $item)
<article class="about-item-editor" data-about-item><div class="about-item-head"><strong data-item-number>Item {{ $loop->iteration }}</strong><div class="about-item-actions"><button class="btn btn-sm btn-outline-primary" type="button" data-move="-1" aria-label="Move item up">↑</button><button class="btn btn-sm btn-outline-primary" type="button" data-move="1" aria-label="Move item down">↓</button><button class="btn btn-sm btn-outline-primary" type="button" data-remove-item>Remove</button></div></div>
<label>Heading<input class="form-control" data-field="title" name="items[{{ $loop->index }}][title]" value="{{ $item['title']??'' }}" maxlength="120" required></label>
<label>Description<textarea class="form-control" data-field="description" name="items[{{ $loop->index }}][description]" rows="3" maxlength="2000" required>{{ $item['description']??'' }}</textarea></label></article>
@endforeach
</div><p data-about-empty hidden>No highlights yet. Add an item to show it below the introduction.</p><p class="about-help" role="status" data-about-feedback></p><button class="btn btn-primary" type="submit">Save changes</button>
</div></div></form>
<template id="about-item-template">
<article class="about-item-editor" data-about-item><div class="about-item-head"><strong data-item-number></strong><div class="about-item-actions"><button class="btn btn-sm btn-outline-primary" type="button" data-move="-1" aria-label="Move item up">↑</button><button class="btn btn-sm btn-outline-primary" type="button" data-move="1" aria-label="Move item down">↓</button><button class="btn btn-sm btn-outline-primary" type="button" data-remove-item>Remove</button></div></div>
<label>Heading<input class="form-control" data-field="title" maxlength="120" required></label><label>Description<textarea class="form-control" data-field="description" rows="3" maxlength="2000" required></textarea></label></article>
</template></div>
<script src="{{ asset('admin-assets/mmc-about-editor.js') }}?v={{ filemtime(public_path('admin-assets/mmc-about-editor.js')) }}"></script>
@endsection
