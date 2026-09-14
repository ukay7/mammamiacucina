<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use ZipArchive;

class CatalogueImporter
{
    public function preview(string $path, string $filename, ?int $userId, int $categoryId, ?string $storedPath = null): ProductImport
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'Upload a valid .xlsx workbook.']);
        }
        $size = 0;
        try {
            if ($zip->numFiles > 2000) {
                throw new \RuntimeException('Too many workbook entries.');
            }for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $size += $entry['size'];
                if ($size > 50 * 1024 * 1024) {
                    throw new \RuntimeException('Workbook expands beyond the 50 MB limit.');
                }
            }
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        } finally {
            $zip->close();
        }
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);

        return DB::transaction(function () use ($reader, $path, $filename, $userId, $categoryId, $storedPath) {
            $import = ProductImport::create(['original_filename' => $filename, 'uploaded_by' => $userId, 'category_id' => $categoryId, 'stored_path' => $storedPath]);
            $found = false;
            $total = 0;
            $failed = 0;
            $skipped = 0;
            $scanned = 0;
            try {
                $reader->open($path);
                foreach ($reader->getSheetIterator() as $sheet) {
                    $header = null;
                    foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                        if (++$scanned > 10000) {
                            throw ValidationException::withMessages(['file' => 'Please upload no more than 10,000 worksheet rows.']);
                        }
                        $values = [];
                        $formulas = [];
                        foreach ($row->getCells() as $index => $cell) {
                            $v = $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell->getValue();
                            if ($cell instanceof FormulaCell) {
                                $formulas[$index] = $cell->getValue();
                            }$values[$index] = $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (is_scalar($v) ? trim((string) $v) : null);
                        }
                        if (! array_filter($values, fn ($v) => $v !== null && $v !== '')) {
                            continue;
                        }
                        $original = ['cells' => $values, 'formulas' => $formulas];
                        if ($header === null) {
                            $normalized = array_map(fn ($v) => Str::lower(trim((string) $v)), $values);
                            if (in_array('qr code', $normalized, true) && in_array('premium marketing name', $normalized, true)) {
                                $header = [];
                                foreach (config('product_fields') as $key => $field) {
                                    $matches = array_keys($normalized, Str::lower($field[0]), true);
                                    if (count($matches) !== 1) {
                                        throw ValidationException::withMessages(['file' => 'Missing or duplicate column: '.$field[0]]);
                                    }$header[$key] = $matches[0];
                                }$found = true;
                            }$import->rows()->create(['source_sheet' => $sheet->getName(), 'row_number' => $rowNumber, 'original_data' => $original, 'status' => 'reference']);
                            $skipped++;

                            continue;
                        }
                        $data = [];
                        foreach ($header as $key => $index) {
                            $v = $values[$index] ?? null;
                            $data[$key] = $v === '' ? null : $v;
                        }
                        // Notes and links below the catalogue have no product identity. Retain them separately.
                        if (! $data['supplier'] && ! $data['product_code'] && ! $data['qr_code'] && ! $data['premium_marketing_name']) {
                            $import->rows()->create(['source_sheet' => $sheet->getName(), 'row_number' => $rowNumber, 'original_data' => $original, 'status' => 'reference']);
                            $skipped++;

                            continue;
                        }
                        $total++;
                        $import->rows()->create(['source_sheet' => $sheet->getName(), 'row_number' => $rowNumber, 'original_data' => $original, 'normalized_data' => $data, 'status' => 'ready']);
                    }
                }
            } finally {
                $reader->close();
            }
            if (! $found || ! $total) {
                throw ValidationException::withMessages(['file' => 'No product table found. Use the 23 original catalogue headers.']);
            }
            $import->update(['total_rows' => $total, 'failed_count' => $failed, 'skipped_count' => $skipped, 'status' => $failed ? 'invalid' : 'preview']);

            return $this->revalidate($import);
        });
    }

    public function revalidate(ProductImport $import): ProductImport
    {
        return DB::transaction(function () use ($import) {
            $import = ProductImport::lockForUpdate()->findOrFail($import->id);
            if ($import->status === 'completed') {
                return $import;
            }
            $rows = $import->rows()->whereNotNull('normalized_data')->orderBy('id')->get();
            $plan = (new CatalogueImportPlan)->build($rows, new ProductImportMatcher(Product::all()));
            $failed = 0;
            foreach ($plan as $entry) {
                $errors = $entry['errors'];
                if ($errors) {
                    $failed++;
                }
                $entry['row']->update(['product_id' => $entry['product']?->id, 'status' => $entry['action'], 'errors' => $errors ?: null]);
            }
            $import->update(['failed_count' => $failed, 'unmatched_count' => count(array_filter($plan, fn ($e) => $e['action'] === 'skipped')), 'unchanged_count' => count(array_filter($plan, fn ($e) => $e['action'] === 'unchanged')), 'status' => $failed ? 'invalid' : 'preview']);

            return $import;
        });
    }

    public function commit(ProductImport $import): ProductImport
    {
        return DB::transaction(function () use ($import) {
            $import = ProductImport::lockForUpdate()->findOrFail($import->id);
            if ($import->status === 'completed') {
                return $import;
            }
            if ($import->status !== 'preview' || $import->failed_count) {
                throw ValidationException::withMessages(['import' => 'Fix the invalid rows and upload again before importing.']);
            }
            // Serialize catalogue imports even when they target different categories.
            Category::where('slug', 'general')->lockForUpdate()->firstOrFail();
            $category = Category::lockForUpdate()->findOrFail($import->category_id);
            if (! $category->is_active) {
                throw ValidationException::withMessages(['import' => 'The selected category is inactive. Upload again using an active category.']);
            }
            $matcher = new ProductImportMatcher(Product::lockForUpdate()->get());
            $plan = (new CatalogueImportPlan)->build($import->rows()->whereNotNull('normalized_data')->orderBy('id')->get(), $matcher);
            foreach ($plan as $entry) {
                if ($entry['errors']) {
                    throw ValidationException::withMessages(['import' => $entry['row']->source_sheet.' row '.$entry['row']->row_number.': '.implode(' ', $entry['errors'])]);
                }
            }
            $updated = 0;
            $created = 0;
            $unchanged = 0;
            foreach ($plan as $entry) {
                $row = $entry['row'];
                $product = $entry['product'];
                if (! $product) {
                    $product = Product::create([...$row->normalized_data, 'category_id' => $category->id, 'is_active' => true, 'slug' => Str::slug($row->normalized_data['premium_marketing_name']).'-'.Str::uuid()]);
                    $product->inventory()->create(['quantity_on_hand' => null]);
                    $created++;
                    $row->update(['product_id' => $product->id, 'status' => 'created', 'errors' => null]);

                    continue;
                }
                if ($entry['action'] === 'unchanged') {
                    $unchanged++;
                    $row->update(['product_id' => $product->id, 'status' => 'unchanged', 'errors' => null]);

                    continue;
                }
                $product->fill(array_filter($row->normalized_data, fn ($value) => $value !== null && $value !== ''));
                $product->save();
                $row->update(['product_id' => $product->id, 'status' => 'updated', 'errors' => null]);
                $updated++;
            }
            $import->update(['created_count' => $created, 'updated_count' => $updated, 'unmatched_count' => 0, 'unchanged_count' => $unchanged, 'status' => 'completed']);

            return $import;
        }, 3);
    }
}
