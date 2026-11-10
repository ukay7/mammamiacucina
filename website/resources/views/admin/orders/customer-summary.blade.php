@php
    $profile = $order->customer;
    $isBusiness = $profile?->account_type === 'business';
    $company = $isBusiness ? trim((string) $profile->business_name) : '';
    $contact = trim($order->first_name.' '.$order->last_name) ?: ($profile?->name ?: 'Customer');
    $type = $profile ? ($isBusiness ? 'Business customer' : 'Individual customer') : 'Guest customer';
@endphp
<div class="mmc-order-customer text-break">
    <small class="d-block">{{ $type }}</small>
    <strong class="d-block">{{ $company !== '' ? $company : $contact }}</strong>
    @if($company !== '')<span class="d-block">Customer: {{ $contact }}</span>@endif
    <small class="d-block">{{ filled($order->email) ? $order->email : 'Email not provided' }}</small>
</div>
