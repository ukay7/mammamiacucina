<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\ProductData;
use App\Services\ProductMediaManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $r)
    {
        $products = Product::with(['categories', 'inventory', 'media' => fn ($q) => $q->where('kind', 'image')->limit(1)])->when($r->filled('q'), fn ($q) => $q->where(function ($q) use ($r) {

            $term = '%'.mb_substr($r->string('q'), 0, 200).'%';
            $q->where('premium_marketing_name', 'like', $term)->orWhere('qr_code', 'like', $term)->orWhere('product_code', 'like', $term)->orWhere('supplier', 'like', $term);
        }))->when($r->filled('category'), fn ($q) => $q->whereHas('categories', fn ($c) => $c->where('categories.id', $r->integer('category'))))->when(in_array($r->input('active'), ['0', '1'], true), fn ($q) => $q->where('is_active', $r->input('active')))->orderBy('premium_marketing_name')->paginate(20)->withQueryString();

        return view('admin.products.index', ['products' => $products, 'categories' => Category::orderBy('name')->get()]);
    }

    public function create()
    {
        return $this->form(new Product(['category_id' => Category::where('slug', 'general')->value('id'), 'is_active' => true]));
    }

    public function show(Product $product)
    {
        return view('admin.products.show', ['product' => $product->load(['categories', 'inventory'])]);
    }

    public function edit(Product $product)
    {
        return $this->form($product);
    }

    private function form(Product $product)
    {
        return view('admin.products.form', ['allergies' => \App\Models\Allergy::orderBy('name')->get(), 'product' => $product, 'categories' => Category::orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function store(Request $r)
    {
        return $this->saveProduct($r, new Product);
    }

    public function update(Request $r, Product $product)
    {
        return $this->saveProduct($r, $product);
    }

    private function saveProduct(Request $r, Product $product)
    {
        $data = $this->data($r, $product->exists ? $product : null);
        $allergyData=$r->validate(['allergy_ids'=>'sometimes|array|max:100','allergy_ids.*'=>'integer|distinct|exists:allergies,id']);
        $allergyIds=$allergyData['allergy_ids']??[];
        $categoryIds = $data['category_ids'];
        unset($data['category_ids']);
        $manager = app(ProductMediaManager::class);
        $manager->validate($r, $product->exists ? $product : null);
        $stored = [];
        $deleted = [];
        try {
            DB::transaction(function () use ($r, $product, $data, $categoryIds, $allergyIds, $manager, &$stored, &$deleted) {
                if ($product->exists) {
                    $product->setRawAttributes(Product::lockForUpdate()->findOrFail($product->id)->getAttributes(), true);
                } else {
                    $product->slug = Str::slug($data['premium_marketing_name']).'-'.Str::uuid();
                }
                $product->fill($data);
                $product->save();
                $product->categories()->sync($categoryIds);
                if($r->has('allergies_present') || $r->has('allergy_ids') || $product->wasRecentlyCreated) $product->allergies()->sync($allergyIds);
                $product->inventory()->firstOrCreate([], ['quantity_on_hand' => null]);
                $manager->save($r, $product, $stored, $deleted);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($stored);
            throw $e;
        }
        Storage::disk('local')->delete($deleted);

        return redirect()->route($r->boolean('modal') ? 'admin.products.edit' : 'admin.products.show', [$product, 'modal' => $r->boolean('modal') ? 1 : 0])->with('status', 'Product saved. Images and videos are shown in display order.');
    }

    public function media(Product $product, ProductMedia $media)
    {
        abort_unless($media->product_id === $product->id, 404);
        abort_unless(Storage::disk('local')->exists($media->path), 404);

        return response()->file(Storage::disk('local')->path($media->path), ['Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=300']);
    }

    public function bulkCategory(Request $r)
    {
        $data = $r->validate(['product_ids' => 'required|array|min:1|max:100', 'product_ids.*' => 'integer|distinct|exists:products,id', 'category_id' => 'required|exists:categories,id', 'mode' => 'nullable|in:add,replace']);
        DB::transaction(function () use ($data) {
            foreach (Product::whereIn('id', $data['product_ids'])->orderBy('id')->lockForUpdate()->get() as $product) {
                if (($data['mode'] ?? 'replace') === 'add') {
                    $product->categories()->syncWithoutDetaching([$data['category_id']]);
                } else {
                    $product->update(['category_id' => $data['category_id']]);
                    $product->categories()->sync([$data['category_id']]);
                }
            }
        });

        return back()->with('status', 'Product categories updated.');
    }

    private function data(Request $r, ?Product $product = null): array
    {
        // Accept the previous single-category payload for existing callers.
        if (! $r->has('category_ids') && $r->has('category_id')) {
            $r->merge(['category_ids' => [$r->input('category_id')]]);
        }
        $data = $r->validate([...ProductData::rules($product?->id), 'category_ids' => 'required|array|min:1|max:100', 'category_ids.*' => 'required|integer|distinct|exists:categories,id', 'is_active' => 'required|boolean'], [], ProductData::attributes());
        $data['category_ids'] = array_map('intval', $data['category_ids']);
        $data['category_id'] = in_array($product?->category_id, $data['category_ids'], true) ? $product->category_id : $data['category_ids'][0];

        return $data;
    }
}
