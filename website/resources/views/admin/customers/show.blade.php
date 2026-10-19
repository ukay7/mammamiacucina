@extends('admin.layout')
@section('title','Customer #'.$customer->id)
@section('hide-page-heading','1')
@section('breadcrumb-actions')
<a class="btn btn-outline-primary btn-sm" href="{{ route('admin.customers.index') }}"><i class="fas fa-arrow-left mr-2" aria-hidden="true"></i>Back to Customers</a>
@endsection
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/customer-account.css') }}?v={{ filemtime(public_path('admin-assets/customer-account.css')) }}">
<div class="customer-account">

<section class="panel panel-content customer-account-header">
<div><span class="customer-eyebrow">CUSTOMER ACCOUNT</span><h2>{{ $customer->name }}</h2>
<p class="mb-2">Customer ID <strong>#{{ $customer->id }}</strong> · {{ $customer->account_type==='business'?'Business owner':'Individual' }} · <span class="badge {{ $customer->user->is_active?'badge-success':'badge-secondary' }}">{{ $customer->user->is_active?'Active':'Inactive' }}</span></p>
<p class="mb-2 customer-contact">{{ $customer->email }} · {{ $customer->phone ?: 'No phone provided' }}</p>
<span class="badge {{ $customer->user->email_verified_at?'badge-success':'badge-danger' }}">{{ $customer->user->email_verified_at?'Email verified':'Email verification pending' }}</span>
@if($customer->account_type==='business')<span class="badge {{ $customer->user->business_approved_at?'badge-success':'badge-danger' }}">{{ $customer->user->business_approved_at?'Business approved':'Business approval pending' }}</span>@endif
</div>
<div class="customer-header-actions">
@if(auth()->user()->hasAdminPermission('pos.manage'))<a class="btn btn-primary" href="{{ route('admin.pos.index',['customer_id'=>$customer->id]) }}"><i class="fas fa-plus mr-1" aria-hidden="true"></i>Quick Sale / New Order</a>@endif
@if(auth()->user()->hasAdminPermission('customers.manage'))
<button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#customer-note-modal"><i class="fas fa-sticky-note mr-1" aria-hidden="true"></i>Add Note</button>
@endif
</div>
</section>
<div class="customer-metrics">
@foreach([
['Open receivables',$balances['receivables'],'Unpaid balance on issued invoices. Cancelled orders are excluded. Later order adjustments and confirmed payments are included.'],
['Pending payments',$balances['pending'],'Unpaid balance across all active orders, including unverified cheque/e-transfer, unpaid cash, card processing and customer credit. Includes open receivables; do not add these two figures.'],
['Credit limit',$balances['allowance'],'Base credit limit plus allowed overage. Credit checkout remains disabled when the base limit is zero.'],
['Available credit',$balances['available'],'Total credit allowance minus unpaid credit orders, with a minimum of zero. Paying a credit order restores availability.']
] as [$label,$amount,$definition])
<div class="panel panel-content customer-metric" title="{{ $definition }}" tabindex="0"><span>{{ $label }} <i class="fas fa-info-circle" aria-label="{{ $definition }}"></i></span><strong>${{ number_format($amount/100,2) }}</strong>
@if($label==='Credit limit')<small>Base ${{ number_format($customer->credit_limit_cents/100,2) }} + overage ${{ number_format($customer->overage_cents/100,2) }}</small>@elseif($label==='Available credit')<small>Used ${{ number_format($balances['used']/100,2) }}@if(!$customer->credit_limit_cents) · Credit disabled @endif</small>@elseif($label==='Open receivables')<small>Outstanding invoice balance</small>@else<small>Awaiting payment / clearance</small>@endif</div>
@endforeach
</div>
<div class="customer-account-layout">

<section class="panel customer-tab-panel">
<ul class="nav nav-tabs" role="tablist" aria-label="Customer account sections">
@foreach(['details'=>'Customer Details','orders'=>'Orders','invoices'=>'Invoices','payments'=>'Payments & Cheques','settings'=>'Manager Settings','discounts'=>'Discounts'] as $key=>$label)
@continue(in_array($key,['settings','discounts']) && !auth()->user()->hasAdminPermission('customers.manage'))
<li class="nav-item"><a class="nav-link {{ $key==='details'?'active':'' }}" id="customer-tab-{{ $key }}" href="#customer-{{ $key }}" data-toggle="tab" role="tab" aria-controls="customer-{{ $key }}" aria-selected="{{ $key==='details'?'true':'false' }}">{{ $label }}</a></li>
@endforeach
</ul>
<div class="tab-content panel-content">
<div class="tab-pane fade show active" id="customer-details" role="tabpanel" aria-labelledby="customer-tab-details">
@if(auth()->user()->hasAdminPermission('customers.manage'))
@include('admin.customers.account-editor')
@else
<h3>Customer Details</h3><dl class="customer-details">
@foreach(['name'=>'Full name','email'=>'Email','phone'=>'Phone','address'=>'Street address','city'=>'City','province'=>'Province / State','postal_code'=>'Postal code','country'=>'Country','website'=>'Website'] as $field=>$label)<dt>{{ $label }}</dt><dd>{{ $customer->$field ?: 'Not provided' }}</dd>@endforeach
@if($customer->account_type==='business')
@foreach(['business_bin'=>'BIN number','business_name'=>'Business name','business_phone'=>'Business contact number','business_email'=>'Business email'] as $field=>$label)<dt>{{ $label }}</dt><dd>{{ $customer->$field ?: 'Not provided' }}</dd>@endforeach
@endif
</dl>
@endif
</div>
<div class="tab-pane fade" id="customer-orders" role="tabpanel" aria-labelledby="customer-tab-orders"><h3>Order History</h3><p class="text-muted">All orders for this customer, including archived orders.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Order</th><th>Date</th><th>Source</th><th>Status</th><th>Payment</th><th>Total CAD</th><th>Action</th></tr></thead><tbody>
@forelse($orders as $order)<tr><td>{{ $order->number }}</td><td>{{ $order->ordered_at->format('d M Y H:i') }}</td><td>{{ ucfirst($order->source) }}</td><td>{{ $order->status_label }}@if($order->archived_at)<br><span class="badge badge-secondary">Archived</span>@endif</td><td>{{ $order->payment_status_label }}</td><td>{{ $order->final_total_cents===null?'Pending':number_format($order->final_total_cents/100,2) }}</td><td><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.orders.show',$order) }}" title="View order" aria-label="View order"><i class="fas fa-eye" aria-hidden="true"></i></a></td></tr>@empty<tr><td colspan="7">No orders placed yet.</td></tr>@endforelse
</tbody></table></div><p>Showing {{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }} orders</p>{{ $orders->withQueryString()->fragment('customer-orders')->links() }}</div>
@include('admin.customers.financial-tabs')

@if(auth()->user()->hasAdminPermission('customers.manage'))
<div class="tab-pane fade" id="customer-settings" role="tabpanel" aria-labelledby="customer-tab-settings">
@include('admin.customers.manager-settings')
</div>
<div class="tab-pane fade" id="customer-discounts" role="tabpanel" aria-labelledby="customer-tab-discounts">@include('admin.customers.discounts')</div>
@endif

</div>
</section>
<aside>
<section class="panel panel-content" id="customer-notes"><div class="d-flex justify-content-between align-items-center mb-3"><h3 class="mb-0">Notes <small>({{ $notes->total() }})</small></h3>@if(auth()->user()->hasAdminPermission('customers.manage'))<button class="btn btn-outline-primary btn-sm" type="button" title="Add Note" aria-label="Add Note" data-toggle="modal" data-target="#customer-note-modal"><i class="fas fa-plus" aria-hidden="true"></i></button>@endif</div>
@forelse($notes as $note)<article class="customer-sticky"><p>{{ $note->body }}</p><small>{{ $note->author?->name ?? 'Former staff member' }} · {{ $note->created_at->format('d M Y H:i') }}</small></article>@empty<p class="text-muted mb-0">No notes yet. Add a description or follow-up for this customer.</p>@endforelse
{{ $notes->fragment('customer-notes')->links() }}
</section>
</aside>
</div>
</div>
@if(auth()->user()->hasAdminPermission('customers.manage'))
<div class="modal fade" id="customer-note-modal" tabindex="-1" role="dialog" aria-labelledby="customer-note-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><form method="post" action="{{ route('admin.customers.notes.store',$customer) }}">@csrf<div class="modal-header"><h3 class="modal-title" id="customer-note-title">Add customer note</h3><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button></div><div class="modal-body"><p>For {{ $customer->name }} · #{{ $customer->id }}</p><label for="customer-note-body">Description</label><textarea id="customer-note-body" name="body" class="form-control" rows="6" maxlength="5000" required>{{ old('body') }}</textarea><small>Your name and the date will be recorded with this note.</small></div><div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Note</button></div></form></div></div></div>

@endif
<script src="{{ asset('admin-assets/customer-account.js') }}?v={{ filemtime(public_path('admin-assets/customer-account.js')) }}" defer></script>
@endsection
