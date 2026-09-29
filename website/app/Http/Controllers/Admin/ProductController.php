<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Allergy;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductMedia;
use App\Services\ProductColumns;
use App\Services\ProductData;
use App\Services\ProductDna;
use App\Services\ProductMediaManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ProductController extends Controller
{
    private function productQuery(Request $r)
    {
        return Product::withCount('documents')->with(['pricingDraft', 'categories', 'inventory', 'media' => fn ($q) => $q->coverImage()])
            ->when($r->filled('q'), function ($q) use ($r) {
                $term = '%'.mb_substr((string) $r->input('q'), 0, 200).'%';
                $q->where(fn ($x) => $x->where('premium_marketing_name', 'like', $term)->orWhere('qr_code', 'like', $term)->orWhere('product_code', 'like', $term)->orWhere('supplier', 'like', $term));
            })
            ->when($r->filled('supplier'), fn ($q) => $q->where('supplier', 'like', '%'.mb_substr((string) $r->input('supplier'), 0, 200).'%'))
            ->when($r->filled('category'), fn ($q) => $q->whereHas('categories', fn ($c) => $c->where('categories.id', $r->integer('category'))))
            ->when(in_array($r->input('active'), ['0', '1'], true), fn ($q) => $q->where('is_active', $r->input('active')))
            ->orderBy('premium_marketing_name')->orderBy('id');
    }

    public function index(Request $r)
    {
        $columns = app(ProductColumns::class);

        return view('admin.products.index', ['columnLabels' => $columns->labels(), 'selectedColumns' => $columns->selected($r), 'columnPresets' => $columns->presets(), 'products' => $this->productQuery($r)->paginate(20)->withQueryString(), 'categories' => Category::orderBy('name')->get()]);
    }

    public function export(Request $r)
    {
        $r->validate(['format' => 'required|in:xlsx,print,pdf']);
        $columns = app(ProductColumns::class);
        $selected = $columns->selected($r);
        if (! $selected) {
            throw ValidationException::withMessages(['columns' => 'Select at least one column to export.']);
        }
        $headers = array_map(fn ($key) => $columns->labels()[$key], $selected);
        $rows = function () use ($r, $columns, $selected) {
            foreach ($this->productQuery($r)->lazy(200) as $p) {
                $values = $columns->values($p);
                yield array_map(fn ($key) => $values[$key] ?? '', $selected);
            }
        };
        if ($r->input('format') !== 'xlsx') {
            return view('admin.products.print', ['headers' => $headers, 'rows' => $rows(), 'pdf' => $r->input('format') === 'pdf']);
        }

        return response()->streamDownload(function () use ($headers, $rows) {
            $path = tempnam(sys_get_temp_dir(), 'mmc-products');
            $writer = new Writer;
            try {
                $writer->openToFile($path);
                $writer->addRow(Row::fromValues($headers));
                foreach ($rows() as $row) {
                    $writer->addRow(new Row(array_map(fn ($value) => is_string($value) ? new StringCell($value, null) : Cell::fromValue($value), $row)));
                }$writer->close();
                readfile($path);
            } finally {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }, 'products-'.now()->format('Y-m-d').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
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
        return view('admin.products.form', ['allergies' => Allergy::orderBy('name')->get(), 'product' => $product, 'categories' => Category::orderBy('sort_order')->orderBy('name')->get()]);
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
        $dnaData = app(ProductDna::class)->validate($r, $product);
        $allergyData = $r->validate(['allergy_ids' => 'sometimes|array|max:100', 'allergy_ids.*' => 'integer|distinct|exists:allergies,id']);
        $allergyIds = $allergyData['allergy_ids'] ?? [];
        $categoryIds = $data['category_ids'];
        unset($data['category_ids']);
        $manager = app(ProductMediaManager::class);
        $manager->validate($r, $product->exists ? $product : null);
        $stored = [];
        $deleted = [];
        try {
            DB::transaction(function () use ($r, $product, $data, $dnaData, $categoryIds, $allergyIds, $manager, &$stored, &$deleted) {
                if ($product->exists) {
                    $product->setRawAttributes(Product::lockForUpdate()->findOrFail($product->id)->getAttributes(), true);
                } else {
                    $product->slug = Str::slug($data['premium_marketing_name']).'-'.Str::uuid();
                }
                if ($product->exists && $r->has('editor_revision') && (int) $r->input('editor_revision') !== (int) $product->editor_revision) {
                    throw ValidationException::withMessages(['product' => 'This product changed. Reload before saving.']);
                }
                $product->fill($data);
                if (isset($dnaData['dna'])) {
                    $product->dna = array_replace($product->dna ?? [], $dnaData['dna']);
                }
                $product->editor_revision = (int) $product->editor_revision + 1;
                $product->save();
                $product->categories()->sync($categoryIds);
                if ($r->has('allergies_present') || $r->has('allergy_ids') || $product->wasRecentlyCreated) {
                    $product->allergies()->sync($allergyIds);
                }
                $product->inventory()->firstOrCreate([], ['quantity_on_hand' => null]);
                $manager->save($r, $product, $stored, $deleted);
                app(ProductDna::class)->saveDocuments($r, $product, $stored, $deleted);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($stored);
            throw $e;
        }
        Storage::disk('local')->delete($deleted);

        return redirect()->route($r->boolean('modal') ? 'admin.products.edit' : 'admin.products.show', [$product, 'modal' => $r->boolean('modal') ? 1 : 0])->with('status', 'Product saved. Images and videos are shown in display order.');
    }

    public function document(Product $product, ProductDocument $document)
    {
        abort_unless($document->product_id === $product->id, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name, ['X-Content-Type-Options' => 'nosniff']);
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
