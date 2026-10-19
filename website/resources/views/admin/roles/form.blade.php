@extends('admin.layout')
@section('title',$role->exists?'Edit User Type':'Add User Type')
@section('content')
@php($selected=old('permissions',$role->exists?\App\Services\AdminNavigation::upgrade($role->permissions??[]):[]))
<form method="post" action="{{ $role->exists ? route('admin.roles.update',$role) : route('admin.roles.store') }}" class="panel">@csrf @if($role->exists)@method('PUT')@endif
<div class="panel-hdr"><h2>User Type &amp; Sidebar Access</h2></div><div class="panel-content">
<input type="hidden" name="permissions[]" value="access.configured">
<label for="name">User type name</label><input id="name" name="name" class="form-control mb-4" required maxlength="80" value="{{ old('name',$role->name) }}">
<p>Select the sidebar pages this user type can open. Names do not grant access. Unchecked pages are hidden and blocked, including direct links.</p>
<div class="row">
@foreach(collect(\App\Services\AdminNavigation::nodes())->groupBy('group') as $group=>$nodes)
<fieldset class="col-md-6 mb-3"><legend class="h5">{{ $group }}</legend>
@foreach($nodes as $node)@php($key=\App\Services\AdminNavigation::key($node))
@if(auth()->user()->hasAdminPermission($key))<label class="d-block mb-2"><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key,$selected))> {{ $node['label'] }}</label>@endif
@endforeach</fieldset>
@endforeach</div>
<fieldset class="mt-3"><legend class="h5">Actions &amp; order visibility</legend>
<p class="text-muted">Page access keeps existing module behaviour. For modules with separate editing permissions, enable the actions below. An action does not make an unchecked page accessible.</p>
<div class="row">@foreach(config('admin.permissions')+['orders.scope.all'=>'Orders — view all orders (otherwise packing access is limited to assigned warehouse orders and own sales)'] as $key=>$label)
@if((str_ends_with($key,'.manage') || in_array($key,['warehouse.pack','payments.refund','orders.scope.all'])) && auth()->user()->hasAdminPermission($key))
<label class="col-md-6 mb-2"><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key,$selected))> {{ $label }}</label>
@endif @endforeach</div></fieldset>
<button class="btn btn-primary mt-3">Save User Type</button><a class="btn btn-light mt-3 ml-2" href="{{ route('admin.roles.index') }}">Cancel</a>
</div></form>
@if($role->exists)<form method="post" action="{{ route('admin.roles.destroy',$role) }}" data-confirm="Delete this user type? It must have no assigned users.">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete User Type</button></form>@endif
@endsection
