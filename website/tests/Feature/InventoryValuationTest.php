<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\InventoryProducts;
use App\Services\StaffPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryValuationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissions = ['access.admin.reports.inventory-valuation', 'access.admin.inventory.index']): User
    {
        $role = Role::create(['name' => 'Valuation '.uniqid(), 'permissions' => StaffPermissions::normalize($permissions)]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function product(string $name, ?string $price = '2.50', ?float $quantity = 10): Product
    {
        $product = Product::create([
            'slug' => 'valuation-'.uniqid(), 'category_id' => Category::first()->id,
            'premium_marketing_name' => $name, 'product_code' => 'CODE-'.uniqid(),
            'supplier_unit_price_eur' => $price, 'supplier' => 'Selected supplier', 'uom' => 'PZ',
            'is_active' => true, 'dna' => ['manufacturer' => 'Selected factory'],
        ]);
        $product->inventory()->create(['quantity_on_hand' => $quantity, 'low_stock_threshold' => 3]);

        return $product;
    }

    public function test_report_combines_warehouses_without_duplicate_categories_and_filters_one_warehouse(): void
    {
        $product = $this->product('Multi-warehouse cake');
        $category = Category::create(['name' => 'Report cakes', 'slug' => 'report-cakes']);
        $product->categories()->attach($category);
        $other = Warehouse::create(['name' => 'Report freezer', 'storage_type' => 'freezer']);
        WarehouseInventory::create(['product_id' => $product->id, 'warehouse_id' => $other->id, 'quantity_on_hand' => 4]);
        Inventory::withoutEvents(fn () => $product->inventory()->update(['quantity_on_hand' => 14]));
        $this->actingAs($this->staff());

        $this->get(route('admin.reports.inventory-valuation'))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['total'] === '35.00' && $s['records'] === 1)
            ->assertSee('All warehouses combined');
        $this->get(route('admin.reports.inventory-valuation', ['warehouse' => $other->id, 'category' => $category->id]))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['total'] === '10.00' && $s['records'] === 1);
    }

    public function test_missing_prices_are_zero_and_highlighted_without_changing_product_data(): void
    {
        $missing = $this->product('Missing price cake', null, 9);
        $this->product('Zero price cake', '0', 5);
        $this->product('Priced cake', '3.15', 2);
        $this->actingAs($this->staff())->get(route('admin.reports.inventory-valuation'))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['total'] === '6.30' && $s['missing_prices'] === 2)
            ->assertSee('valuation-missing-price')->assertSee('Missing price');
        $this->assertNull($missing->fresh()->supplier_unit_price_eur);
        $this->get(route('admin.reports.inventory-valuation', ['pricing' => 'missing']))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['records'] === 2 && $s['total'] === '0.00');
    }

    public function test_grand_total_covers_all_pages_and_sums_rounded_lines(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->product('Paged cake '.$i, '0.104', 1);
        }
        $response = $this->actingAs($this->staff())->get(route('admin.reports.inventory-valuation', ['per_page' => 10, 'page' => 2]))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->count() === 2);
        $this->assertSame(12, $response->viewData('summary')['records']);
        $this->assertSame('1.20', $response->viewData('summary')['total']);
    }

    public function test_inventory_filters_use_selected_warehouse_and_header_searches(): void
    {
        $product = $this->product('Location cake', '2', 10);
        $other = Warehouse::create(['name' => 'Low stock freezer', 'storage_type' => 'freezer']);
        WarehouseInventory::create(['product_id' => $product->id, 'warehouse_id' => $other->id, 'quantity_on_hand' => 2]);
        Inventory::withoutEvents(fn () => $product->inventory()->update(['quantity_on_hand' => 12]));
        $this->actingAs($this->staff());
        $this->get(route('admin.inventory.index', ['warehouse' => $other->id, 'stock' => 'low', 'category' => $product->category_id, 'product' => 'Location', 'quantity_max' => 3]))
            ->assertOk()->assertViewHas('products', fn ($p) => $p->total() === 1 && (float) $p->first()->stock_quantity === 2.0)
            ->assertSee('inv-column-filters')->assertSee('data-category-mirror', false);
        $this->get(route('admin.inventory.index', ['warehouse' => $other->id, 'stock' => 'healthy']))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 0);
        $emptyWarehouse = Warehouse::create(['name' => 'Unconfigured freezer', 'storage_type' => 'freezer']);
        $this->get(route('admin.inventory.index', ['warehouse' => $emptyWarehouse->id, 'stock' => 'unset']))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 1);
    }

    public function test_csv_exports_all_filtered_rows_extra_columns_and_safe_text(): void
    {
        $this->product('CSV cake');
        $this->product('=SUM(1,2)', null, 2);
        $this->product('Excluded cake', '7', 8);
        $this->actingAs($this->staff());
        $response = $this->get(route('admin.reports.inventory-valuation.export', ['format' => 'csv', 'columns' => 'manufacturer,supplier', 'quantity_max' => 10]))->assertOk()->assertDownload();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Selected factory', $csv);
        $this->assertStringContainsString('Missing price', $csv);
        $this->assertStringContainsString("'=SUM(1,2)", $csv);
        $this->assertStringContainsString('Grand total (EUR)', $csv);
        $this->assertStringContainsString('81.00', $csv);
        $this->assertTrue(strpos($csv, 'Total value (EUR)') < strpos($csv, 'Supplier'));
        $filtered = $this->get(route('admin.reports.inventory-valuation.export', ['format' => 'csv', 'product' => 'CSV cake']))->assertOk()->streamedContent();
        $this->assertStringContainsString('CSV cake', $filtered);
        $this->assertStringNotContainsString('Excluded cake', $filtered);
        $this->assertStringContainsString('25.00', $filtered);
    }

    public function test_excel_exports_extra_fields_red_missing_price_and_numeric_total(): void
    {
        $this->product('Excel cake');
        $this->product('Unpriced excel cake', null, 2);
        $this->actingAs($this->staff());
        $response = $this->get(route('admin.reports.inventory-valuation.export', ['format' => 'xlsx', 'columns' => 'manufacturer']))->assertOk()->assertDownload();
        $path = tempnam(sys_get_temp_dir(), 'valuation-test-');
        try {
            file_put_contents($path, $response->streamedContent());
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path));
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $styles = $zip->getFromName('xl/styles.xml');
            $zip->close();
            $this->assertStringContainsString('Manufacturer', $sheet);
            $this->assertStringContainsString('Selected factory', $sheet);
            $this->assertStringContainsString('Grand total (EUR)', $sheet);
            $this->assertStringContainsString('<v>25</v>', $sheet);
            $this->assertStringContainsString('FDE2E5', $styles);
            $this->assertTrue(strpos($sheet, 'Total value (EUR)') < strpos($sheet, 'Manufacturer'));
        } finally {
            unlink($path);
        }
    }

    public function test_pdf_download_uses_the_shared_theme_and_keeps_extra_fields(): void
    {
        $this->product('PDF cake');
        $this->actingAs($this->staff());
        $response = $this->get(route('admin.reports.inventory-valuation.export', ['format' => 'pdf', 'columns' => 'supplier,manufacturer,category,qr_code']))->assertOk()->assertDownload();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(3000, strlen($response->getContent()));
    }

    public function test_permissions_and_invalid_report_inputs_are_enforced(): void
    {
        $this->actingAs($this->staff(['access.admin.products.index']));
        $this->get(route('admin.reports.inventory-valuation'))->assertForbidden();
        foreach (['xlsx', 'csv', 'pdf'] as $format) {
            $this->get(route('admin.reports.inventory-valuation.export', ['format' => $format]))->assertForbidden();
        }
        $this->actingAs($this->staff());
        $this->getJson(route('admin.reports.inventory-valuation', ['columns' => 'password']))->assertUnprocessable();
        $this->getJson(route('admin.reports.inventory-valuation', ['columns' => ['supplier']]))->assertUnprocessable();
        $this->getJson(route('admin.reports.inventory-valuation', ['quantity_min' => 5, 'quantity_max' => 2]))->assertUnprocessable();
        $this->getJson(route('admin.reports.inventory-valuation', ['warehouse' => 9999]))->assertUnprocessable();
    }

    public function test_mysql_decimal_prices_round_half_cents_in_both_directions(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Checks exact DECIMAL arithmetic on the isolated MySQL test server.');
        }
        $positive = $this->product('Half cent positive', '0.105', 1);
        $negative = $this->product('Half cent negative', '0.105', -1);
        $inventory = app(InventoryProducts::class);
        $rows = $inventory->query([])->get()->keyBy('id');
        $this->assertSame(0.11, (float) $rows[$positive->id]->stock_value);
        $this->assertSame(-0.11, (float) $rows[$negative->id]->stock_value);
        $this->assertSame('0.00', $inventory->summary($inventory->query([]))['total']);
    }

    public function test_status_switch_requires_management_and_rejects_stale_changes(): void
    {
        $product = $this->product('Toggle cake');
        $this->actingAs($this->staff(['access.admin.products.index']));
        $this->get(route('admin.products.index'))->assertOk()->assertDontSee('data-product-active=', false);
        $this->patchJson(route('admin.products.active', $product), ['is_active' => false, 'expected_active' => true])->assertForbidden();

        $this->actingAs($this->staff(['products.manage']));
        $this->get(route('admin.products.index'))->assertOk()->assertSee('role="switch"', false);
        $this->patchJson(route('admin.products.active', $product), ['is_active' => false, 'expected_active' => true])->assertOk()->assertJsonPath('is_active', false);
        $this->assertFalse($product->fresh()->is_active);
        $this->assertSame(1, $product->fresh()->editor_revision);
        $this->assertSame('10.000', $product->inventory->fresh()->quantity_on_hand);
        $this->patchJson(route('admin.products.active', $product), ['is_active' => true, 'expected_active' => true])->assertUnprocessable();
        $this->patchJson(route('admin.products.active', $product), ['is_active' => true, 'expected_active' => false])->assertOk();
        $this->assertTrue($product->fresh()->is_active);
    }
}
