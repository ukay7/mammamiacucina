@php($billing=$refund->snapshot['customer'])
<h2>Customer</h2><div class="doc-billing-line doc-billing-name">{{ $billing['business_name'] ?: $billing['name'] }}</div><div class="doc-billing-line doc-billing-account">Customer account #{{ $billing['id'] }}</div>
@if($billing['business_name'])<div class="doc-billing-line">{{ $billing['name'] }}</div>@endif
@foreach([$billing['address'],implode(', ',array_filter([$billing['city'],$billing['province'],$billing['postal_code']])),$billing['email'],$billing['phone']] as $value)@if($value)<div class="doc-billing-line">{{ $value }}</div>@endif @endforeach
