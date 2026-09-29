@extends(request()->boolean('modal')?'admin.modal':'admin.layout')
@section('title',$product->exists?'Edit Product':'Add Product')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/product-dna.css') }}">
@unless(request()->boolean('modal'))<div class="mb-3"><a class="btn btn-outline-primary" href="{{ route('admin.products.index') }}">Back to Products</a><button class="btn btn-primary float-right" type="submit" form="product-edit-form">Save Product</button></div>@endunless
<div class="dna-summary"><strong>{{ $product->premium_marketing_name ?: 'New product' }}</strong><span>{{ $product->qr_code }} · {{ $product->supplier }}</span><span data-current-price>Current selling price: {{ $product->total_selling_price_cad===null?'Not set':'CAD '.number_format($product->total_selling_price_cad,2) }}</span></div>
<nav class="dna-tabs" aria-label="Product sections">@foreach(['identity'=>'Identity','packing'=>'Packing and handling','files'=>'Images and ingredients','pricing'=>'Pricing','evidence'=>'Notes and sources'] as $tab=>$label)<button type="button" data-dna-tab="{{ $tab }}" aria-pressed="{{ $loop->first?'true':'false' }}">{{ $label }}</button>@endforeach</nav>
<form id="product-edit-form" enctype="multipart/form-data" method="post" action="{{ $product->exists?route('admin.products.update',[$product,'modal'=>request()->boolean('modal')?1:0]):route('admin.products.store') }}">@csrf @if($product->exists)@method('PUT')@endif
<input type="hidden" name="editor_revision" value="{{ old('editor_revision',$product->editor_revision??0) }}">
<section data-dna-panel="identity" class="dna-panel">
<p class="text-muted">Edit Total Selling Price (CAD) below and click Save Product. The Pricing tab is an optional calculator for setting the same price.</p>
<div class="row">
@foreach(['premium_marketing_name','total_selling_price_cad','business_selling_price_cad','qr_code','product_code','supplier','italian_subtitle','original_description'] as $key)
@php($field=config('product_fields.'.$key)) @include('admin.products.field')
@endforeach
@include('admin.products.dna-fields',['group'=>'identity'])
<div class="form-group col-md-6"><label for="is_active">Product enabled</label><select class="form-control" id="is_active" name="is_active"><option value="1" @selected(old('is_active',$product->is_active)==1)>Active</option><option value="0" @selected(old('is_active',$product->is_active)==0)>Inactive</option></select></div>
</div><div class="row">
<div class="form-group col-md-6"><fieldset><legend class="h6">Categories</legend><p class="text-muted">Select one or more categories for this product.</p>@php($selectedCategories=old('category_ids',$product->exists?$product->categories->pluck('id')->all():[$product->category_id]))<div style="max-height:230px;overflow:auto">@foreach($categories as $category)<label class="d-block"><input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id,(array)$selectedCategories))> {{ $category->name }}{{ $category->is_active?'':' (Inactive)' }}</label>@endforeach</div></fieldset></div><div class="form-group col-md-6"><fieldset><legend class="h6">Allergies & Dietary Information</legend><p class="text-muted">Select the labels that apply to this product.</p><input type="hidden" name="allergies_present" value="1">@php($selectedAllergies=old('allergies_present')?old('allergy_ids',[]):old('allergy_ids',$product->exists?$product->allergies->pluck('id')->all():[]))<div style="max-height:230px;overflow:auto">@foreach($allergies as $allergy)<label class="d-flex align-items-center mb-2"><input type="checkbox" name="allergy_ids[]" value="{{ $allergy->id }}" @checked(in_array($allergy->id,(array)$selectedAllergies))><img src="{{ $allergy->iconUrl() }}" alt="" width="30" height="30" style="object-fit:contain;margin:0 8px">{{ $allergy->name }}</label>@endforeach</div></fieldset></div></div>

</section>
<section data-dna-panel="packing" class="dna-panel" hidden><p class="text-muted">Pack and minimum-order fields are planning information. They do not change checkout or inventory units.</p><div class="row">
@foreach(['unit_weight_g','size_diameter_cm','pieces_per_pack','pack_weight_kg','uom','cartons'] as $key)@php($field=config('product_fields.'.$key)) @include('admin.products.field') @endforeach
@include('admin.products.dna-fields',['group'=>'packing'])
</div></section>
<section data-dna-panel="files" class="dna-panel" hidden>
@include('admin.products.media',['editing'=>true])
<div class="row">@include('admin.products.dna-fields',['group'=>'files'])</div>
<label for="ingredient-documents">Ingredient / supplier documents (private to admin)</label><input class="form-control" id="ingredient-documents" type="file" name="ingredient_documents[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp"><p class="text-muted">Up to 10 files, 20 MB each. Uploads are saved with Save Product. Verification flags record staff review; they do not give suppliers portal access.</p>
@if($product->exists)@foreach($product->documents as $document)<div class="mb-2"><a href="{{ route('admin.products.document',[$product,$document]) }}">{{ $document->original_name }}</a> <label><input type="checkbox" name="remove_documents[]" value="{{ $document->id }}"> Remove on save</label></div>@endforeach @endif
</section>
<section data-dna-panel="evidence" class="dna-panel" hidden><div class="row">@include('admin.products.dna-fields',['group'=>'evidence'])</div>
<details><summary>Existing imported fields (preserved)</summary><div class="row">
@foreach(config('product_fields') as $key=>$field)
@if(!in_array($key,['premium_marketing_name','qr_code','product_code','supplier','italian_subtitle','original_description','unit_weight_g','size_diameter_cm','pieces_per_pack','pack_weight_kg','uom','cartons','total_selling_price_cad','business_selling_price_cad'])) @include('admin.products.field') @endif
@endforeach
</div></details>
</section>
<div class="mt-3 mb-3" data-product-save-footer><button class="btn btn-primary" type="submit">Save Product Details</button></div>
</form>
@include('admin.products.pricing')
<script src="{{ asset('admin-assets/product-dna.js') }}?v={{ filemtime(public_path('admin-assets/product-dna.js')) }}"></script>
@endsection
