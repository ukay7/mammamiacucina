<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ProductImport;
use App\Services\CatalogueImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ProductImportController extends Controller
{
    public function index()
    {
        return view('admin.imports.index', ['imports' => ProductImport::with('uploader')->latest()->paginate(15)]);
    }

    public function store(Request $r, CatalogueImporter $service)
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
        $rows = $import->rows()->when($r->input('rows') !== 'all', fn ($q) => $q->where('status', '!=', 'reference'))->paginate(25)->withQueryString();

        return view('admin.imports.show', compact('import', 'rows'));
    }

    public function commit(ProductImport $import, CatalogueImporter $service)
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
        return response()->streamDownload(function () {
            $w = new Writer;
            $path = tempnam(sys_get_temp_dir(), 'mmc');
            try {
                $w->openToFile($path);
                $w->addRow(Row::fromValues(array_values(array_map(fn ($field) => $field[0], config('product_fields')))));
                $w->close();
                readfile($path);
            } finally {
                @unlink($path);
            }
        }, 'product-import-template.xlsx');
    }
}
