<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DnaCatalogueImporter;
use App\Services\ProductImportTemplate;
use App\Services\SupplierDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class SupplierDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $supplier = ''): Product
    {
        return Product::create(['category_id' => Category::first()->id, 'slug' => Str::uuid(), 'qr_code' => Str::uuid(),
            'premium_marketing_name' => 'Supplier test product', 'supplier' => $supplier,
            'is_active' => true, 'dna' => ['manufacturer' => 'Separate factory']]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('is_super', true)->value('id'), 'is_active' => true]);
    }

    public function test_backfill_has_a_dry_run_deduplicates_names_and_is_repeatable_without_changing_prices_or_stock(): void
    {
        $first = $this->product();
        $second = $this->product();
        $blank = $this->product();
        DB::table('products')->where('id', $first->id)->update(['supplier' => '  Acme   Foods  ', 'supplier_unit_price_eur' => 2]);
        DB::table('products')->where('id', $second->id)->update(['supplier' => 'ACME FOODS']);
        $first->inventory()->create(['quantity_on_hand' => 12, 'low_stock_threshold' => 3]);
        $before = DB::table('inventories')->get()->map(fn ($row) => (array) $row)->all();
        $this->artisan('suppliers:backfill', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Supplier::count());
        $this->assertNull($first->fresh()->supplier_id);
        $result = app(SupplierDirectory::class)->backfill();
        $this->assertSame(['linked' => 2, 'created' => 1, 'blank' => 1], $result);
        $this->assertSame($first->fresh()->supplier_id, $second->fresh()->supplier_id);
        $this->assertSame('Acme Foods', $first->fresh()->supplier);
        $this->assertSame('2.00000000', $first->fresh()->supplier_unit_price_eur);
        $this->assertSame($before, DB::table('inventories')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertSame(0, app(SupplierDirectory::class)->backfill()['linked']);
        $this->assertNull($blank->fresh()->supplier_id);
    }

    public function test_product_name_mapping_and_supplier_rename_preserve_manufacturer_and_invalidate_old_editors(): void
    {
        $first = $this->product('Acme Foods');
        $second = $this->product(' ACME   foods ');
        $this->assertSame(1, Supplier::count());
        $this->assertSame($first->supplier_id, $second->supplier_id);
        $supplier = Supplier::findOrFail($first->supplier_id);
        $revision = $first->editor_revision;
        $supplier->update(['name' => 'Renamed Foods']);
        $this->assertSame('Renamed Foods', $first->fresh()->supplier);
        $this->assertSame($revision + 1, $first->fresh()->editor_revision);
        $this->assertSame('Separate factory', $first->fresh()->dna['manufacturer']);
        $first->refresh()->update(['supplier' => 'Different Supplier']);
        $this->assertNotSame($first->supplier_id, $second->fresh()->supplier_id);
    }

    public function test_supplier_crud_rejects_duplicate_names_and_deleting_a_linked_supplier(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.suppliers.store'), ['name' => 'Acme Foods', 'revision' => 0, 'is_active' => 1])->assertSessionHasNoErrors();
        $supplier = Supplier::firstOrFail();
        $this->assertNull($supplier->email);
        $this->get(route('admin.suppliers.show', $supplier))->assertOk()->assertSee('Delete unused supplier');
        $this->post(route('admin.suppliers.store'), ['name' => ' ACME   foods ', 'revision' => 0, 'is_active' => 1])->assertSessionHasErrors('name');
        $product = $this->product('Acme Foods');
        $this->get(route('admin.suppliers.show', $supplier))->assertOk()->assertDontSee('Delete unused supplier')->assertSee('mark it inactive');
        $this->delete(route('admin.suppliers.destroy', $supplier))->assertSessionHasErrors('supplier');
        $this->assertNotNull($supplier->fresh());
        $this->put(route('admin.suppliers.update', $supplier), ['name' => 'New Name', 'email' => 'supplier@example.test', 'revision' => 0, 'is_active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($supplier->fresh()->is_active);
        $this->assertSame('New Name', $product->fresh()->supplier);
        $this->put(route('admin.suppliers.update', $supplier), ['name' => 'Old editor', 'revision' => 0, 'is_active' => 1])->assertSessionHasErrors('supplier');
        $unused = Supplier::create(['name' => 'Unused']);
        $this->delete(route('admin.suppliers.destroy', $unused))->assertRedirect(route('admin.suppliers.index'));
        $this->assertNull($unused->fresh());
    }

    public function test_optional_supplier_id_import_maps_existing_names_and_never_creates_suppliers_during_preview(): void
    {
        $actor = $this->admin();
        $supplier = Supplier::create(['name' => 'Existing Supplier']);
        $row = ProductDnaImportTest::sample(['Supplier' => ' existing   supplier ']);
        $path = $this->workbook($row, (string) $supplier->id);
        try {
            $importer = app(DnaCatalogueImporter::class);
            $import = $importer->preview($path, 'suppliers.xlsx', $actor->id, Category::first()->id);
            $this->assertSame('preview', $import->status);
            $this->assertSame(1, Supplier::count());
            $this->assertSame($supplier->id, $import->rows()->first()->normalized_data['fields']['supplier_id']);
            $importer->commit($import);
            $this->assertSame($supplier->id, Product::firstOrFail()->supplier_id);
            $this->assertSame('Existing Supplier', Product::firstOrFail()->supplier);
            $again = $importer->preview($path, 'same-suppliers.xlsx', $actor->id, Category::first()->id);
            $plan = $again->rows()->first()->normalized_data;
            $candidate = clone Product::firstOrFail();
            $candidate->fill($plan['fields']);
            $this->assertSame('unchanged', $again->rows()->first()->status, 'Unexpected changed fields: '.implode(', ', array_keys($candidate->getDirty())));
        } finally {
            unlink($path);
        }
        $unknown = $this->workbook(ProductDnaImportTest::sample(['Supplier' => 'New Supplier', 'Internal code' => 'NEW-ID']), null);
        try {
            $preview = $importer->preview($unknown, 'new.xlsx', $actor->id, Category::first()->id);
            $this->assertSame(1, Supplier::count());
            $importer->commit($preview);
            $this->assertSame(2, Supplier::count());
        } finally {
            unlink($unknown);
        }
    }

    public function test_supplier_id_conflicts_make_the_batch_invalid_and_template_has_the_optional_column(): void
    {
        $supplier = Supplier::create(['name' => 'ID Owner']);
        $path = $this->workbook(ProductDnaImportTest::sample(['Supplier' => 'Another Name']), (string) $supplier->id);
        try {
            $import = app(DnaCatalogueImporter::class)->preview($path, 'bad.xlsx', $this->admin()->id, Category::first()->id);
            $this->assertSame('invalid', $import->status);
            $this->assertSame(0, Product::count());
            $this->assertStringContainsString('different suppliers', implode(' ', $import->rows()->first()->errors));
        } finally {
            unlink($path);
        }
        $template = app(ProductImportTemplate::class)->path();
        $reader = new Reader;
        try {
            $reader->open($template);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $this->assertSame([...config('product_import.headers'), 'Supplier ID'], $row->toArray());
                    break;
                }
                break;
            }
        } finally {
            $reader->close();
            unset($row, $sheet);
            gc_collect_cycles();
            @unlink($template);
        }
    }

    private function workbook(array $row, ?string $supplierId): string
    {
        $path = tempnam(sys_get_temp_dir(), 'supplier-import-').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([...config('product_import.headers'), 'Supplier ID']));
        $writer->addRow(Row::fromValues([...array_values($row), $supplierId]));
        $writer->close();

        return $path;
    }
}
