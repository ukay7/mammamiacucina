@extends('admin.layout')
@section('title','Homepage Sections')
@section('content')
<p>Edit the homepage text and images. Existing layout, icons and image animations are preserved.</p>
@foreach($sections as $section)
<div class="panel panel-content" style="max-width:1000px">
<h2>{{ $section->key==='tradition'?'Our Tradition':'Authentic Recipes' }}</h2>
<form method="post" enctype="multipart/form-data" action="{{ route('admin.home-sections.update',$section->key) }}">
@csrf @method('PUT')
<input type="hidden" name="revision" value="{{ $section->revision }}">
@foreach(['eyebrow'=>'Section label','heading'=>'Main heading','description'=>'Description','image_alt'=>'Image description (accessibility)'] as $key=>$label)
<div class="form-group"><label for="{{ $section->key.'-'.$key }}">{{ $label }}</label><textarea class="form-control" id="{{ $section->key.'-'.$key }}" name="{{ $key }}" rows="{{ $key==='description'?4:2 }}" @required($key!=='description')>{{ $section->content[$key] }}</textarea></div>
@endforeach
@if($section->key==='baking')
@foreach($section->content['features'] as $i=>$feature)
<h3>Feature {{ $i+1 }}</h3>
<label for="feature-heading-{{ $i }}">Heading</label><input class="form-control mb-3" id="feature-heading-{{ $i }}" name="features[{{ $i }}][heading]" value="{{ $feature['heading'] }}" required>
<label for="feature-description-{{ $i }}">Description</label><textarea class="form-control mb-3" id="feature-description-{{ $i }}" name="features[{{ $i }}][description]" required>{{ $feature['description'] }}</textarea>
@endforeach
@endif
<img src="{{ $section->imageUrl() }}" alt="Current section image" style="display:block;width:100%;max-width:460px;margin:20px 0">
<label for="{{ $section->key }}-image">Replace image</label><input class="form-control" id="{{ $section->key }}-image" name="image" type="file" accept="image/png,image/jpeg,image/webp">
<p>PNG, JPG or WebP, up to 5 MB. A wide image around 1920 × 1080 works best. Leave empty to retain the current image.</p>
<button class="btn btn-primary" type="submit">Save {{ $section->key==='tradition'?'Our Tradition':'Authentic Recipes' }}</button>
</form></div>
@endforeach
@endsection
