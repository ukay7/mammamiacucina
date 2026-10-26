<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\InventoryColumns;
use App\Services\StaffPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class InventoryManagementExportTest extends TestCase
{
    use RefreshDatabase;

    private function actor(bool $manage = false): User
    {
        $permissions = ['access.admin.inventory.index'];
        if ($manage) {
            $permissions[] = 'inventory.manage';
        }
        $role = Role::create(['name' => 'Stock exports '.Str::random(8), 'permissions' => StaffPermissions::normalize($permissions)]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function product(string $name, ?float $quantity = 10, bool $active = true, array $extra = []): Product
    {
        $product = Product::create($extra + ['category_id' => Category::first()->id, 'slug' => Str::uuid(),
            'premium_marketing_name' => $name, 'product_code' => '00123', 'qr_code' => Str::random(8),
            'supplier' => 'Stock supplier', 'uom' => 'CT', 'is_active' => $active]);
        $product->inventory()->create(['quantity_on_hand' => $quantity, 'low_stock_threshold' => 3]);

        return $product;
    }

    public function test_active_and_quantity_setup_filters_exclude_unset_but_preserve_zero_and_shortages(): void
    {
        $zero = $this->product('Zero is configured', 0);
        $shortage = $this->product('Shortage is configured', -2.5);
        $this->product('Unset active', null);
        $this->product('Inactive configured', 50, false);
        $this->actingAs($this->actor());
        $filters = ['active' => '1', 'quantity_setup' => 'set'];
        $this->get(route('admin.inventory.index', $filters))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 2 && $p->pluck('id')->contains($zero->id) && $p->pluck('id')->contains($shortage->id))
            ->assertViewHas('stockSummary', fn ($s) => $s['quantity'] === '-2.5' && $s['records'] === 2 && $s['unset_quantities'] === 0)
            ->assertSee('Product status')->assertSee('Quantity setup')->assertSee('Choose columns');
        $this->getJson(route('admin.inventory.summary', $filters))->assertOk()
            ->assertJsonPath('records', 2)->assertJsonPath('quantity_times_five', '-12.5');
        $this->get(route('admin.inventory.index', $filters + ['stock' => 'out']))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 1 && $p->first()->id === $zero->id);
        $this->get(route('admin.inventory.index', ['active' => '0']))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 1 && ! $p->first()->is_active);
        $this->get(route('admin.inventory.index', ['active' => '1', 'quantity_setup' => 'unset']))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 1 && $p->first()->stock_quantity === null);
    }

    public function test_setup_filter_is_for_the_selected_warehouse_not_the_combined_total(): void
    {
        $product = $this->product('Warehouse-specific cake', 10);
        $other = Warehouse::create(['name' => 'Unconfigured location', 'storage_type' => 'ambient']);
        $this->actingAs($this->actor());
        $filters = ['warehouse' => $other->id, 'quantity_setup' => 'unset', 'category' => $product->category_id];
        $this->get(route('admin.inventory.index', $filters))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 1 && $p->first()->stock_quantity === null);
        $this->get(route('admin.inventory.export', $filters + ['format' => 'csv', 'columns' => 'premium_marketing_name']))
            ->assertOk()->assertStreamedContent("\xEF\xBB\xBF\"Mamma Mia name\",\"Current stock\"\n\"Warehouse-specific cake\",\"Not set\"\n\"Total stock quantity\",0\n\"Quantity × 5\",0\n");
        WarehouseInventory::create(['warehouse_id' => $other->id, 'product_id' => $product->id, 'quantity_on_hand' => 0]);
        $this->getJson(route('admin.inventory.summary', ['warehouse' => $other->id, 'quantity_setup' => 'set']))
            ->assertOk()->assertJsonPath('records', 1)->assertJsonPath('quantity', '0')->assertJsonPath('unset_quantities', 0);
    }

    public function test_csv_exports_all_filtered_pages_and_only_selected_columns_plus_current_stock(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->product('Included '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 1.25);
        }
        $this->product('Excluded inactive', 200, false);
        $this->product('Excluded unset', null);
        $this->actingAs($this->actor());
        $response = $this->get(route('admin.inventory.export', ['format' => 'csv', 'columns' => 'uom,product_code,premium_marketing_name',
            'active' => '1', 'quantity_setup' => 'set', 'per_page' => 10, 'page' => 2]))->assertOk();
        $rows = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), explode("\n", trim(substr($response->streamedContent(), 3))));
        $this->assertSame(['Mamma Mia name', 'Supplier product code', 'UoM', 'Current stock'], $rows[0]);
        $this->assertCount(15, $rows);
        $this->assertSame(['Included 00', '00123', 'CT', '1.25'], $rows[1]);
        $this->assertSame('Included 11', $rows[12][0]);
        $this->assertSame(['Total stock quantity', '', '', '15'], $rows[13]);
        $this->assertSame(['Quantity × 5', '', '', '75'], $rows[14]);
        $text = json_encode($rows);
        foreach (['Excluded inactive', 'Excluded unset', 'New quantity', 'Warning below', 'Actions'] as $excluded) {
            $this->assertStringNotContainsString($excluded, $text);
        }
        $this->get(route('admin.inventory.export', ['format' => 'print', 'columns' => 'premium_marketing_name,product_code',
            'active' => '1', 'quantity_setup' => 'set', 'per_page' => 10, 'page' => 2]))->assertOk()
            ->assertViewHas('rows', fn ($rows) => count($rows) === 12)
            ->assertViewHas('summary', fn ($summary) => $summary['quantity'] === '15')
            ->assertSee('Included 11')->assertDontSee('Excluded inactive');
    }

    public function test_excel_preserves_identifiers_numeric_stock_and_missing_stock_without_formulas(): void
    {
        $this->product('=Dangerous formula', 0);
        $this->product('Missing stock', null);
        $this->actingAs($this->actor());
        $response = $this->get(route('admin.inventory.export', ['format' => 'xlsx', 'columns' => 'premium_marketing_name,product_code']))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'inventory-export-test-');
        $reader = new Reader;
        try {
            file_put_contents($path, $response->streamedContent());
            $reader->open($path);
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
            }
            $this->assertSame(['Mamma Mia name', 'Supplier product code', 'Current stock'], $rows[0]);
            $this->assertSame('=Dangerous formula', $rows[1][0]);
            $this->assertSame('00123', $rows[1][1]);
            $this->assertEquals(0, $rows[1][2]);
            $this->assertSame('Not set', $rows[2][2]);
            $this->assertSame('Total stock quantity', $rows[3][0]);
            $zip = new \ZipArchive;
            $zip->open($path);
            $this->assertStringNotContainsString('<f>', $zip->getFromName('xl/worksheets/sheet1.xml'));
            $zip->close();
        } finally {
            $reader->close();
            unlink($path);
        }
        $csv = $this->get(route('admin.inventory.export', ['format' => 'csv', 'columns' => 'premium_marketing_name']))->streamedContent();
        $this->assertStringContainsString("'=Dangerous formula", $csv);
    }

    public function test_print_and_pdf_are_read_only_include_all_columns_and_embed_photos(): void
    {
        Storage::fake('local');
        $product = $this->product('Printable cake', 12.5);
        $path = UploadedFile::fake()->image('cake.jpg', 200, 100)->store('product-media', 'local');
        $media = $product->media()->create(['kind' => 'image', 'path' => $path, 'mime_type' => 'image/jpeg', 'original_name' => 'cake.jpg']);
        $this->actingAs($this->actor());
        $selected = array_keys(app(InventoryColumns::class)->labels());
        $request = ['columns' => implode(',', $selected), 'format' => 'print'];
        $print = $this->get(route('admin.inventory.export', $request))->assertOk()->assertSee('Printable cake')->assertSee('Current stock')
            ->assertSee('data:image/jpeg;base64,', false)->assertSee('size:A4 landscape', false)->assertSee('Column section');
        foreach (['<input', '<select', '<form', 'New quantity', 'Warning below', '>Actions<', 'inv-adjust-form', 'data-orders-url'] as $excluded) {
            $print->assertDontSee($excluded, false);
        }
        $groups = $print->viewData('groups');
        $included = array_unique(array_merge(...array_map('array_keys', $groups)));
        $this->assertEqualsCanonicalizing(array_merge($selected, ['stock_quantity']), $included);
        foreach ($groups as $headers) {
            $this->assertLessThanOrEqual(8, count($headers));
            $this->assertArrayHasKey('stock_quantity', $headers);
        }
        $pdf = $this->get(route('admin.inventory.export', ['format' => 'pdf', 'columns' => 'image,premium_marketing_name,product_code']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('/Width 160', $pdf->getContent());
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->get(route('admin.inventory.media', [$product, $media]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $other = $this->product('Other product');
        $this->get(route('admin.inventory.media', [$other, $media]))->assertNotFound();
    }

    public function test_export_permissions_invalid_parameters_and_existing_management_controls(): void
    {
        $product = $this->product('Permissions cake');
        $this->actingAs($this->actor());
        $this->get(route('admin.inventory.index'))->assertOk()->assertDontSee('inv-adjust-form');
        $this->postJson(route('admin.inventory.adjust', $product), [])->assertForbidden();
        foreach ([['columns' => 'actions'], ['columns' => 'stock_quantity'], ['columns' => ''], ['active' => 'invalid'],
            ['quantity_setup' => 'invalid'], ['format' => 'invalid'], ['columns' => ['status']]] as $invalid) {
            $this->getJson(route('admin.inventory.export', $invalid + ['format' => 'csv', 'columns' => 'premium_marketing_name']))->assertUnprocessable();
        }
        $this->actingAs($this->actor(true))->get(route('admin.inventory.index', ['columns' => 'premium_marketing_name']))->assertOk()
            ->assertSee('inv-adjust-form')->assertSee('History')->assertSee('Orders')->assertSee('New quantity');
        $role = Role::create(['name' => 'No inventory', 'permissions' => []]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'is_active' => true]))
            ->get(route('admin.inventory.export', ['format' => 'csv']))->assertForbidden();
    }
}
