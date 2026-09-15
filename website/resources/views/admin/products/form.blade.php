@extends(request()->boolean('modal')?'admin.modal':'admin.layout')
@section('title',$product->exists?'Edit Product':'Add Product')
@section('content')
@unless(request()->boolean('modal'))<div class="mb-3"><a class="btn btn-outline-primary" href="{{ $product->exists ? route('admin.products.show',$product) : route('admin.products.index') }}">← {{ $product->exists ? 'Back to Product' : 'Back to Products' }}</a><button class="btn btn-primary float-right" type="submit" form="product-edit-form">Save Product</button></div>@endunless
<form id="product-edit-form" enctype="multipart/form-data" method="post" action="{{ $product->exists?route('admin.products.update',[$product,'modal'=>request()->boolean('modal')?1:0]):route('admin.products.store') }}">@csrf @if($product->exists)@method('PUT')@endif
@php($primaryFields=['premium_marketing_name','product_code','qr_code','total_selling_price_cad'])
<div class="panel"><div class="panel-hdr"><h2>Product Essentials</h2></div><div class="panel-container show"><div class="panel-content"><div class="row">
@foreach($primaryFields as $key)
@php($field=config('product_fields.'.$key))
@php($field[0]=['premium_marketing_name'=>'Product Name','qr_code'=>'Product QR Code'][$key] ?? $field[0])
@include('admin.products.field')
@endforeach
<div class="form-group col-md-6"><fieldset><legend class="h6">Categories</legend><p class="text-muted">Select one or more categories for this product.</p>@php($selectedCategories=old('category_ids',$product->exists?$product->categories->pluck('id')->all():[$product->category_id]))<div style="max-height:230px;overflow:auto">@foreach($categories as $category)<label class="d-block"><input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id,(array)$selectedCategories))> {{ $category->name }}{{ $category->is_active?'':' (Inactive)' }}</label>@endforeach</div></fieldset></div><div class="form-group col-md-6"><fieldset><legend class="h6">Allergies & Dietary Information</legend><p class="text-muted">Select the labels that apply to this product.</p><input type="hidden" name="allergies_present" value="1">@php($selectedAllergies=old('allergies_present')?old('allergy_ids',[]):old('allergy_ids',$product->exists?$product->allergies->pluck('id')->all():[]))<div style="max-height:230px;overflow:auto">@foreach($allergies as $allergy)<label class="d-flex align-items-center mb-2"><input type="checkbox" name="allergy_ids[]" value="{{ $allergy->id }}" @checked(in_array($allergy->id,(array)$selectedAllergies))><img src="{{ $allergy->iconUrl() }}" alt="" width="30" height="30" style="object-fit:contain;margin:0 8px">{{ $allergy->name }}</label>@endforeach</div></fieldset></div></div></div></div></div>
@include('admin.products.media',['editing'=>true])

<div class="panel"><div class="panel-hdr"><h2>Other Product Details</h2></div><div class="panel-container show"><div class="panel-content"><p class="text-muted">Blank values stay empty. Prices retain up to eight decimal places.</p><div class="row">
@foreach(config('product_fields') as $key=>$field)
@unless(in_array($key,$primaryFields))
@include('admin.products.field')
@if($key==='surcharge_increase_cad')
<div class="form-group col-md-6"><label for="is_active">Product enabled</label><select class="form-control" id="is_active" name="is_active"><option value="1" @selected(old('is_active',$product->is_active)==1)>Active</option><option value="0" @selected(old('is_active',$product->is_active)==0)>Inactive</option></select></div>
@endif
@endunless
@endforeach
</div><button class="btn btn-primary" type="submit">Save Product</button></div></div></div></form>
@endsection
