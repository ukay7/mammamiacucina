@php
$sections = config('admin_navigation');
$allowed = static fn($item) => \App\Services\AdminNavigation::allowed(auth()->user(), $item);
$active = static fn($item) => str_starts_with($item['match'], 'path:')
    ? request()->is(substr($item['match'], 5))
    : request()->routeIs($item['match'])
        && !($item['route'] === 'admin.customers.index' && request()->routeIs('admin.customers.business-requests'))
        && !($item['route'] === 'admin.orders.index' && request()->routeIs('admin.orders.completed','admin.orders.deleted.*'))
        && !($item['route'] === 'admin.reports.inventory' && request()->routeIs('admin.reports.inventory-valuation*'))
        && !($item['route'] === 'admin.reports.index' && request()->routeIs('admin.reports.inventory*','admin.reports.overdue-cheques'));
@endphp
@if(auth()->user()->isCustomer())
<li><a href="{{ route('customer.orders') }}"><i class="fas fa-home" aria-hidden="true"></i><span>My Orders</span></a></li>
<li class="{{ request()->routeIs('customer.profile*') ? 'active' : '' }}"><a href="{{ route('customer.profile') }}"><i class="fas fa-user" aria-hidden="true"></i><span>My Profile</span></a></li>
@else
@foreach($sections as $heading => $items)
@php
$visible = collect($items)->reject(fn($item)=>($item['route']??null)==='admin.stock-counts.mine' && auth()->user()->hasAdminPermission('stock-counts.view'))->map(function($item) use ($allowed) {
    if (isset($item['children'])) {
        $item['children'] = array_values(array_filter($item['children'], $allowed));
        return $item['children'] ? $item : null;
    }
    return $allowed($item) ? $item : null;
})->filter();
@endphp
@if($visible->isNotEmpty())
<li class="nav-title">{{ $heading }}</li>
@foreach($visible as $item)
@if(isset($item['children']))
<li><details class="mmc-admin-submenu" @if(collect($item['children'])->contains($active)) open @endif>
<summary><i class="fas fa-{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span><i class="fas fa-chevron-down mmc-submenu-arrow" aria-hidden="true"></i></summary>
<ul>@foreach($item['children'] as $child)
@include('admin.sidebar-link', ['entry' => $child, 'isActive' => $active($child)])
@endforeach</ul></details></li>
@else
@include('admin.sidebar-link', ['entry' => $item, 'isActive' => $active($item)])
@endif
@endforeach
@endif
@endforeach
@endif
@if(auth()->user()->hasAdminPermission('settings.manage') && count($siteSettings?->contactLinks(true) ?? []))
<li class="nav-title">Quick Links</li>
<li><details class="mmc-admin-submenu"><summary><i class="fas fa-external-link-alt" aria-hidden="true"></i><span>Website & Social</span><i class="fas fa-chevron-down mmc-submenu-arrow" aria-hidden="true"></i></summary><ul>@include('admin.sidebar-social')</ul></details></li>

@endif
