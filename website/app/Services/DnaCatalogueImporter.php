<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductPricingDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use ZipArchive;

class DnaCatalogueImporter
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }

    private function code(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    public function fingerprint(?Product $product): ?string
    {
        return $product ? hash('sha256', json_encode([$product->getAttributes(), $product->pricingDraft?->getAttributes()])) : null;
    }

    private function missingFormulaCaches(ZipArchive $zip): array
    {
        $xml = function (string $part) use ($zip): \DOMXPath {
            $text = $zip->getFromName($part);
            if ($text === false || stripos($text, '<!DOCTYPE') !== false) {
                $this->fail('Invalid workbook XML. Save the workbook again in Excel.');
            }
            $document = new \DOMDocument;
            if (! @$document->loadXML($text, LIBXML_NONET)) {
                $this->fail('Invalid workbook XML. Save the workbook again in Excel.');
            }

            return new \DOMXPath($document);
        };
        $relationships = [];
        foreach ($xml('xl/_rels/workbook.xml.rels')->query('//*[local-name()="Relationship"]') as $rel) {
            $relationships[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
        }
        $missing = [];
        foreach ($xml('xl/workbook.xml')->query('//*[local-name()="sheet"]') as $sheet) {
            $id = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            $target = $relationships[$id] ?? '';
            if (! $target) {
                continue;
            }
            $part = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            foreach ($xml($part)->query('//*[local-name()="c"][*[local-name()="f"]]') as $cell) {
                $value = $cell->getElementsByTagName('v')->item(0);
                if ($value && trim($value->textContent) !== '') {
                    continue;
                }
                if (preg_match('/^([A-Z]+)(\d+)$/', $cell->getAttribute('r'), $match)) {
                    $column = 0;
                    foreach (str_split($match[1]) as $letter) {
                        $column = $column * 26 + ord($letter) - 64;
                    }
                    $missing[$sheet->getAttribute('name')][(int) $match[2]][$column - 1] = true;
                }
            }
        }

        return $missing;
    }

    public function preview(string $path, string $filename, ?int $userId, int $categoryId, ?string $storedPath = null): ProductImport
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->fail('Upload a valid XLSX workbook.');
        }
        try {
            $size = 0;
            if ($zip->numFiles > 2000) {
                $this->fail('Workbook contains too many entries.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $size += $zip->statIndex($i)['size'];
                if ($size > 50 * 1024 * 1024) {
                    $this->fail('Workbook expands beyond the 50 MB limit.');
                }
            }
            // OpenSpout turns a missing numeric formula cache into zero; retain that distinction.
            $missingCaches = $this->missingFormulaCaches($zip);
        } finally {
            $zip->close();
        }

        return DB::transaction(function () use ($path, $filename, $userId, $categoryId, $storedPath, $missingCaches) {
            $import = ProductImport::create(['original_filename' => $filename, 'uploaded_by' => $userId, 'category_id' => $categoryId, 'stored_path' => $storedPath]);
            $products = Product::with('pricingDraft', 'category')->get()->groupBy(fn ($p) => $this->code($p->qr_code));
            $options = new Options;
            $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
            $reader = new Reader($options);
            $seen = [];
            $scanned = 0;
            $total = 0;
            try {
                $reader->open($path);
                foreach ($reader->getSheetIterator() as $sheet) {
                    $header = null;
                    foreach ($sheet->getRowIterator() as $number => $row) {
                        if (++$scanned > 10000) {
                            $this->fail('Upload no more than 10,000 worksheet rows.');
                        }
                        $values = [];
                        $badCells = array_keys($missingCaches[$sheet->getName()][$number] ?? []);
                        foreach ($row->getCells() as $i => $cell) {
                            $v = $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell->getValue();
                            if (($cell instanceof FormulaCell && $v === null) || (is_string($v) && preg_match('/^#(REF!|VALUE!|DIV\/0!|N\/A|NAME\?|NUM!|SPILL!|NULL!)/', $v))) {
                                $badCells[] = $i;
                            }
                            $values[$i] = is_scalar($v) ? trim((string) $v) : null;
                        }
                        if (! array_filter($values, fn ($v) => $v !== null && $v !== '')) {
                            continue;
                        }
                        if ($header === null) {
                            $lower = array_map(fn ($v) => $this->code($v), $values);
                            if (! in_array('internal code', $lower, true)) {
                                continue;
                            }
                            $header = [];
                            foreach (config('product_import.headers') as $label) {
                                $matches = array_keys($lower, $this->code($label), true);
                                if (count($matches) !== 1) {
                                    $this->fail('Missing or duplicate column: '.$label.'. Download the Product DNA template.');
                                }
                                $header[$label] = $matches[0];
                            }

                            continue;
                        }
                        $source = [];
                        $errors = [];
                        foreach ($header as $label => $i) {
                            $source[$label] = $values[$i] ?? '';
                            if (in_array($i, $badCells, true) && ! in_array($label, config('product_import.ignored'), true)) {
                                $errors[] = "$label: invalid or unsaved formula. Recalculate and save in Excel.";
                            }
                        }
                        if (! array_filter(array_diff_key($source, array_flip(config('product_import.ignored'))), fn ($v) => $v !== null && $v !== '')) {
                            continue;
                        }
                        $total++;
                        $code = $this->code($source['Internal code']);
                        $matches = $products->get($code, collect());
                        $product = $matches->count() === 1 ? $matches->first() : null;
                        if ($code === '') {
                            $errors[] = 'Internal code is required.';
                        }
                        if (isset($seen[$code])) {
                            $errors[] = 'Duplicate Internal code in this workbook (first at '.$seen[$code].').';
                        }
                        $seen[$code] = $sheet->getName().' row '.$number;
                        if ($matches->count() > 1) {
                            $errors[] = 'Multiple existing products use this Internal code. Resolve the conflict first.';
                        }
                        $plan = ['format' => 'dna-v1', 'source' => $source];
                        try {
                            $plan += $this->plan($source, $product, $categoryId);
                        } catch (ValidationException $e) {
                            $errors = array_merge($errors, $e->validator->errors()->all());
                        }
                        $action = $errors ? 'invalid' : (! $product ? 'insert' : ($plan['changed'] ? 'update' : 'unchanged'));
                        $import->rows()->create(['source_sheet' => $sheet->getName(), 'row_number' => $number, 'original_data' => ['cells' => $values], 'normalized_data' => $plan, 'product_id' => $product?->id, 'status' => $action, 'errors' => $errors ?: null]);
                    }
                }
            } finally {
                $reader->close();
            }
            if (! $total) {
                $this->fail('No product rows found. Use the 38-column Product DNA template and enter an Internal code for each product.');
            }
            $failed = $import->rows()->where('status', 'invalid')->count();
            $import->update(['total_rows' => $total, 'failed_count' => $failed, 'unchanged_count' => $import->rows()->where('status', 'unchanged')->count(), 'status' => $failed ? 'invalid' : 'preview']);

            return $import;
        });
    }

    private function number(mixed $value, string $label): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0 || (float) $value > 1000000000) {
            $this->fail("$label must be a number between 0 and 1,000,000,000.");
        }

        return (float) $value;
    }

    private function same(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.000000005;
        }

        return (string) $a === (string) $b;
    }

    private function plan(array $source, ?Product $product, int $categoryId): array
    {
        $old = $this->current($product);
        $effective = $old;
        foreach ($source as $label => $v) {
            if (in_array($label, config('product_import.ignored'), true) || $v === '' || $v === null) {
                continue;
            }
            if (mb_strlen($v) > (in_array($label, ['Review notes', 'Product name on source']) ? 20000 : 255)) {
                $this->fail("$label is too long.");
            }
            $effective[$label] = $v;
        }
        foreach (['Internal code', 'Mamma Mia name', 'Supplier'] as $label) {
            if (empty($effective[$label])) {
                $this->fail("$label is required.");
            }
        }
        foreach (['Weight per piece, g', 'Pieces per carton', 'Net carton weight, kg', 'Supplier quoted price', 'Price per piece, EUR', 'After discount, EUR', 'CAD per 1 EUR', 'Product cost, CAD / piece', 'Freight, CAD / carton', 'Freight, CAD / piece', 'Other cost, CAD / piece', 'Total unit cost, CAD', 'Profit setting %', 'Selling price / piece, CAD', 'Selling price / carton, CAD'] as $label) {
            if (($effective[$label] ?? '') !== '' && $effective[$label] !== null) {
                $effective[$label] = $this->number($effective[$label], $label);
            }
        }
        if (($source['Calculated margin %'] ?? '') !== '' && (! is_numeric($source['Calculated margin %']) || ! is_finite((float) $source['Calculated margin %']))) {
            $this->fail('Calculated margin % must be numeric.');
        }
        $basis = strtolower((string) ($effective['Supplier price basis'] ?? ''));
        $sale = strtolower((string) ($effective['Sell as'] ?? ''));
        if (! in_array($sale, ['piece', 'carton'], true)) {
            $this->fail('Sell as must be Piece or Carton.');
        }
        $mode = strtolower((string) ($effective['Pricing method'] ?? ''));
        if (! in_array($mode, ['markup on cost', 'markup'], true)) {
            $this->fail('Pricing method must be Markup on cost for this business / individual template.');
        }
        $inputs = $product?->pricingDraft?->inputs ?? [];
        foreach (config('product_import.inputs') as $label => $key) {
            $inputs[$key] = $effective[$label] ?? null;
        }
        $inputs = array_replace($inputs, ['purchase_basis' => $basis, 'currency' => strtoupper((string) $inputs['currency']), 'discount' => str_replace(['%', ' '], '', (string) $inputs['discount']), 'profit_mode' => 'markup', 'profit_percent' => 150, 'carton_status' => 'source']);
        $result = app(ProductPricing::class)->calculate($inputs);
        $pieces = (int) $inputs['pieces_per_carton'];
        $dna = $product?->dna ?? [];
        $fields = [];
        foreach (config('product_import.columns') as $label => $key) {
            if (($effective[$label] ?? '') !== '' && $effective[$label] !== null) {
                $fields[$key] = $effective[$label];
            }
        }
        foreach (config('product_import.dna') as $label => $key) {
            if (($effective[$label] ?? '') !== '' && $effective[$label] !== null) {
                $dna[$key] = $effective[$label];
            }
        }
        $dna['sale_unit'] = $sale;
        $gluten = strtolower((string) ($effective['Gluten-free status'] ?? 'unknown'));
        $dna['gluten_status'] = match ($gluten) {
            'unknown' => 'unknown', 'no', 'not gluten-free' => 'no', 'yes', 'declared', 'gluten-free', 'declared gluten-free' => 'declared', 'verified', 'verified gluten-free' => 'verified', default => $this->fail('Gluten-free status must be Unknown, No, Declared or Verified.')
        };
        $minimum = (string) ($effective['Minimum sale'] ?? '');
        if (preg_match('/^(\d+)\s*cartons?\s*=\s*(\d+)\s*(pieces?|pcs?|pz)$/i', $minimum, $m)) {
            if ((int) $m[1] < 1 || (int) $m[2] !== (int) $m[1] * $pieces) {
                $this->fail('Minimum sale must agree with Pieces per carton.');
            }
            $dna['minimum_cartons'] = (int) $m[1];
            $dna['minimum_pieces'] = (int) $m[2];
        } elseif (preg_match('/^(\d+)\s*(pieces?|pcs?|pz)$/i', $minimum, $m) && (int) $m[1] > 0) {
            $dna['minimum_pieces'] = (int) $m[1];
            $dna['minimum_cartons'] = null;
        } else {
            $this->fail('Minimum sale: use "1 Carton = '.$pieces.' Piece" or "60 pieces".');
        }

        $warnings = [];
        $fields['category_id'] = $product?->category_id ?? $categoryId;
        if (! empty($effective['Category'])) {
            $category = Category::whereRaw('LOWER(name) = ?', [mb_strtolower($effective['Category'])])->where('is_active', true)->first();
            if ($category) {
                $fields['category_id'] = $category->id;
            } else {
                $warnings[] = 'Category "'.$effective['Category'].'" is saved as product family; '.($product ? 'the existing category is kept.' : 'the product will be placed in General.');
            }
        }
        $expectedWeight = (float) ($inputs['grams'] ?? 0) * $pieces / 1000;
        if ($expectedWeight && isset($effective['Net carton weight, kg']) && abs($effective['Net carton weight, kg'] - $expectedWeight) > max(0.05, $expectedWeight * .02)) {
            $warnings[] = 'Check net carton weight: file says '.$effective['Net carton weight, kg'].' kg; piece weight × carton quantity gives '.round($expectedWeight, 4).' kg. The file value will be saved.';
        }
        $landed = $effective['Total unit cost, CAD'] ?? $result['landed_cad'];
        $individualUnit = round($landed * 2.5, 2);
        $fields['total_selling_price_cad'] = round($individualUnit * ($sale === 'carton' ? $pieces : 1), 2);
        $priceLabel = $sale === 'carton' ? 'Selling price / carton, CAD' : 'Selling price / piece, CAD';
        if (! isset($effective[$priceLabel]) || $effective[$priceLabel] === '') {
            $this->fail($priceLabel.' is required.');
        }
        $fields['business_selling_price_cad'] = $effective[$priceLabel];
        if ($fields['total_selling_price_cad'] > 1000000000) {
            $this->fail('Calculated individual price is too large.');
        }
        $fields['supplier_discount'] = $result['discount_percent'];
        $fields['uom'] = $sale === 'carton' ? 'CRT' : 'PZ';
        if (abs($landed - $result['landed_cad']) > .00001) {
            $warnings[] = 'File total unit cost differs from purchase / freight calculations. File cost is used for individual pricing; review before confirming.';
        }
        $expectedBusiness = ($sale === 'carton' ? $result['business_carton_cents'] : $result['business_unit_cents']) / 100;
        if (abs($fields['business_selling_price_cad'] - $expectedBusiness) > .011) {
            $warnings[] = 'Business selling price differs from calculated markup. The exact file selling price will be saved.';
        }
        $storedSource = array_diff_key($effective, array_flip(config('product_import.ignored')));
        $dna['import_pricing'] = $storedSource;
        $fields['dna'] = $dna;
        $candidate = $product ? clone $product : new Product;
        $candidate->fill($fields);
        $changed = ! $product || $candidate->isDirty() || ($product->pricingDraft?->inputs ?? []) != $inputs;
        $diff = [];
        foreach (config('product_import.headers') as $label) {
            $ignored = in_array($label, config('product_import.ignored'), true);
            $incoming = $effective[$label] ?? null;
            $isCode = in_array($label, ['Internal code', 'Supplier SKU'], true);
            $different = $isCode ? (string) ($old[$label] ?? '') !== (string) $incoming : ! $this->same($old[$label] ?? null, $incoming);
            $diff[] = ['label' => $label, 'before' => $old[$label] ?? null, 'after' => $ignored ? ($old[$label] ?? null) : $incoming, 'changed' => ! $ignored && $different, 'note' => $ignored ? 'Ignored — existing data preserved' : (($source[$label] ?? '') === '' ? 'Blank — keep current value' : '')];
        }
        $diff[] = ['label' => 'Individual selling price, CAD (150% markup)', 'before' => $product?->total_selling_price_cad, 'after' => $fields['total_selling_price_cad'], 'changed' => ! $this->same($product?->total_selling_price_cad, $fields['total_selling_price_cad']), 'note' => 'Per '.strtolower($sale)];
        $diff[] = ['label' => 'Assigned website category', 'before' => $product?->category?->name, 'after' => Category::find($fields['category_id'])?->name, 'changed' => $product?->category_id !== $fields['category_id'], 'note' => 'Additional categories are preserved'];
        $result['landed_cad'] = $landed;
        $result['unit_cents'] = (int) round($individualUnit * 100);
        $result['carton_cents'] = $result['unit_cents'] * $pieces;
        $result['business_unit_cents'] = (int) round(($effective['Selling price / piece, CAD'] ?? $fields['business_selling_price_cad']) * 100);
        $result['business_carton_cents'] = (int) round(($effective['Selling price / carton, CAD'] ?? ($fields['business_selling_price_cad'] * $pieces)) * 100);

        return compact('fields', 'inputs', 'diff', 'warnings', 'changed', 'result') + ['fingerprint' => $this->fingerprint($product)];
    }

    private function current(?Product $product): array
    {
        if (! $product) {
            return [];
        }
        $dna = $product->dna ?? [];
        $values = $dna['import_pricing'] ?? [];
        if ($product->pricingDraft) {
            try {
                $calculated = app(ProductPricing::class)->calculate($product->pricingDraft->inputs);
                foreach (['Product cost, CAD / piece' => 'cost_cad', 'Total unit cost, CAD' => 'landed_cad'] as $label => $key) {
                    $values[$label] ??= $calculated[$key];
                }
                $values['Calculated margin %'] ??= $calculated['business_unit_cents'] > 0 ? (1 - $calculated['landed_cad'] / ($calculated['business_unit_cents'] / 100)) * 100 : null;
                $values['Selling price / piece, CAD'] ??= $calculated['business_unit_cents'] / 100;
                $values['Selling price / carton, CAD'] ??= $calculated['business_carton_cents'] / 100;
            } catch (ValidationException) { /* An incomplete old draft must not prevent a corrected import. */
            }
        }
        $values['Status'] = $product->is_active ? 'Active' : 'Inactive';
        $values['Image'] = $dna['image_link'] ?? 'Existing product media preserved';
        $values['Ingredients file'] = $dna['ingredients_link'] ?? 'Existing ingredients preserved';
        foreach (config('product_import.columns') as $label => $key) {
            $values[$label] = $product->$key;
        }
        foreach (config('product_import.dna') as $label => $key) {
            $values[$label] = $dna[$key] ?? null;
        }
        foreach (config('product_import.inputs') as $label => $key) {
            if (isset($product->pricingDraft?->inputs[$key])) {
                $values[$label] = $product->pricingDraft->inputs[$key];
            }
        }
        $values['Sell as'] = $dna['sale_unit'] ?? null;
        $values['Gluten-free status'] = $dna['gluten_status'] ?? 'unknown';
        $values['Pricing method'] = ($product->pricingDraft?->inputs['profit_mode'] ?? 'markup') === 'markup' ? 'Markup on cost' : 'Margin';
        if (! empty($dna['minimum_cartons'])) {
            $values['Minimum sale'] = $dna['minimum_cartons'].' Carton = '.($dna['minimum_pieces'] ?? ($dna['minimum_cartons'] * ($dna['pieces_per_carton'] ?? 1))).' Piece';
        } elseif (! empty($dna['minimum_pieces'])) {
            $values['Minimum sale'] = $dna['minimum_pieces'].' pieces';
        }
        $priceLabel = ($dna['sale_unit'] ?? 'piece') === 'carton' ? 'Selling price / carton, CAD' : 'Selling price / piece, CAD';
        $values[$priceLabel] = $product->business_selling_price_cad;

        return $values;
    }

    public function cancel(ProductImport $import): void
    {
        DB::transaction(function () use ($import) {
            $import = ProductImport::lockForUpdate()->findOrFail($import->id);
            if (! in_array($import->status, ['preview', 'invalid', 'cancelled'], true)) {
                $this->fail('This import has already been applied.');
            }
            $import->update(['status' => 'cancelled']);
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
                $this->fail('This import cannot be applied. Correct any errors and upload again.');
            }
            if (! $import->uploaded_by) {
                $this->fail('Upload this workbook from an authenticated admin account before applying it.');
            }
            Category::where('slug', 'general')->lockForUpdate()->firstOrFail();
            $products = Product::lockForUpdate()->get();
            // Lock drafts too: pricing changes must not race the reviewed product snapshot.
            ProductPricingDraft::whereIn('product_id', $products->modelKeys())->lockForUpdate()->get();
            $products->load('pricingDraft');
            $byCode = $products->groupBy(fn ($p) => $this->code($p->qr_code));
            $rows = $import->rows()->orderBy('id')->get();
            foreach ($rows as $row) {
                $plan = $row->normalized_data;
                if (($plan['format'] ?? '') !== 'dna-v1') {
                    $this->fail('This preview uses the old template. Upload again with the Product DNA template.');
                }
                $matches = $byCode->get($this->code($plan['source']['Internal code']), collect());
                $product = $matches->first();
                if ($matches->count() > 1 || $product?->id !== $row->product_id || $this->fingerprint($product) !== $plan['fingerprint']) {
                    $this->fail('Product '.$plan['source']['Internal code'].' changed since preview. Upload again and review the latest values. Nothing was imported.');
                }
                if (! Category::whereKey($plan['fields']['category_id'])->where('is_active', true)->exists()) {
                    $this->fail('A reviewed category is no longer active. Upload again.');
                }
            }
            $created = 0;
            $updated = 0;
            $unchanged = 0;
            foreach ($rows as $row) {
                $plan = $row->normalized_data;
                if ($row->status === 'unchanged') {
                    $unchanged++;

                    continue;
                }
                $product = $row->product_id ? $products->firstWhere('id', $row->product_id) : new Product(['slug' => Str::slug($plan['fields']['premium_marketing_name']).'-'.Str::uuid(), 'is_active' => true]);
                $new = ! $product->exists;
                $previousPrice = $product->total_selling_price_cad;
                $product->fill($plan['fields']);
                $product->editor_revision = ($product->editor_revision ?? 0) + 1;
                $product->save();
                if ($new) {
                    $product->inventory()->create(['quantity_on_hand' => null]);
                }
                $draft = $product->pricingDraft()->updateOrCreate([], ['inputs' => $plan['inputs'], 'options' => $product->pricingDraft?->options ?? [], 'revision' => ($product->pricingDraft?->revision ?? 0) + 1, 'updated_by' => $import->uploaded_by]);
                $product->priceReviews()->where('status', 'pending')->update(['status' => 'superseded']);
                $product->priceReviews()->create(['draft_revision' => $draft->revision, 'snapshot' => ['inputs' => $plan['inputs'], 'result' => $plan['result'], 'import_id' => $import->id, 'source' => $plan['source'], 'choice' => $plan['fields']['dna']['sale_unit']], 'previous_price' => $previousPrice, 'proposed_cents' => (int) round($product->total_selling_price_cad * 100), 'sale_label' => ucfirst($plan['fields']['dna']['sale_unit']), 'status' => 'saved', 'submitted_by' => $import->uploaded_by]);
                $new ? $created++ : $updated++;
                $row->update(['product_id' => $product->id, 'status' => $new ? 'created' : 'updated']);
            }
            $import->update(['status' => 'completed', 'created_count' => $created, 'updated_count' => $updated, 'unchanged_count' => $unchanged]);

            return $import;
        }, 3);
    }
}
