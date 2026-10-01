@php($businessProfile = $businessProfile ?? null)
<fieldset data-business-fields @if(isset($businessDynamic)) hidden @endif style="border:1px solid #dec8a5;border-radius:6px;padding:18px;margin:18px 0">
<legend style="font-size:18px;width:auto;padding:0 8px">Business details</legend>
@foreach(['business_bin'=>['BIN number','text',100],'business_name'=>['Business name','text',255],'business_phone'=>['Business contact number','tel',40],'business_email'=>['Business email','email',255]] as $field=>$meta)
<div class="form-group mb-3"><label for="{{ $field }}">{{ $meta[0] }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" type="{{ $meta[1] }}" maxlength="{{ $meta[2] }}" value="{{ old($field,$businessProfile?->$field) }}" @if(!isset($businessDynamic)) required @endif></div>
@endforeach
<small>Business email is a contact address. Your login email stays unchanged.</small>
</fieldset>
