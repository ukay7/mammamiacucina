@extends('admin.layout')
@section('title',($record->exists?'Edit ':'Add ').['policies'=>'Policy','departments'=>'Department','jobs'=>'Job Posting'][$kind])
@section('content')
<form class="panel panel-content p-4" method="post" enctype="multipart/form-data" action="{{ $record->exists?route('admin.content.update',[$kind,$record->id]):route('admin.content.store',$kind) }}">@csrf @if($record->exists) @method('PUT') @endif
<div class="row"><div class="form-group col-md-8"><label>Title<input class="form-control" name="title" maxlength="255" value="{{ old('title',$record->title) }}" required></label></div><div class="form-group col-md-4"><label>Display order<input class="form-control" type="number" name="sort_order" min="0" max="99999" value="{{ old('sort_order',$record->sort_order??0) }}" required></label></div></div>
@if($kind==='policies')
<label class="d-block mb-3">Short summary<textarea class="form-control" name="summary" maxlength="500" rows="2">{{ old('summary',$record->summary) }}</textarea></label>
<label class="d-block mb-3">Effective date<input class="form-control" type="date" name="effective_date" value="{{ old('effective_date',$record->effective_date?->format('Y-m-d')) }}"></label>
<label class="d-block mb-3">Policy text<textarea class="form-control" name="body" rows="16" maxlength="100000" required>{{ old('body',$record->body) }}</textarea></label><p>Separate paragraphs with a blank line. Text is displayed exactly as written; HTML is not needed.</p>
@elseif($kind==='departments')
<label class="d-block mb-3">Department description<textarea class="form-control" name="description" rows="5" maxlength="5000">{{ old('description',$record->description) }}</textarea></label>
@if($record->image_path)<img src="{{ route('careers.image',$record) }}" alt="Current department image" style="width:240px;max-height:180px;object-fit:cover"><label class="d-block"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>@endif
<label class="d-block mb-3">Department image (JPG, PNG or WebP, max 5 MB)<input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
<label class="d-block mb-3">Image description<input class="form-control" name="image_alt" maxlength="255" value="{{ old('image_alt',$record->image_alt) }}"></label>
@else
<div class="row"><div class="form-group col-md-6"><label>Department<select class="form-control" name="department_id" required><option value="">Choose a department</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id',$record->department_id)==$department->id)>{{ $department->title }}{{ $department->is_active?'':' (Draft)' }}</option>@endforeach</select></label></div><div class="form-group col-md-6"><label>Employment type<select class="form-control" name="employment_type" required>@foreach(['Full-time','Part-time','Contract','Temporary','Internship'] as $type)<option @selected(old('employment_type',$record->employment_type)===$type)>{{ $type }}</option>@endforeach</select></label></div></div>
@foreach(['location'=>'Location / remote arrangement','salary'=>'Salary / compensation (optional)','application_email'=>'Application email'] as $field=>$label)<label class="d-block mb-3">{{ $label }}<input class="form-control" type="{{ $field==='application_email'?'email':'text' }}" name="{{ $field }}" maxlength="255" value="{{ old($field,$record->$field) }}" @required($field!=='salary')></label>@endforeach
<label class="d-block mb-3">Closing date (leave blank for ongoing recruitment)<input class="form-control" type="date" name="closing_date" value="{{ old('closing_date',$record->closing_date?->format('Y-m-d')) }}"></label>
@foreach(['description'=>'About the role','responsibilities'=>'Responsibilities — one per line','skills'=>'Required skills & experience — one per line','benefits'=>'Benefits — one per line','application_instructions'=>'How to apply / documents required'] as $field=>$label)<label class="d-block mb-3">{{ $label }}<textarea class="form-control" name="{{ $field }}" rows="5" @required(in_array($field,['description','skills']))>{{ old($field,$record->$field) }}</textarea></label>@endforeach
<p>The Apply button opens an email addressed to the application email above, with the job title in the subject.</p>
@endif
<input type="hidden" name="is_active" value="0"><label class="d-block my-4"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$record->is_active))> Publish on website</label>
<button class="btn btn-primary">Save {{ $kind==='jobs'?'job posting':($kind==='policies'?'policy':'department') }}</button> <a class="btn btn-outline-primary" href="{{ route('admin.content.index',$kind) }}">Cancel</a>
</form>
@endsection
