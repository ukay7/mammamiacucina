<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SupplierDirectory
{
    public static function name(?string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $name));
    }

    public static function key(?string $name): string
    {
        return hash('sha256', mb_strtolower(self::name($name), 'UTF-8'));
    }

    /** Read-only validation for import previews; unknown names are created only on save/commit. */
    public function reference(mixed $id, ?string $name, ?int $currentId = null): ?Supplier
    {
        $supplier = null;
        if ($id !== null && $id !== '') {
            if (! ctype_digit((string) $id) || ! ($supplier = Supplier::find($id))) {
                throw ValidationException::withMessages(['supplier_id' => 'Supplier ID does not exist.']);
            }
            if (filled($name) && self::key($name) !== $supplier->name_key) {
                throw ValidationException::withMessages(['supplier_id' => 'Supplier ID and name identify different suppliers.']);
            }
        } elseif (filled(self::name($name))) {
            $supplier = Supplier::where('name_key', self::key($name))->first();
        }
        if ($supplier && ! $supplier->is_active && $supplier->id !== $currentId) {
            throw ValidationException::withMessages(['supplier' => 'This supplier is inactive. Choose an active supplier.']);
        }

        return $supplier;
    }

    public function syncProduct(Product $product): void
    {
        if (! ($product->isDirty('supplier') || $product->isDirty('supplier_id') || (! $product->supplier_id && filled($product->supplier)))) {
            return;
        }
        // Earlier historical migrations may save products before this feature's migration runs.
        if (! Schema::hasColumn('products', 'supplier_id')) {
            return;
        }
        $currentId = $product->getRawOriginal('supplier_id');
        if ($product->isDirty('supplier_id') && $product->supplier_id) {
            $supplier = $this->reference($product->supplier_id, null, $currentId);
        } else {
            $name = self::name($product->supplier);
            if ($name === '') {
                $product->supplier_id = null;

                return;
            }
            $supplier = $this->reference(null, $name, $currentId)
                ?? Supplier::firstOrCreate(['name_key' => self::key($name)], ['name' => $name, 'is_active' => true]);
        }
        $product->supplier_id = $supplier->id;
        $product->supplier = $supplier->name;
    }

    public function backfill(bool $dryRun = false): array
    {
        return DB::transaction(function () use ($dryRun) {
            $counts = ['linked' => 0, 'created' => 0, 'blank' => 0];
            $known = Supplier::all()->keyBy('name_key');
            DB::table('products')->whereNull('supplier_id')->orderBy('id')->lockForUpdate()->chunkById(200, function ($rows) use (&$counts, $known, $dryRun) {
                foreach ($rows as $row) {
                    $name = self::name($row->supplier);
                    if ($name === '') {
                        $counts['blank']++;

                        continue;
                    }
                    $key = self::key($name);
                    if (! $known->has($key)) {
                        $known[$key] = $dryRun ? new Supplier(['name' => $name])
                            : Supplier::firstOrCreate(['name_key' => $key], ['name' => $name, 'is_active' => true]);
                        $counts['created']++;
                    }
                    if (! $dryRun) {
                        DB::table('products')->where('id', $row->id)->update(['supplier_id' => $known[$key]->id,
                            'supplier' => $known[$key]->name, 'editor_revision' => DB::raw('editor_revision + 1'), 'updated_at' => now()]);
                    }
                    $counts['linked']++;
                }
            });

            return $counts;
        }, 3);
    }
}
