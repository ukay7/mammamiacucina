@extends('admin.layout')
@section('title',ucfirst($kind))
@section('content')
@if($kind!=='jobs')<details class="panel panel-content mb-4"><summary style="cursor:pointer;font-weight:600">Edit {{ $kind==='policies'?'Policies':'Careers' }} page heading & introduction</summary><form class="mt-3" method="post" action="{{ route('admin.content.page',$kind) }}">@csrf @method('PUT')
@foreach(['title'=>'Page title','eyebrow'=>'Small heading','heading'=>'Main heading'] as $field=>$label)<label class="d-block mb-3">{{ $label }}<input class="form-control" name="{{ $field }}" maxlength="255" value="{{ old($field,$pageSettings->$field) }}" @required($field!=='eyebrow')></label>@endforeach
<label class="d-block mb-3">Introduction<textarea class="form-control" name="introduction" rows="3" maxlength="5000">{{ old('introduction',$pageSettings->introduction) }}</textarea></label><button class="btn btn-primary">Save page introduction</button></form></details>@endif

<div class="mb-4 d-flex flex-wrap" style="gap:12px"><a class="btn btn-primary" href="{{ route('admin.content.create',$kind) }}">Add {{ ['policies'=>'Policy','departments'=>'Department','jobs'=>'Job Posting'][$kind] }}</a><a class="btn btn-outline-primary" href="{{ route($kind==='policies'?'theme.policies':'theme.careers') }}" target="_blank" rel="noopener">View website page</a>@if($kind!=='policies')<a class="btn btn-outline-primary" href="{{ route('admin.content.index',$kind==='jobs'?'departments':'jobs') }}">{{ $kind==='jobs'?'Manage departments':'Manage job postings' }}</a>@endif</div>
<p>Drafts are hidden from the website. Lower display-order numbers appear first.@if($kind==='jobs') Expired jobs and jobs in unpublished departments are also hidden.@endif</p>
<div class="panel panel-content table-responsive"><table class="table"><thead><tr><th>Title</th><th>Publication</th><th>Display order</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
@forelse($records as $record)<tr><td>{{ $record->title }}@if($kind==='jobs')<small class="d-block">{{ $record->location }} · {{ $record->employment_type }}@if($record->closing_date) · Closes {{ $record->closing_date->format('d M Y') }}@endif</small>@endif</td><td>{{ $record->is_active?'Published':'Draft' }}</td><td>{{ $record->sort_order }}</td><td>{{ $record->updated_at->format('d M Y') }}</td><td><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.content.edit',[$kind,$record->id]) }}">Edit</a> <form class="d-inline" method="post" action="{{ route('admin.content.destroy',[$kind,$record->id]) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Delete</button></form></td></tr>
@empty<tr><td colspan="5">No {{ $kind }} yet. Add your first entry above.</td></tr>@endforelse
</tbody></table>{{ $records->links() }}</div>
@endsection
