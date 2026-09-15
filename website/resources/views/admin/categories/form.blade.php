@extends('admin.layout')
@section('title',$category->exists?'Edit Category':'Add Category')
@section('content')
<div class="panel"><div class="panel-container show"><div class="panel-content"><form method="post" enctype="multipart/form-data" action="{{ $category->exists?route('admin.categories.update',$category):route('admin.categories.store') }}">@csrf @if($category->exists)@method('PUT')@endif
<div class="form-group"><label for="name">Category name</label><input class="form-control" id="name" name="name" value="{{ old('name',$category->name) }}" maxlength="120" required @readonly($category->slug==='general')></div>
<div class="form-group"><label for="image">Category image</label>
@if($category->image_path)<div class="mb-3"><img src="{{ route('category.image',$category) }}" alt="{{ $category->name }}" style="width:140px;height:140px;object-fit:contain;border:1px solid #dfc69c;border-radius:8px;background:#fff"></div>@endif
<input class="form-control" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" aria-describedby="category-image-help">
<small id="category-image-help" class="form-text text-muted">Recommended: 800 × 800 px (square). JPG, PNG or WebP, maximum 5 MB. Leave empty to keep the current image.</small></div>
<div class="form-group"><label for="description">Description</label><textarea class="form-control" id="description" name="description" rows="3">{{ old('description',$category->description) }}</textarea></div>
<div class="form-group">
@if($category->slug==='general')<p class="text-muted"><i class="fas fa-lock" aria-hidden="true"></i> This default category is permanently protected.</p>
@elseif(auth()->user()->hasAdminPermission('categories.manage'))
<input type="hidden" name="is_protected" value="0">
<div class="custom-control custom-switch"><input class="custom-control-input" type="checkbox" role="switch" id="is_protected" name="is_protected" value="1" @checked(old('is_protected',$category->is_protected))><label class="custom-control-label" for="is_protected">Protect this category from deletion</label></div>
<small class="form-text text-muted">For homepage sections and reusable collections. You can still edit the category and its products. Admins with category management access can change protection.</small>
@elseif($category->is_protected)<p class="text-muted"><i class="fas fa-lock" aria-hidden="true"></i> Protected from deletion. Admins with category management access can change protection.</p>@endif
</div>
<div class="form-group"><label for="show_to_customer">Show to Customer</label><select class="form-control" id="show_to_customer" name="show_to_customer"><option value="1" @selected(old('show_to_customer',$category->show_to_customer ?? true)==1)>Yes</option><option value="0" @selected(old('show_to_customer',$category->show_to_customer ?? true)==0)>No</option></select><small class="form-text text-muted">Choose No for internal collections. The category is hidden from customer menus, filters and product category labels. Its products remain available.</small></div>
<div class="row"><div class="form-group col-md-6"><label for="sort_order">Display order</label><input class="form-control" type="number" min="0" id="sort_order" name="sort_order" value="{{ old('sort_order',$category->sort_order) }}" required></div><div class="form-group col-md-6"><label for="is_active">Status</label><select class="form-control" id="is_active" name="is_active"><option value="1" @selected(old('is_active',$category->is_active)==1)>Active</option>@if($category->slug!=='general')<option value="0" @selected(old('is_active',$category->is_active)==0)>Inactive</option>@endif</select></div></div>
<button class="btn btn-primary">Save Category</button> <a class="btn btn-outline-primary" href="{{ route('admin.categories.index') }}">Back to Categories</a></form>
@if($category->exists && $category->slug!=='general' && !$category->is_protected)<form class="mt-4" method="post" action="{{ route('admin.categories.destroy',$category) }}" data-confirm="Delete this category?">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete Category</button><small class="d-block mt-2">Categories used by products or imports must be kept for history. You can deactivate them.</small></form>@endif</div></div></div>
@endsection