@extends('admin.layout')
@section('title',$allergy->exists?'Edit Allergy':'Add Allergy')
@section('content')
<div class="panel"><div class="panel-content"><form method="post" enctype="multipart/form-data" action="{{ $allergy->exists?route('admin.allergies.update',$allergy):route('admin.allergies.store') }}">@csrf
@if($allergy->exists)@method('PUT')@endif
<div class="form-group"><label for="name">Name</label><input class="form-control" name="name" id="name" required maxlength="120" value="{{ old('name',$allergy->name) }}"></div><div class="form-group"><label for="icon">Icon</label>@if($allergy->iconUrl())<div><img src="{{ $allergy->iconUrl() }}" width="72" height="72" alt="Current icon" style="object-fit:contain"></div>@endif<input class="form-control" id="icon" name="icon" type="file" accept="image/png,image/jpeg,image/webp" @required(!$allergy->exists)><small>Recommended: 256 × 256 transparent PNG. JPG, PNG or WebP, maximum 2 MB.</small></div><button class="btn btn-primary">Save Allergy</button> <a href="{{ route('admin.allergies.index') }}">Back</a></form></div></div>
@endsection
