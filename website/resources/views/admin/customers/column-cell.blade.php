@switch($key)
@case('id')
    <strong class="customer-directory-id">#{{ $customer->id }}</strong>
    @break
@case('identity')
    @if($customer->account_type==='business' && filled($customer->business_name))
        <strong class="customer-directory-name">{{ $customer->business_name }}</strong>
        <strong class="customer-directory-owner">Owner: {{ $customer->name }}</strong>
    @else
        <strong class="customer-directory-name">{{ $customer->name }}</strong>
    @endif
    <strong class="customer-directory-email">{{ $customer->email }}</strong>
    @break
@case('phone')
    {{ $customer->phone ?: '—' }}
    @break
@case('account_type')
    {{ $customer->account_type==='business'?'Business owner':'Individual' }}
    @if($customer->account_type==='business')<br><strong>{{ $customer->user->business_approved_at?'Approved':'Approval pending' }}</strong>@endif
    @break
@case('verified')
    {{ $customer->user->email_verified_at?'Yes':'Pending' }}
    @break
@case('status')
    {{ $customer->user->is_active?'Active':'Inactive' }}
    @break
@case('created_at')
    <span class="text-nowrap">{{ $customer->created_at?->format('d M Y') ?? '—' }}</span>
    @break
@case('orders')
    {{ $customer->orders_count }}
    @break
@case('last_order')
    {{ $customer->orders_max_ordered_at ? \Carbon\Carbon::parse($customer->orders_max_ordered_at)->format('d M Y') : '—' }}
    @break
@endswitch
