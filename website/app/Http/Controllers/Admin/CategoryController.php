<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ProductImport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return view('admin.categories.index', ['categories' => Category::withCount('products')->orderBy('sort_order')->orderBy('name')->paginate(20)]);
    }

    public function create()
    {
        return view('admin.categories.form', ['category' => new Category(['is_active' => true, 'sort_order' => 0])]);
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', compact('category'));
    }

    public function store(Request $r)
    {
        $data = $this->data($r);
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(6));
        Category::create($data);

        return redirect()->route('admin.categories.index')->with('status', 'Category created.');
    }

    public function update(Request $r, Category $category)
    {
        $data = $this->data($r, $category);
        if ($category->slug === 'general') {
            unset($data['name']);
            $data['is_active'] = true;
        }$category->update($data);

        return redirect()->route('admin.categories.index')->with('status', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        if ($category->slug === 'general' || $category->products()->exists() || ProductImport::where('category_id', $category->id)->exists()) {
            return back()->withErrors(['category' => 'General and categories used by products or imports cannot be deleted. Reassign products or deactivate the category instead.']);
        }$category->delete();

        return back()->with('status', 'Category deleted.');
    }

    private function data(Request $r, ?Category $category = null): array
    {
        return $r->validate(['name' => ['required', 'string', 'max:120', Rule::unique('categories')->ignore($category?->id)], 'description' => 'nullable|string|max:5000', 'is_active' => 'required|boolean', 'sort_order' => 'required|integer|min:0|max:100000']);
    }
}
