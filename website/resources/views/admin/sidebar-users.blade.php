@php
$sidebarGroups = [
 ['User Management','fa-users',[
  ['users.view','admin.users.index','admin.users.*','View User','fa-user'],
  ['roles.view','admin.roles.index','admin.roles.*','User Type & Permission','fa-user-shield'],
 ]],
];
@endphp
@foreach($sidebarGroups as [$groupTitle,$groupIcon,$groupItems])
@php
$visibleItems=collect($groupItems)->filter(fn($item)=>auth()->user()->hasAdminPermission($item[0]));
$groupOpen=$visibleItems->contains(fn($item)=>request()->routeIs($item[2]));
@endphp
@if($visibleItems->isNotEmpty())
<li><details class="mmc-admin-submenu" @if($groupOpen) open @endif><summary><i class="fas {{ $groupIcon }}" aria-hidden="true"></i><span>{{ $groupTitle }}</span><i class="fas fa-chevron-down mmc-submenu-arrow" aria-hidden="true"></i></summary><ul>
@foreach($visibleItems as $item)
<li class="{{ request()->routeIs($item[2])?'active':'' }}"><a href="{{ route($item[1]) }}" @if(request()->routeIs($item[2])) aria-current="page" @endif><i class="fas {{ $item[4] }}" aria-hidden="true"></i><span>{{ $item[3] }}</span></a></li>
@endforeach
</ul></details></li>
@endif
@endforeach
