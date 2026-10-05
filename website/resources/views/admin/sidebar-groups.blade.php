@php
$sidebarGroups = [
 ['Manage Product','fa-box-open',[
  ['products.view','admin.products.index','admin.products.*','View Products','fa-box-open'],
  ['categories.view','admin.categories.index','admin.categories.*','Category Management','fa-tags'],
  ['inventory.view','admin.inventory.index','admin.inventory.*','Inventory','fa-warehouse'],
  ['imports.view','admin.imports.index','admin.imports.*','Bulk Product Uploader','fa-file-upload'],
  ['allergies.manage','admin.allergies.index','admin.allergies.*','Allergies','fa-leaf'],
 ]],
 ['Manage Order','fa-shopping-cart',[
  ['orders.view|warehouse.pack','admin.orders.index','admin.orders.*','View Pending Orders','fa-shopping-cart'],
  ['orders.view|warehouse.pack','admin.orders.completed','admin.orders.completed','View Completed Orders','fa-check-circle'],
  ['orders.view','admin.reports.payments','admin.reports.payments*','Payment Report','fa-chart-bar'],
 ]],
];
@endphp
@if(auth()->user()->hasAdminPermission('pos.manage'))
<li class="{{ request()->routeIs('admin.pos.*')?'active':'' }}"><a href="{{ route('admin.pos.index') }}"><i class="fas fa-cash-register" aria-hidden="true"></i><span>Quick Sale / POS</span></a></li>
@endif
@foreach($sidebarGroups as [$groupTitle,$groupIcon,$groupItems])
@php
$visibleItems=collect($groupItems)->filter(fn($item)=>collect(explode('|',$item[0]))->contains(fn($ability)=>auth()->user()->hasAdminPermission($ability)));
$groupOpen=$visibleItems->contains(fn($item)=>request()->routeIs($item[2]));
@endphp
@if($visibleItems->isNotEmpty())
<li><details class="mmc-admin-submenu" @if($groupOpen) open @endif><summary><i class="fas {{ $groupIcon }}" aria-hidden="true"></i><span>{{ $groupTitle }}</span><i class="fas fa-chevron-down mmc-submenu-arrow" aria-hidden="true"></i></summary><ul>
@foreach($visibleItems as $item)
@php($itemActive = request()->routeIs($item[2]) && !($item[1]==='admin.orders.index' && request()->routeIs('admin.orders.completed')))
<li class="{{ $itemActive?'active':'' }}"><a href="{{ route($item[1]) }}" @if($itemActive) aria-current="page" @endif><i class="fas {{ $item[4] }}" aria-hidden="true"></i><span>{{ $item[3] }}</span></a></li>
@endforeach
</ul></details></li>
@endif
@endforeach
@if(auth()->user()->hasAdminPermission('enquiries.manage'))<li class="{{ request()->routeIs('admin.enquiries.*')?'active':'' }}"><a href="{{ route('admin.enquiries.index') }}"><i class="fas fa-envelope" aria-hidden="true"></i><span>Contact Us Queries</span></a></li>@endif

@if(auth()->user()->hasAdminPermission('orders.view'))
<li class="{{ request()->routeIs('admin.customers.*')?'active':'' }}"><a href="{{ route('admin.customers.index') }}"><i class="fas fa-address-book" aria-hidden="true"></i><span>Customer Management</span></a></li>
@endif

@if(auth()->user()->hasAdminPermission('quotations.view') || auth()->user()->hasAdminPermission('quotations.manage'))<li class="{{ request()->routeIs('admin.quotations.*')?'active':'' }}"><a href="{{ route('admin.quotations.index') }}"><i class="fas fa-file-invoice"></i><span>Quotations</span></a></li>@endif

@if(auth()->user()->hasAdminPermission('settings.manage'))
<li><details class="mmc-admin-submenu" @if(request()->routeIs('admin.email.*')) open @endif><summary><i class="fas fa-envelope"></i><span>Manage Email</span><i class="fas fa-chevron-down mmc-submenu-arrow"></i></summary><ul>
<li><a href="{{ route('admin.email.edit') }}">SMTP Configuration</a></li>
<li><a href="{{ route('admin.email.templates.index') }}">Email Templates</a></li>
<li><a href="{{ route('admin.email.history') }}">Email History</a></li>
</ul></details></li>
@endif
