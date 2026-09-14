<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogueController extends Controller
{
    private function visible()
    {
        return Product::where('is_active', true)->whereHas('categories', fn ($q) => $q->where('is_active', true));
    }

    public function index(Request $r)
    {
        $input = $r->validate(['category' => 'nullable|string|max:200', 'sort' => ['nullable', Rule::in(['featured', 'name', 'price-low', 'price-high'])], 'show' => ['nullable', Rule::in([6, 12, 24])], 'view' => ['nullable', Rule::in(['grid', 'list'])], 'min' => 'nullable|numeric|min:0|max:999999999999', 'max' => 'nullable|numeric|min:0|max:999999999999', 'page' => 'nullable|integer|min:1|max:1000000']);
        $min = $input['min'] ?? null;
        $max = $input['max'] ?? null;
        if ($min !== null && $max !== null && $max < $min) {
            throw ValidationException::withMessages(['max' => 'Maximum price must be at least the minimum price.']);
        }
        $categories = Category::where('is_active', true)->withCount(['products' => fn ($q) => $q->where('is_active', true)])->orderBy('sort_order')->orderBy('name')->get();
        $category = $input['category'] ?? '';
        $selected = $categories->firstWhere('slug', $category);
        // Keep existing home/header links working with admin-generated category slugs.
        if (! $selected && $category) {
            $aliases = ['cakes' => ['cake', 'cakes'], 'pastries' => ['pastry', 'pastries'], 'cannoli' => ['cannoli']];
            $selected = $categories->first(fn ($c) => in_array(mb_strtolower($c->name), $aliases[$category] ?? [], true));
            if ($selected) {
                $category = $selected->slug;
            }
        }
        $sort = $input['sort'] ?? 'featured';
        $perPage = (int) ($input['show'] ?? 12);
        $view = $input['view'] ?? 'grid';
        $allCount = $this->visible()->count();
        $query = $this->visible();
        if ($category) {
            $selected ? $query->whereHas('categories', fn ($q) => $q->where('categories.id', $selected->id)) : $query->whereRaw('1=0');
        }
        if ($min !== null) {
            $query->where('total_selling_price_cad', '>=', $min);
        }
        if ($max !== null) {
            $query->where('total_selling_price_cad', '<=', $max);
        }
        if ($sort === 'name') {
            $query->orderBy('premium_marketing_name');
        } elseif (in_array($sort, ['price-low', 'price-high'], true)) {
            $query->orderByRaw('total_selling_price_cad IS NULL')->orderBy('total_selling_price_cad', $sort === 'price-low' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('created_at');
        }
        $total = (clone $query)->count();
        $page = min((int) ($input['page'] ?? 1), max(1, (int) ceil($total / $perPage)));
        $products = $query->orderBy('id')->with(['media' => fn ($q) => $q->where('kind', 'image')->limit(1)])->paginate($perPage, ['*'], 'page', $page)->appends($r->except('page'));
        $data = compact('products', 'categories', 'category', 'sort', 'perPage', 'view', 'min', 'max', 'total', 'page', 'allCount');
        if ($r->expectsJson()) {
            return response()->json(['html' => view('partials.catalogue-grid', $data)->render(), 'total' => $total]);
        }

        return view('pages.product-grid', $data);
    }

    public function show(string $slug)
    {
        $product = $this->visible()->where('slug', $slug)->with(['media', 'categories' => fn ($q) => $q->where('is_active', true)])->firstOrFail();

        return view('pages.catalogue-product', compact('product'));
    }

    public function media(Product $product, ProductMedia $media)
    {
        abort_unless($media->product_id === $product->id && $product->is_active && $product->categories()->where('is_active', true)->exists(), 404);
        abort_unless(Storage::disk('local')->exists($media->path), 404);

        return response()->file(Storage::disk('local')->path($media->path),['Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-cache']);
    }
}
