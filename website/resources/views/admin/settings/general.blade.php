@extends('admin.layout')
@section('title','General Settings')
@section('content')
<p><a class="btn btn-outline-primary" href="{{ route('admin.home-sections.edit') }}">Edit Homepage Sections</a></p>
<p><a class="btn btn-outline-primary" href="{{ route('admin.email.history') }}">Email History</a></p>
<div class="panel panel-content"><h2>Email / SMTP Configuration</h2><p>Configure Google Workspace or another SMTP provider for verification and password reset emails.</p><a class="btn btn-outline-primary" href="{{ route('admin.email.edit') }}">Configure Email / SMTP</a></div>
<div class="panel"><div class="panel-container show"><div class="panel-content"><h2>Default Order Charges</h2><p>The flat delivery amount applies when postal-code delivery rates are disabled. Tax is calculated as a percentage of the product subtotal, excluding delivery, and rounded to the nearest cent. Existing orders retain their saved charges; edit an individual order to override them.</p><form enctype="multipart/form-data" method="post" action="{{ route('admin.settings.update') }}">@csrf @method('PUT')<input type="hidden" name="revision" value="{{ $settings->revision }}"><div class="row"><div class="col-md-6"><label for="default-delivery">Delivery amount (CAD)</label><input class="form-control" id="default-delivery" name="delivery" type="number" data-number-display="decimal" required min="0" max="9999999.99" step="0.01" value="{{ old('delivery',number_format($settings->delivery_cents/100,2,'.','')) }}"></div><div class="col-md-6"><label for="default-tax">Tax percentage (%)</label><input class="form-control" id="default-tax" name="tax" type="number" data-number-display="decimal" required min="0" max="100" step="0.01" value="{{ old('tax',number_format($settings->tax_basis_points/100,2,'.','')) }}"></div></div><p class="mt-3">Enter 0 when no charge applies.</p><hr><h2>Postal-code Delivery Rates</h2><p><a href="{{ route('admin.delivery.index') }}">View matrix, compare services and edit delivery rates →</a></p>
<div class="form-group"><label for="warehouse-postal">Warehouse From postal code</label><input class="form-control" id="warehouse-postal" name="warehouse_postal_code" maxlength="7" value="{{ old('warehouse_postal_code',$settings->warehouse_postal_code ?? 'M2N') }}"><small>Temporary prefix: M2N. Replace with your warehouse postal code.</small></div>
<input type="hidden" name="matrix_delivery_enabled" value="0"><label><input type="checkbox" name="matrix_delivery_enabled" value="1" @checked(old('matrix_delivery_enabled',$settings->matrix_delivery_enabled))> Use postal-code delivery rates at website checkout and Quick Sale</label>
<p>Requires all five service matrices. Unavailable routes are blocked. The flat delivery amount remains in use until this is enabled.</p>
<hr><h2>Pickup Address</h2><label for="pickup-address">Store / warehouse pickup address</label><textarea id="pickup-address" name="pickup_address" class="form-control" rows="3" maxlength="2000">{{ old('pickup_address',$settings->pickup_address) }}</textarea><p>Shown when customers choose Pick up. Set the full address to enable pickup at checkout.</p>
<hr><h2>E-transfer Payment Account</h2>
<div class="form-group"><label for="etransfer-email">E-transfer receiving email</label><input class="form-control" id="etransfer-email" name="etransfer_email" type="email" maxlength="255" required value="{{ old('etransfer_email',$settings->eTransferEmail()) }}"><small>Shown when E-transfer is selected in customer checkout and Quick Sale / admin orders, and beside the receipt upload on unpaid orders. Changes apply when those pages are next opened. This is separate from your contact email and email sender settings.</small></div>
<hr><h2>Document Header</h2>
<p>Company details printed on invoices, order confirmations, receipts, supplier orders and reports.</p>
<div class="form-group"><label for="document-company-name">Company legal name</label><input class="form-control" id="document-company-name" name="document_company_name" maxlength="160" required value="{{ old('document_company_name',$settings->document_company_name ?: \App\Models\GeneralSetting::DOCUMENT_COMPANY_NAME) }}"></div>
<div class="row"><div class="form-group col-md-6"><label for="document-phone">Contact phone</label><input class="form-control" id="document-phone" name="phone" maxlength="60" value="{{ old('phone',$settings->phone) }}"></div><div class="form-group col-md-6"><label for="document-email">Contact email</label><input class="form-control" id="document-email" type="email" name="email" maxlength="255" value="{{ old('email',$settings->email) }}"></div></div>
<p class="text-muted">Phone and email are shared with Contact Us settings.</p>
<div class="row"><div class="form-group col-md-6"><label for="document-mailing-address">Mailing address</label><textarea class="form-control" id="document-mailing-address" name="mailing_address" rows="3" maxlength="2000">{{ old('mailing_address',$settings->mailing_address) }}</textarea></div><div class="form-group col-md-6"><label for="document-warehouse-address">Warehouse address for documents</label><textarea class="form-control" id="document-warehouse-address" name="document_warehouse_address" rows="3" maxlength="2000">{{ old('document_warehouse_address',$settings->document_warehouse_address) }}</textarea><small>Pickup instructions for customers are managed above.</small></div></div>
<fieldset class="mb-3">
    <legend class="h5">Invoice Payment Methods</legend>
    <p class="text-muted">Invoices use the E-transfer receiving email and mailing address above. Enter the direct deposit details below to display them alongside those methods.</p>
    <div class="row">
        @foreach(['invoice_eft_transit' => ['Transit number', 5, 5], 'invoice_eft_institution' => ['Institution number', 3, 3], 'invoice_eft_account' => ['Account number', 1, 20]] as $field => [$label, $min, $max])
            <div class="col-md-4 form-group">
                <label for="{{ $field }}">{{ $label }}</label>
                <input class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $settings->$field) }}" type="text" inputmode="numeric" autocomplete="off" minlength="{{ $min }}" maxlength="{{ $max }}" pattern="[0-9]+">
                @error($field)<div class="text-danger">{{ $message }}</div>@enderror
            </div>
        @endforeach
    </div>
</fieldset>
<div class="form-group"><label for="document-gst-hst">GST/HST number</label><input class="form-control" id="document-gst-hst" name="gst_hst_number" maxlength="80" value="{{ old('gst_hst_number',$settings->gst_hst_number) }}"><small>Revised invoices and order-payment receipts show this in the header. Other documents show it in the footer.</small></div>
<div class="form-group">
    <label for="financial-document-layout">Invoice and payment receipt layout</label>
    <select class="form-control" id="financial-document-layout" name="financial_document_layout" aria-describedby="financial-document-layout-help">
        @foreach(\App\Models\GeneralSetting::FINANCIAL_DOCUMENT_LAYOUTS as $value => $label)
            <option value="{{ $value }}" @selected(old('financial_document_layout', $settings->financial_document_layout ?? 'classic') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <small id="financial-document-layout-help">Choose the layout for invoices and order-payment receipts. The selection applies the next time a document is opened or generated.</small>
    @error('financial_document_layout')<div class="text-danger">{{ $message }}</div>@enderror
</div>
<hr><h2>Business Customer Icon</h2>
<p>Show this icon beside business customer names throughout customer and order screens, reports and printed documents.</p>
<div class="form-group">
    <label for="business-customer-icon">Upload business customer icon</label>
    <div class="mb-3">Preview: Business customer <x-business-customer-icon :business="true" /></div>
    <p class="text-muted">A small gold building icon is included by default. Upload your own icon to replace it.</p>
    <input class="form-control" type="file" id="business-customer-icon" name="business_customer_icon" accept="image/png,image/jpeg,image/webp" aria-describedby="business-icon-help">
    <small id="business-icon-help" class="form-text text-muted">Use a square icon, preferably a transparent PNG. PNG, JPG or WebP, up to 2 MB and 2048 × 2048 pixels. Leave empty to keep the current icon.</small>
    @if($settings->business_customer_icon_path)<label class="mt-2"><input type="checkbox" name="remove_business_customer_icon" value="1" @checked(old('remove_business_customer_icon'))> Restore default business customer icon</label>@endif
    @error('business_customer_icon')<div class="text-danger">{{ $message }}</div>@enderror
</div>
<hr><h2>Website Details</h2>
<div class="form-group"><label for="site-logo">Logo</label><div class="mb-3"><img src="{{ $settings->logoUrl() }}" alt="Current logo" style="width:140px;height:140px;object-fit:contain;background:#1c150a;border-radius:8px;padding:10px"></div><input class="form-control" type="file" id="site-logo" name="logo" accept="image/png,image/jpeg,image/webp"><small class="form-text text-muted">Recommended: transparent PNG, 800 × 800 px. PNG, JPG or WebP, maximum 5 MB. Leave empty to keep the current logo.</small></div>
<div class="form-group"><label for="page-banner">Inner-page banner background</label><div class="mb-3"><img src="{{ $settings->pageBannerUrl() }}" alt="Current page banner" style="width:100%;max-width:600px;height:160px;object-fit:cover"></div><input class="form-control" type="file" id="page-banner" name="page_banner" accept="image/png,image/jpeg,image/webp"><small class="form-text text-muted">Background behind page titles such as General Login, Checkout and About Us. Recommended: wide image, 1920 × 500 px. PNG, JPG or WebP, maximum 5 MB. Leave empty to keep the current background.</small></div>
<p>Contact details, Instagram and Facebook are managed in the Contact Us settings screen.</p>
<div class="form-group"><label for="site-youtube">YouTube video URL or ID</label><input class="form-control" id="site-youtube" name="youtube" value="{{ old('youtube',$settings->youtube_video_id ? 'https://www.youtube.com/watch?v='.$settings->youtube_video_id : '') }}"><small class="form-text text-muted">Paste a YouTube watch, share or Shorts link. Leave empty to hide the video.</small></div>
<hr><h2>Social Links</h2><p>Enter your full profile URLs. Leave a field blank to hide that icon in the footer.</p>
@foreach(['twitter_url'=>'Twitter / X'] as $field=>$label)
<div class="form-group"><label for="{{ $field }}">{{ $label }}</label><input class="form-control" type="url" id="{{ $field }}" name="{{ $field }}" maxlength="500" placeholder="https://" value="{{ old($field,$settings->$field) }}"></div>
@endforeach
<hr><h2>Opening Hours</h2><p>Set the local business hours for each day. Closing before opening indicates an overnight schedule.</p>
@foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day)
<div class="row mb-3"><div class="col-md-3"><strong>{{ $day }}</strong><input type="hidden" name="opening_hours[{{ $day }}][closed]" value="0"><label class="d-block"><input type="checkbox" name="opening_hours[{{ $day }}][closed]" value="1" @checked(old('opening_hours.'.$day.'.closed',$settings->opening_hours[$day]['closed'] ?? false))> Closed</label></div><div class="col-md-4"><label for="open-{{ $day }}">Opens</label><input class="form-control" type="time" id="open-{{ $day }}" name="opening_hours[{{ $day }}][open]" required value="{{ old('opening_hours.'.$day.'.open',$settings->opening_hours[$day]['open'] ?? '08:00') }}"></div><div class="col-md-4"><label for="close-{{ $day }}">Closes</label><input class="form-control" type="time" id="close-{{ $day }}" name="opening_hours[{{ $day }}][close]" required value="{{ old('opening_hours.'.$day.'.close',$settings->opening_hours[$day]['close'] ?? '20:30') }}"></div></div>
@endforeach
<button class="btn btn-primary" type="submit">Save Settings</button></form></div></div></div>
@endsection
