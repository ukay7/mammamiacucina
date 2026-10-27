@extends('admin.layout')
@section('title',$supplier->exists?'Edit Supplier':'Add Supplier')
@section('content')
<form class="panel panel-content" method="post" action="{{ $supplier->exists?route('admin.suppliers.update',$supplier):route('admin.suppliers.store') }}">@csrf @if($supplier->exists)@method('PUT')@endif
<input type="hidden" name="revision" value="{{ old('revision',$supplier->revision??0) }}">
@if($supplier->exists)<p>Supplier ID: <strong>#{{ $supplier->id }}</strong></p>@endif
<div class="row">@foreach(['name'=>'Supplier name','contact_name'=>'Contact person','email'=>'Email address','phone'=>'Phone number'] as $key=>$label)<div class="form-group col-md-6"><label for="supplier-{{ $key }}">{{ $label }}{{ $key==='name'?' *':'' }}</label><input class="form-control" id="supplier-{{ $key }}" name="{{ $key }}" type="{{ $key==='email'?'email':'text' }}" maxlength="{{ $key==='phone'?60:255 }}" value="{{ old($key,$supplier->$key) }}" @required($key==='name')></div>@endforeach</div>
<div class="form-group"><label for="supplier-address">Address</label><textarea class="form-control" id="supplier-address" name="address" rows="3" maxlength="3000">{{ old('address',$supplier->address) }}</textarea></div>
<div class="form-group"><label for="supplier-notes">Notes</label><textarea class="form-control" id="supplier-notes" name="notes" rows="3" maxlength="5000">{{ old('notes',$supplier->notes) }}</textarea></div>
<div class="form-group"><label for="supplier-status">Status</label><select class="form-control" id="supplier-status" name="is_active"><option value="1" @selected(old('is_active',$supplier->is_active)==1)>Active</option><option value="0" @selected(old('is_active',$supplier->is_active)==0)>Inactive</option></select></div>
<p class="text-muted">Only the name is required. Contact details can be added later. Renaming a supplier updates the linked product supplier names.</p>
<button class="btn btn-primary">Save Supplier</button> <a class="btn btn-outline-primary" href="{{ route('admin.suppliers.index') }}">Cancel</a>
</form>
@endsection
