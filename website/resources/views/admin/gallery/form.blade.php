@extends('admin.layout')
@section('title',$event->exists?'Edit Gallery Event':'Add Gallery Event')
@section('content')
<div class="panel"><div class="panel-content"><form method="post" enctype="multipart/form-data" action="{{ $event->exists?route('admin.gallery.update',$event):route('admin.gallery.store') }}">@csrf
@if($event->exists)@method('PUT')@endif
<div class="form-group"><label for="title">Event title</label><input class="form-control" id="title" name="title" required maxlength="160" value="{{ old('title',$event->title) }}"></div>
<div class="form-group"><label for="description">Description</label><textarea class="form-control" id="description" name="description" rows="3" maxlength="5000">{{ old('description',$event->description) }}</textarea></div>
<div class="row"><div class="form-group col-md-4"><label for="event_date">Event date (optional)</label><input class="form-control" type="date" id="event_date" name="event_date" value="{{ old('event_date',$event->event_date?->format('Y-m-d')) }}"></div><div class="form-group col-md-4"><label for="sort_order">Display order</label><input class="form-control" type="number" id="sort_order" name="sort_order" min="0" max="100000" required value="{{ old('sort_order',$event->sort_order) }}"></div><div class="form-group col-md-4"><label for="is_active">Status</label><select class="form-control" id="is_active" name="is_active"><option value="1" @selected(old('is_active',$event->is_active)==1)>Active</option><option value="0" @selected(old('is_active',$event->is_active)==0)>Hidden</option></select></div></div>
<div class="form-group"><label for="photos">Upload event photos</label><input class="form-control" type="file" id="photos" name="photos[]" multiple accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WebP. Up to 20 pictures per upload, 5 MB each. Recommended: at least 1600 px wide. Existing photos are kept.</small></div>
<button class="btn btn-primary">Save Event</button> <a href="{{ route('admin.gallery.index') }}" class="btn btn-outline-primary">Back to Events</a></form></div></div>
@if($event->exists)
<h2 class="mt-4">Event Photos</h2><div class="row">@forelse($event->photos as $photo)<div class="col-md-3 col-sm-6 mb-4"><img src="{{ route('gallery.image',$photo) }}" alt="{{ $event->title }}" loading="lazy" style="width:100%;height:160px;object-fit:contain;background:#fff;border-radius:8px"><form method="post" action="{{ route('admin.gallery.photos.remove',[$event,$photo]) }}" data-confirm="Remove this photo?">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm mt-2">Remove Photo</button></form></div>@empty<p class="ml-3">Upload pictures above to publish this event in the gallery.</p>@endforelse</div>
<form method="post" action="{{ route('admin.gallery.destroy',$event) }}" data-confirm="Delete this event and all its photos?">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete Event</button></form>
@endif
@endsection
