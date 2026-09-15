<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ProductImport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return view('admin.categories.index', ['categories' => Category::withCount('products')->orderBy('sort_order')->orderBy('name')->paginate(20)]);
    }

    public function create()
    {
        return view('admin.categories.form', ['category' => new Category(['is_active' => true, 'show_to_customer' => true, 'sort_order' => 0])]);
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', compact('category'));
    }

    public function store(Request $r)
    {
        $data = $this->data($r);
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(6));
        $this->saveCategory($r, new Category, $data);

        return redirect()->route('admin.categories.index')->with('status', 'Category created.');
    }

    public function update(Request $r, Category $category)
    {
        $data = $this->data($r, $category);
        if ($category->slug === 'general') {
            unset($data['name']);
            $data['is_active'] = true;
        }$this->saveCategory($r, $category, $data);

        return redirect()->route('admin.categories.index')->with('status', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        $path = DB::transaction(function () use ($category) {
            $category = Category::lockForUpdate()->findOrFail($category->id);
            if ($category->is_protected || $category->slug === 'general') {
                throw \Illuminate\Validation\ValidationException::withMessages(['category' => 'This category is protected from deletion.']);
            }
            if ($category->products()->exists() || ProductImport::where('category_id', $category->id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['category' => 'Categories used by products or imports cannot be deleted. Reassign products or deactivate the category instead.']);
            }
            $path = $category->image_path;
            $category->delete();
            return $path;
        });
        if ($path) Storage::disk('local')->delete($path);

        return back()->with('status', 'Category deleted.');
    }

    public function image(Category $category)
    {
        abort_unless($category->is_active || auth()->user()?->hasAdminPermission('categories.view'), 404);
        abort_unless($category->image_path && Storage::disk('local')->exists($category->image_path), 404);
        return response()->file(Storage::disk('local')->path($category->image_path), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-cache']);
    }

    private function saveCategory(Request $r, Category $category, array $data): void
    {
        $path = null;
        $old = null;
        try {
            if ($r->hasFile('image')) {
                $path = $r->file('image')->store('categories', 'local');
                if (!$path) throw new \RuntimeException('Unable to store category image.');
                $data['image_path'] = $path;
            }
            DB::transaction(function () use ($category, $data, &$old) {
                if ($category->exists) {
                    $category->setRawAttributes(Category::lockForUpdate()->findOrFail($category->id)->getAttributes(), true);
                    $old = $category->image_path;
                }
                $category->fill($data)->save();
            });
        } catch (\Throwable $e) {
            if ($path) Storage::disk('local')->delete($path);
            throw $e;
        }
        if ($path && $old) Storage::disk('local')->delete($old);
    }

    private function data(Request $r, ?Category $category = null): array
    {
        $data = $r->validate(['is_protected' => $r->user()->hasAdminPermission('categories.manage') ? 'sometimes|required|boolean' : 'prohibited', 'show_to_customer' => 'sometimes|required|boolean', 'image' => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:5120', 'name' => ['required', 'string', 'max:120', Rule::unique('categories')->ignore($category?->id)], 'description' => 'nullable|string|max:5000', 'is_active' => 'required|boolean', 'sort_order' => 'required|integer|min:0|max:100000']);
        unset($data['image']);
        if ($category?->slug === 'general') $data['is_protected'] = true;
        return $data;
    }
}
