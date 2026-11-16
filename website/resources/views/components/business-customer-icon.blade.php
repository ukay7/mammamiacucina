@props(['customer' => null, 'customerId' => null, 'business' => null, 'embedded' => false])
@php
    $icons = app(\App\Services\BusinessCustomerIcon::class);
    $iconSource = ($business ?? $icons->isBusiness($customer, $customerId)) ? $icons->source($embedded) : null;
@endphp
@if($iconSource)<img class="business-customer-icon" src="{{ $iconSource }}" alt="Business customer" title="Business customer" width="14" height="14" style="display:inline-block;width:14px;height:14px;max-width:none;object-fit:contain;vertical-align:middle;margin-left:4px;border:0;border-radius:0;padding:0;flex-shrink:0;">@endif
