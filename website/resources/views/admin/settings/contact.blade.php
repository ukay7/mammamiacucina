@extends('admin.layout')
@section('title','Contact Us')
@section('content')
<style>.contact-editor{max-width:1200px}.contact-editor .panel-content{padding:24px}.contact-editor-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}.contact-editor label{display:block;margin:14px 0 6px}.contact-editor small{display:block;margin-top:6px}.contact-editor-actions{display:flex;justify-content:flex-end;gap:10px;margin-bottom:20px}@media(max-width:700px){.contact-editor-grid{grid-template-columns:1fr}}</style>
<form class="contact-editor" id="contact-settings" method="post" enctype="multipart/form-data" action="{{ route('admin.contact.update') }}">@csrf @method('PUT')
<input type="hidden" name="revision" value="{{ old('revision',$settings->revision) }}">
<div class="contact-editor-actions"><a class="btn btn-outline-primary" href="{{ route('theme.contact') }}" target="_blank" rel="noopener">View page</a><button class="btn btn-primary">Save changes</button></div>
<div class="panel"><div class="panel-content"><h2>Contact details</h2><p>Shown on the Contact Us page and website footer. Leave any optional field blank to hide it.</p><div class="contact-editor-grid">
@foreach(['whatsapp_number'=>['WhatsApp number','tel','+1 416 555 0123'],'instagram_url'=>['Instagram URL','url','https://www.instagram.com/yourname'],'facebook_url'=>['Facebook URL','url','https://www.facebook.com/yourpage'],'website_url'=>['Website URL','url','https://example.com'],'email'=>['Email','email','hello@example.com'],'phone'=>['Phone number','tel','+1 416 555 0123']] as $field=>$details)
<div><label for="{{ $field }}">{{ $details[0] }}</label><input class="form-control" type="{{ $details[1] }}" id="{{ $field }}" name="{{ $field }}" maxlength="{{ $field==='phone'?60:($field==='whatsapp_number'?30:($field==='email'?255:500)) }}" placeholder="{{ $details[2] }}" value="{{ old($field,$settings->$field) }}">@if($field==='whatsapp_number')<small>Include the country code. Customers can tap this to open WhatsApp.</small>@endif</div>
@endforeach
</div></div></div>
<div class="panel"><div class="panel-content"><h2>Page introduction</h2><div class="contact-editor-grid"><div>
<label for="contact_eyebrow">Small heading</label><input class="form-control" id="contact_eyebrow" name="contact_eyebrow" maxlength="255" value="{{ old('contact_eyebrow',$settings->contact_eyebrow) }}">
<label for="contact_heading">Heading</label><input class="form-control" id="contact_heading" name="contact_heading" maxlength="255" required value="{{ old('contact_heading',$settings->contact_heading) }}">
<label for="contact_description">Description</label><textarea class="form-control" id="contact_description" name="contact_description" rows="6" maxlength="15000" required>{{ old('contact_description',$settings->contact_description) }}</textarea>
</div><div>
<img src="{{ $settings->contact_image_path?route('contact.image',['v'=>$settings->revision]):asset('assets/images/mmc/category-cannoli-hd.png') }}" alt="{{ $settings->contact_image_alt }}" style="width:100%;height:230px;object-fit:contain;background:#f3eadb">
<label for="contact-image">Image</label><input class="form-control" id="contact-image" type="file" name="image" accept="image/png,image/jpeg,image/webp"><small>PNG, JPG or WebP, maximum 5 MB. Leave empty to keep the current image.</small>
<label for="contact_image_alt">Image description</label><input class="form-control" id="contact_image_alt" name="contact_image_alt" maxlength="255" required value="{{ old('contact_image_alt',$settings->contact_image_alt) }}">
</div></div><p class="mt-3">Opening hours are managed in General Settings. Customer messages continue to appear in Contact Enquiries.</p></div></div>
<button class="btn btn-primary">Save changes</button></form>
@endsection
