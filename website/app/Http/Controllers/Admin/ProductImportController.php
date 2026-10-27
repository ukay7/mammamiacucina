<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ProductImport;
use App\Services\DnaCatalogueImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductImportController extends Controller
{
    public function index()
    {
        return view('admin.imports.index', ['imports' => ProductImport::with('uploader')->latest()->paginate(15)]);
    }

    public function store(Request $r, DnaCatalogueImporter $service)
    {
        $r->validate(['file' => 'required|file|mimes:xlsx|max:10240']);
        $category = Category::where('slug', 'general')->where('is_active', true)->firstOrFail();
        $file = $r->file('file');
        $path = $file->store('catalogue-imports', 'local');
        try {
            $import = $service->preview(Storage::disk('local')->path($path), $file->getClientOriginalName(), $r->user()->id, $category->id, $path);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            if ($e instanceof ValidationException) {
                throw $e;
            }report($e);
            throw ValidationException::withMessages(['file' => 'Unable to read this workbook. Please save it as a valid .xlsx file and try again.']);
        }

        return redirect()->route('admin.imports.show', $import);
    }

    public function show(Request $r, ProductImport $import)
    {
        $dna = ($import->rows()->first()?->normalized_data['format'] ?? '') === 'dna-v1';
        $query = $import->rows()->when($r->input('rows') !== 'all', fn ($q) => $q->where('status', '!=', 'reference'));
        if ($dna) {
            $r->validate(['search' => 'nullable|string|max:120', 'status' => 'nullable|in:new,updates,unchanged,invalid', 'per_page' => 'nullable|in:25,50,100']);
            $search = trim((string) $r->input('search'));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    foreach (['Internal code', 'Mamma Mia name', 'Supplier SKU'] as $field) {
                        $q->orWhere('normalized_data->source->'.$field, 'like', '%'.$search.'%');
                    }
                    $q->orWhere('normalized_data->fields->premium_marketing_name', 'like', '%'.$search.'%');
                });
            }
            $statuses = ['new' => ['insert', 'created'], 'updates' => ['update', 'updated'], 'unchanged' => ['unchanged'], 'invalid' => ['invalid']];
            if (isset($statuses[$r->input('status')])) {
                $query->whereIn('status', $statuses[$r->input('status')]);
            }
        }
        $rows = $query->orderBy('id')->paginate($dna ? (int) ($r->input('per_page') ?: 25) : 25)->withQueryString();

        return view($dna ? 'admin.imports.dna-review' : 'admin.imports.show', compact('import', 'rows'));
    }

    public function commit(ProductImport $import, DnaCatalogueImporter $service)
    {
        $result = $service->commit($import);

        return redirect()->route('admin.imports.show', $import)->with('status', "Import complete: {$result->created_count} created, {$result->updated_count} updated, {$result->unchanged_count} unchanged.");
    }

    public function download(ProductImport $import)
    {
        abort_unless($import->stored_path && Storage::disk('local')->exists($import->stored_path), 404);

        return Storage::disk('local')->download($import->stored_path, basename($import->original_filename));
    }

    public function template()
    {
        return response()->download(app(\App\Services\ProductImportTemplate::class)->path(), 'product-import-template.xlsx')->deleteFileAfterSend(true);
    }

    public function cancel(ProductImport $import, DnaCatalogueImporter $service)
    {
        $service->cancel($import);

        return redirect()->route('admin.imports.show', $import)->with('status', 'Import cancelled. No product data was changed.');
    }
}
