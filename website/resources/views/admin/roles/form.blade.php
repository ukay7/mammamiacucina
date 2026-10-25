@extends('admin.layout')
@section('title',$role->exists?'Edit User Type':'Add User Type')
@section('content')
@php
$selected=old('permissions',$role->exists?\App\Services\StaffPermissions::upgrade($role->permissions??[]):[]);
$actions=\App\Services\StaffPermissions::actions();
$orderKeys=['access.admin.orders.index','access.admin.orders.completed'];
@endphp
<style>
.permission-module{border:1px solid #e6d5b8;border-radius:6px;padding:14px;margin-bottom:12px;background:var(--mmc-panel-bg,#fffaf2)}
.permission-module label{display:flex;align-items:flex-start;gap:9px;margin:0;cursor:pointer}.permission-module input{margin-top:4px;flex-shrink:0}
.permission-actions{margin:12px 0 0 22px;display:grid;gap:10px}.permission-group{margin-bottom:20px}.permission-group legend{font-size:1.05rem;font-weight:600}
</style>
<form method="post" id="staff-permissions" action="{{ $role->exists ? route('admin.roles.update',$role) : route('admin.roles.store') }}" class="panel">
@csrf @if($role->exists)@method('PUT')@endif
<div class="panel-hdr"><h2>User Type &amp; Permissions</h2></div><div class="panel-content">
<input type="hidden" name="permissions[]" value="access.grouped">
@if(in_array('orders.scope.restricted',$selected))<input type="hidden" name="permissions[]" value="orders.scope.restricted">@endif
<label for="name">User type name</label><input id="name" name="name" class="form-control mb-3" required maxlength="80" value="{{ old('name',$role->name) }}">
<p>Choose each module and its actions together. Actions select the required page automatically. Turning a page off removes its actions. Unselected pages are hidden and blocked.</p>
<div class="row">
@foreach(collect(\App\Services\AdminNavigation::nodes())->groupBy('group') as $group=>$nodes)
@php($visible=$nodes->filter(fn($node)=>!($node['super_only']??false) && auth()->user()->hasAdminPermission(\App\Services\AdminNavigation::key($node))))
@continue($visible->isEmpty())
<fieldset class="col-lg-6 permission-group"><legend>{{ $group }}</legend>
@foreach($visible as $node)
@php($key=\App\Services\AdminNavigation::key($node))
<div class="permission-module" data-module="{{ $key }}">
<label><input type="checkbox" class="permission-page" name="permissions[]" value="{{ $key }}" @checked(in_array($key,$selected))><strong>{{ $node['label'] }}</strong></label>
@if(isset($actions[$node['route']]))
<div class="permission-actions">
@foreach($actions[$node['route']] as $action=>$label)
@if(auth()->user()->hasAdminPermission($action))
<label><input type="checkbox" class="permission-action" name="permissions[]" value="{{ $action }}" @checked(in_array($action,$selected))><span>{{ $label }}</span></label>
@endif
@endforeach
</div>
@elseif(!in_array($key,$orderKeys))
<small class="text-muted d-block mt-1 ml-4">{{ str_contains($node['permission'],'.manage') ? 'Allows use and management of this module.' : 'Allows viewing this module.' }}</small>
@endif
</div>
@endforeach
@if($visible->contains(fn($node)=>in_array(\App\Services\AdminNavigation::key($node),$orderKeys)))
<div class="permission-module" id="order-permission-actions"><strong>Actions for selected order pages</strong><div class="permission-actions">
@foreach(\App\Services\StaffPermissions::orderActions() as $action=>$label)
@if(auth()->user()->hasAdminPermission($action))<label><input type="checkbox" name="permissions[]" value="{{ $action }}" @checked(in_array($action,$selected))><span>{{ $label }}</span></label>@endif
@endforeach
</div><small class="text-muted d-block mt-2">Packing-only users see assigned warehouse orders and their own sales. All-orders visibility expands that scope. Order managers can see all orders in their selected pages.</small></div>
@endif
</fieldset>
@endforeach
</div>
<button class="btn btn-primary">Save User Type</button><a class="btn btn-light ml-2" href="{{ route('admin.roles.index') }}">Cancel</a>
</div></form>
@if($role->exists)<form method="post" action="{{ route('admin.roles.destroy',$role) }}" data-confirm="Delete this user type? It must have no assigned users.">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete User Type</button></form>@endif
<script>
document.getElementById('staff-permissions').addEventListener('change',function(event){
 const input=event.target;
 const module=input.closest('[data-module]');
 if(module){
  if(input.classList.contains('permission-action')&&input.checked) module.querySelector('.permission-page').checked=true;
  if(input.classList.contains('permission-page')&&!input.checked) module.querySelectorAll('.permission-action').forEach(el=>el.checked=false);
  if(input.value==='payments.refund'&&input.checked){const manage=module.querySelector('[value="payments.manage"]');if(manage)manage.checked=true;}
  if(input.value==='payments.manage'&&!input.checked){const refund=module.querySelector('[value="payments.refund"]');if(refund)refund.checked=false;}
 }
 const pages=Array.from(this.querySelectorAll('.permission-page')).filter(el=>['access.admin.orders.index','access.admin.orders.completed'].includes(el.value));
 const orderActions=this.querySelector('#order-permission-actions');
 if(orderActions&&orderActions.contains(input)&&input.checked&&!pages.some(el=>el.checked)&&pages.length) pages[0].checked=true;
 if(pages.includes(input)&&!pages.some(el=>el.checked)&&orderActions) orderActions.querySelectorAll('input').forEach(el=>el.checked=false);
});
</script>
@endsection
