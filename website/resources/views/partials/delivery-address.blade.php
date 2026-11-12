@php
 $addressService=app(\App\Services\CustomerDeliveryAddress::class);
 $companyAddress=isset($addressCustomer)?$addressService->company($addressCustomer):$addressService->normalize([]);
 $alternateAddress=$addressService->normalize($addressCustomer->delivery_address??[]);
 $addressType=old('delivery_address_type',$addressService->complete($companyAddress)?'company':'alternate');
 if($addressType==='alternate')foreach(\App\Services\CustomerDeliveryAddress::FIELDS as $field)$alternateAddress[$field]=old($field,$alternateAddress[$field]);
 $addressPrefix=$addressPrefix??'delivery';
@endphp
<link rel="stylesheet" href="{{ asset('assets/css/delivery-address.css') }}?v={{ filemtime(public_path('assets/css/delivery-address.css')) }}">
<fieldset class="delivery-address-picker" data-delivery-address data-company="{{ json_encode($companyAddress) }}" data-alternate="{{ json_encode($alternateAddress) }}" data-account-type="{{ $addressCustomer->account_type??'business' }}">
<legend>Where should we deliver this order?</legend>
<div class="delivery-address-options">
<label><input type="radio" name="delivery_address_type" value="company" @checked($addressType==='company')><span data-company-label>{{ ($addressCustomer->account_type??'business')==='business'?'Company address':'Profile address' }}</span></label>
<label><input type="radio" name="delivery_address_type" value="alternate" @checked($addressType==='alternate')><span>Different delivery address</span></label>
</div>
<p class="delivery-company-summary" data-company-summary></p>
<p class="delivery-address-hint" data-company-missing hidden>The company/profile address is incomplete. Enter a delivery address below, or complete the customer profile.</p>
<div data-alternate-fields>
<p class="delivery-address-hint">Deliver to a warehouse or another location. This address is saved separately for future orders and can be edited here.</p>
<div class="delivery-address-grid">
@foreach(['address'=>['Street address','street-address',255],'city'=>['City','address-level2',100],'province'=>['Province / State','address-level1',100],'postal_code'=>['Postal code','postal-code',30],'country'=>['Country','country-name',100]] as $field=>$details)
<label @if($field==='address') class="delivery-address-wide" @endif for="{{ $addressPrefix }}-{{ $field }}">{{ $details[0] }}<input class="form-control" data-address-field id="{{ $addressPrefix }}-{{ $field }}" name="{{ $field }}" maxlength="{{ $details[2] }}" autocomplete="shipping {{ $details[1] }}" value="{{ ($addressType==='company'?$companyAddress:$alternateAddress)[$field] }}" required @error($field) aria-invalid="true" @enderror></label>
@endforeach
</div></div>
</fieldset>
