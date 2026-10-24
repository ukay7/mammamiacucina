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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class InventoryValuationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissions = ['access.admin.reports.inventory-valuation', 'access.admin.inventory.index']): User
    {
        $role = Role::create(['name' => 'Valuation '.uniqid(), 'permissions' => StaffPermissions::normalize($permissions)]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function product(string $name, ?string $price = '2.50', ?float $quantity = 10, array $inputs = []): Product
    {
        $product = Product::create([
            'slug' => 'valuation-'.uniqid(), 'category_id' => Category::first()->id,
            'premium_marketing_name' => $name, 'product_code' => 'CODE-'.uniqid(),
            'supplier_unit_price_eur' => $price, 'supplier' => 'Selected supplier', 'uom' => 'PZ',
            'is_active' => true, 'dna' => ['manufacturer' => 'Selected factory'],
        ]);
        $product->inventory()->create(['quantity_on_hand' => $quantity, 'low_stock_threshold' => 3]);
        $product->pricingDraft()->create(['inputs' => $inputs + [
            'purchase_price' => $price, 'purchase_basis' => 'carton', 'currency' => 'EUR', 'discount' => '0',
        ], 'options' => [], 'revision' => 1]);

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
            ->assertViewHas('summary', fn ($s) => $s['currencies']['EUR']['total'] === '35.00' && $s['records'] === 1)
            ->assertViewHas('summary', fn ($s) => (float) $s['stock_quantity'] === 14.0 && $s['stock_estimate_cad'] === '70.00')
            ->assertSee('All warehouses combined');
        $this->get(route('admin.reports.inventory-valuation', ['warehouse' => $other->id, 'category' => $category->id]))->assertOk()
            ->assertViewHas('summary', fn ($s) => (float) $s['stock_quantity'] === 4.0 && $s['stock_estimate_cad'] === '20.00')
            ->assertViewHas('summary', fn ($s) => $s['currencies']['EUR']['total'] === '10.00' && $s['records'] === 1);
    }

    public function test_missing_prices_are_zero_and_highlighted_without_changing_product_data(): void
    {
        $missing = $this->product('Missing price cake', null, 9);
        $this->product('Zero price cake', '0', 5);
        $this->product('Priced cake', '3.15', 2);
        $this->actingAs($this->staff())->get(route('admin.reports.inventory-valuation'))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['currencies']['EUR']['total'] === '6.30' && $s['missing_prices'] === 2)
            ->assertSee('valuation-missing-price')->assertSee('Missing price');
        $this->assertNull($missing->fresh()->supplier_unit_price_eur);
        $this->get(route('admin.reports.inventory-valuation', ['pricing' => 'missing']))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['records'] === 2 && $s['currencies']['EUR']['total'] === '0.00');
    }

    public function test_grand_total_covers_all_pages_and_sums_rounded_lines(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->product('Paged cake '.$i, '0.104', 1);
        }
        $response = $this->actingAs($this->staff())->get(route('admin.reports.inventory-valuation', ['per_page' => 10, 'page' => 2]))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->count() === 2);
        $this->assertSame(12, $response->viewData('summary')['records']);
        $this->assertSame('1.20', $response->viewData('summary')['currencies']['EUR']['total']);
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

    public function test_fixed_cad_estimate_uses_all_matching_stock_without_changing_supplier_totals(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->product('Estimate cake '.$i, '2', 1.2);
        }
        $this->product('Estimate shortage', '3', -2.4, ['currency' => 'CAD']);
        $this->product('Estimate not set', '17', null);
        $this->product('Estimate needs setup', '999', 0.001, ['purchase_basis' => 'pack']);
        $this->product('Unrelated product', '999', 1000);
        $this->actingAs($this->staff());

        $response = $this->get(route('admin.reports.inventory-valuation', ['product' => 'Estimate', 'per_page' => 10, 'page' => 2]))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->count() === 5)
            ->assertSee('Stock quantity × C$5')->assertSee('C$60.01')->assertSee('Additional estimate');
        $summary = $response->viewData('summary');
        $this->assertSame(12.001, (float) $summary['stock_quantity']);
        $this->assertSame('60.01', $summary['stock_estimate_cad']);
        $this->assertSame('28.80', $summary['currencies']['EUR']['total']);
        $this->assertSame('-7.20', $summary['currencies']['CAD']['total']);

        $this->get(route('admin.reports.inventory-valuation', ['product' => 'Estimate', 'currency' => 'CAD']))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['stock_estimate_cad'] === '-12.00' && $s['records'] === 1);
        $this->get(route('admin.reports.inventory-valuation', ['product' => 'Estimate', 'setup' => 'needs_setup']))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['stock_estimate_cad'] === '0.01' && $s['currencies']['EUR']['total'] === '0.00');
        $this->get(route('admin.reports.inventory-valuation', ['product' => 'No matching product']))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['stock_estimate_cad'] === '0.00' && $s['records'] === 0);
    }

    public function test_piece_and_carton_costs_use_quoted_currency_and_discount_without_conversion(): void
    {
        $piece = $this->product('Wild Berry Cheesecake', '999', 50, [
            'purchase_price' => '2', 'purchase_basis' => 'piece', 'currency' => 'EUR',
            'discount' => '38', 'pieces_per_carton' => '6', 'fx' => '99', 'freight_carton' => '99',
        ]);
        $carton = $this->product('EUR carton', '888', 50, [
            'purchase_price' => '2', 'discount' => '38', 'pieces_per_carton' => null,
        ]);
        $cad = $this->product('CAD carton', '0.01', 4, [
            'purchase_price' => '20', 'currency' => 'CAD', 'discount' => '25', 'pieces_per_carton' => '100',
        ]);
        $response = $this->actingAs($this->staff())->get(route('admin.reports.inventory-valuation'))->assertOk()
            ->assertSee('Grand total (EUR)')->assertSee('Grand total (CAD)');
        $summary = $response->viewData('summary');
        $this->assertSame('434.00', $summary['currencies']['EUR']['total']);
        $this->assertSame('60.00', $summary['currencies']['CAD']['total']);
        $this->assertSame(2, $summary['currencies']['EUR']['products']);
        $this->assertSame(1, $summary['currencies']['CAD']['products']);
        $rows = $response->viewData('products')->getCollection()->keyBy('id');
        $this->assertSame(7.44, (float) $rows[$piece->id]->valuation['carton_cost']);
        $this->assertSame('piece', $rows[$piece->id]->valuation['purchase_basis']);
        $this->assertSame('2', $rows[$piece->id]->valuation['purchase_price']);
        $this->assertSame('38', $rows[$piece->id]->valuation['discount']);
        $this->assertSame(1.24, (float) $rows[$piece->id]->valuation['discounted_price']);
        $this->assertSame('6', $rows[$piece->id]->valuation['pieces_per_carton']);
        $this->assertSame('372.00', $rows[$piece->id]->valuation['inventory_value']);
        $this->assertSame(1.24, (float) $rows[$carton->id]->valuation['carton_cost']);
        $this->assertSame(15.0, (float) $rows[$cad->id]->valuation['carton_cost']);
        $this->assertSame('999.00000000', $piece->fresh()->supplier_unit_price_eur);
        $this->assertSame('50.000', $piece->inventory->fresh()->quantity_on_hand);

        $this->get(route('admin.reports.inventory-valuation', ['price_min' => 7, 'price_max' => 8, 'value_min' => 370, 'value_max' => 375]))
            ->assertOk()->assertViewHas('products', fn ($p) => $p->total() === 1 && $p->first()->id === $piece->id)
            ->assertViewHas('summary', fn ($s) => $s['currencies']['EUR']['total'] === '372.00');
        $this->get(route('admin.reports.inventory-valuation', ['currency' => 'CAD']))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 1 && $p->first()->id === $cad->id)
            ->assertViewHas('summary', fn ($s) => $s['currencies']['EUR']['total'] === '0.00');
    }

    public function test_incomplete_or_unsupported_setup_is_identified_and_excluded(): void
    {
        $this->product('Complete carton');
        $cases = [
            ['purchase_basis' => 'pack'], ['purchase_basis' => 'kg'], ['currency' => null], ['currency' => 'USD'],
            ['purchase_basis' => 'piece', 'pieces_per_carton' => null],
            ['purchase_basis' => 'piece', 'pieces_per_carton' => '0'],
            ['purchase_basis' => 'piece', 'pieces_per_carton' => '2.5'],
            ['purchase_basis' => 'piece', 'pieces_per_carton' => '6', 'carton_status' => 'conflict'],
        ];
        foreach ($cases as $index => $inputs) {
            $this->product('Incomplete '.$index, '1000', 100, $inputs);
        }
        $response = $this->actingAs($this->staff())->get(route('admin.reports.inventory-valuation'))->assertOk()
            ->assertSee('Needs setup')->assertSee('Excluded')->assertSee('Set purchase currency to EUR or CAD.');
        $summary = $response->viewData('summary');
        $this->assertSame(8, $summary['needs_setup']);
        $this->assertSame(9, $summary['records']);
        $this->assertSame(7, $summary['currencies']['EUR']['products']);
        $this->assertSame('25.00', $summary['currencies']['EUR']['total']);
        foreach ($response->viewData('products') as $product) {
            if (str_starts_with($product->premium_marketing_name, 'Incomplete')) {
                $this->assertNull($product->valuation['inventory_value']);
                $this->assertNull($product->valuation['carton_cost']);
                $this->assertSame('Needs setup', $product->valuation['valuation_status']);
            }
        }
        $this->get(route('admin.reports.inventory-valuation', ['setup' => 'needs_setup']))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['records'] === 8 && $s['currencies']['EUR']['total'] === '0.00');
        $this->get(route('admin.reports.inventory-valuation', ['setup' => 'ready']))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['records'] === 1);
        $this->get(route('admin.reports.inventory-valuation', ['currency' => 'unknown']))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['records'] === 2);
    }

    public function test_discount_precision_missing_quoted_prices_and_unset_stock(): void
    {
        $this->product('Positive half cent', '0.105', 1);
        $this->product('Negative half cent', '0.105', -1);
        $compound = $this->product('Compound discount', '999', 10, [
            'purchase_price' => '2', 'purchase_basis' => 'piece', 'pieces_per_carton' => '6', 'discount' => '35+3',
        ]);
        $this->product('Missing quoted price despite legacy price', '1000', 20, ['purchase_price' => null]);
        $this->product('Unconfigured stock', '50', null, ['currency' => 'CAD']);
        $this->product('Missing legacy price but valid quote', null, 1, ['purchase_price' => '5', 'currency' => 'CAD']);
        $response = $this->actingAs($this->staff())->get(route('admin.reports.inventory-valuation'))->assertOk();
        $summary = $response->viewData('summary');
        $this->assertSame('75.66', $summary['currencies']['EUR']['total']);
        $this->assertSame('5.00', $summary['currencies']['CAD']['total']);
        $this->assertSame(1, $summary['missing_prices']);
        $this->assertSame(1, $summary['unset_quantities']);
        $this->assertSame(0, $summary['needs_setup']);
        $row = $response->viewData('products')->getCollection()->keyBy('id')[$compound->id];
        $this->assertSame(7.566, (float) $row->valuation['carton_cost']);
        $this->assertSame(1.261, (float) $row->valuation['discounted_price']);
        $this->get(route('admin.reports.inventory-valuation', ['pricing' => 'missing']))->assertOk()
            ->assertViewHas('products', fn ($p) => $p->total() === 1 && str_starts_with($p->first()->premium_marketing_name, 'Missing quoted'));
    }

    public function test_missing_pricing_settings_and_invalid_discounts_need_setup_but_dna_carton_size_is_usable(): void
    {
        $legacy = $this->product('Legacy product without pricing settings');
        $legacy->pricingDraft()->delete();
        $this->product('Invalid discount', '2', 100, ['discount' => '101']);
        $this->product('Invalid discount expression', '2', 100, ['discount' => 'call supplier']);
        $piece = $this->product('Packing size from product', '2', 10, [
            'currency' => 'CAD', 'purchase_basis' => 'piece', 'pieces_per_carton' => null,
        ]);
        $piece->update(['dna' => ['manufacturer' => 'Selected factory', 'pieces_per_carton' => 6]]);
        $this->actingAs($this->staff())->get(route('admin.reports.inventory-valuation'))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['needs_setup'] === 3
                && $s['currencies']['EUR']['total'] === '0.00'
                && $s['currencies']['CAD']['total'] === '120.00');
    }

    public function test_csv_contains_separate_totals_and_excludes_setup_values_across_all_pages(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->product('EUR piece '.$i, '2', 50, ['purchase_basis' => 'piece', 'pieces_per_carton' => '6', 'discount' => '38']);
        }
        $this->product('CAD carton', '10', 3, ['currency' => 'CAD', 'discount' => '20']);
        $this->product('Needs setup item', '1000', 50, ['purchase_basis' => 'pack']);
        $csv = $this->actingAs($this->staff())->get(route('admin.reports.inventory-valuation.export', [
            'format' => 'csv', 'per_page' => 10, 'page' => 2, 'columns' => 'manufacturer,purchase_price,discount,pieces_per_carton',
        ]))->assertOk()->streamedContent();
        $rows = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), explode("\n", trim($csv)));
        $headers = array_shift($rows);
        $columns = array_flip($headers);
        $this->assertSame('Purchase currency', $headers[3]);
        $this->assertSame('Cost per carton', $headers[9]);
        $this->assertSame('Valuation status', $headers[12]);
        $this->assertCount(14, $headers);
        $data = array_slice($rows, 0, 14);
        $this->assertCount(14, $data);
        $this->assertSame('Selected factory', $data[0][$columns['Manufacturer']]);
        $piece = collect($data)->first(fn ($r) => $r[2] === 'EUR piece 0');
        $this->assertSame('Piece', $piece[$columns['Supplier price basis']]);
        $this->assertSame('2', $piece[$columns['Supplier quoted price']]);
        $this->assertSame('38', $piece[$columns['Supplier discount %']]);
        $this->assertSame(1.24, (float) $piece[$columns['Discounted price']]);
        $this->assertSame('6', $piece[$columns['Pieces per carton']]);
        $this->assertSame(50.0, (float) $piece[$columns['Inventory quantity (cartons)']]);
        $eur = collect($rows)->first(fn ($r) => $r[0] === 'Grand total (EUR)');
        $cad = collect($rows)->first(fn ($r) => $r[0] === 'Grand total (CAD)');
        $this->assertSame('4464.00', $eur[$columns['Total value']]);
        $this->assertSame('24.00', $cad[$columns['Total value']]);
        $excluded = collect($data)->first(fn ($r) => $r[2] === 'Needs setup item');
        $this->assertSame('Excluded', $excluded[$columns['Total value']]);
        $this->assertStringStartsWith('Needs setup:', $excluded[$columns['Valuation status']]);
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
        $headers = str_getcsv(explode("\n", $csv)[0], ',', '"', '');
        $this->assertLessThan(array_search('Supplier', $headers), array_search('Total value', $headers));
        $filtered = $this->get(route('admin.reports.inventory-valuation.export', ['format' => 'csv', 'product' => 'CSV cake']))->assertOk()->streamedContent();
        $this->assertStringContainsString('CSV cake', $filtered);
        $this->assertStringNotContainsString('Excluded cake', $filtered);
        $this->assertStringContainsString('25.00', $filtered);
    }

    public function test_excel_exports_extra_fields_red_missing_price_and_numeric_total(): void
    {
        $this->product('Excel cake');
        $this->product('Unpriced excel cake', null, 2);
        $this->product('CAD excel cake', '10', 2, ['currency' => 'CAD']);
        $this->product('Setup excel cake', '1000', 2, ['purchase_basis' => 'pack']);
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
            $this->assertStringContainsString('Grand total (CAD)', $sheet);
            $this->assertStringContainsString('Excluded', $sheet);
            $this->assertStringContainsString('Needs setup', $sheet);
            $this->assertStringContainsString('<v>25</v>', $sheet);
            $this->assertStringContainsString('FDE2E5', $styles);
            $this->assertTrue(strpos($sheet, 'Total value') < strpos($sheet, 'Manufacturer'));
        } finally {
            unlink($path);
        }
    }

    public function test_pdf_download_uses_the_shared_theme_and_keeps_extra_fields(): void
    {
        $this->product('PDF cake', '2', 50, ['purchase_basis' => 'piece', 'pieces_per_carton' => '6', 'discount' => '38']);
        $this->product('CAD PDF cake', '10', 2, ['currency' => 'CAD']);
        $this->product('Setup PDF cake', '1000', 2, ['purchase_basis' => 'pack']);
        $pdfViewData = [];
        View::composer('documents.inventory-valuation', function ($view) use (&$pdfViewData) {
            $pdfViewData = Arr::only($view->getData(), ['doc', 'rows', 'headers', 'groups', 'summary', 'report', 'warehouse', 'filters']);
            $this->assertSame('372.00', $view->getData()['summary']['currencies']['EUR']['total']);
            $this->assertSame('20.00', $view->getData()['summary']['currencies']['CAD']['total']);
            $this->assertCount(4, $view->getData()['groups']);
            $this->assertSame('Excluded', $view->getData()['report']->format($view->getData()['rows'][2]['values']['inventory_value'], 'inventory_value'));
        });
        $this->actingAs($this->staff());
        $response = $this->get(route('admin.reports.inventory-valuation.export', ['format' => 'pdf', 'columns' => 'supplier,manufacturer,category,qr_code']))->assertOk()->assertDownload();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(3000, strlen($response->getContent()));
        $html = view('documents.inventory-valuation', $pdfViewData)->render();
        $this->assertStringNotContainsString('@elseif', $html);
        foreach (['Supplier', 'Manufacturer', 'Category', 'Internal code', 'Supplier price basis', 'Supplier quoted price', 'Supplier discount %', 'Discounted price', 'Pieces per carton'] as $label) {
            $this->assertStringContainsString('>'.$label.'</th>', $html);
        }
    }

    public function test_permissions_and_invalid_report_inputs_are_enforced(): void
    {
        $this->actingAs($this->staff(['access.admin.products.index']));
        $this->get(route('admin.reports.inventory-valuation'))->assertForbidden();
        $this->getJson(route('admin.inventory.summary'))->assertForbidden();
        foreach (['xlsx', 'csv', 'pdf'] as $format) {
            $this->get(route('admin.reports.inventory-valuation.export', ['format' => $format]))->assertForbidden();
        }
        $this->actingAs($this->staff());
        $this->getJson(route('admin.reports.inventory-valuation', ['columns' => 'password']))->assertUnprocessable();
        $this->getJson(route('admin.reports.inventory-valuation', ['columns' => ['supplier']]))->assertUnprocessable();
        $this->getJson(route('admin.reports.inventory-valuation', ['quantity_min' => 5, 'quantity_max' => 2]))->assertUnprocessable();
        $this->getJson(route('admin.reports.inventory-valuation', ['warehouse' => 9999]))->assertUnprocessable();
        $this->getJson(route('admin.reports.inventory-valuation', ['currency' => 'USD']))->assertUnprocessable();
        $this->getJson(route('admin.reports.inventory-valuation', ['setup' => 'invalid']))->assertUnprocessable();
    }

    public function test_inventory_summary_covers_all_filtered_pages_and_preserves_signed_quantities(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->product('Summary cake '.$i, '2', 1.2);
        }
        $this->product('Summary negative', '2', -2.4);
        $this->product('Summary zero', '2', 0);
        $this->product('Summary unset', '2', null);
        $this->product('Outside filter', '2', 999);
        $this->actingAs($this->staff());
        $response = $this->get(route('admin.inventory.index', ['product' => 'Summary', 'per_page' => 10, 'page' => 2]))->assertOk();
        $this->assertSame(5, $response->viewData('products')->count());
        $this->assertSame(['records' => 15, 'unset_quantities' => 1, 'quantity' => '12', 'quantity_times_five' => '60'], $response->viewData('stockSummary'));
        $response->assertSee('Total stock quantity')->assertSee('Quantity × 5')->assertDontSee('kg</', false);
        $this->getJson(route('admin.inventory.summary', ['product' => 'Summary', 'stock' => 'shortage']))
            ->assertOk()->assertJsonPath('quantity', '-2.4')->assertJsonPath('quantity_times_five', '-12')->assertJsonPath('records', 1);
        $this->getJson(route('admin.inventory.summary', ['product' => 'No match']))
            ->assertOk()->assertJsonPath('quantity', '0')->assertJsonPath('quantity_times_five', '0');
    }

    public function test_inventory_summary_uses_warehouse_category_and_refreshes_after_adjustment(): void
    {
        $product = $this->product('Filtered summary', '2', 20);
        $other = Warehouse::create(['name' => 'Summary freezer', 'storage_type' => 'freezer', 'is_active' => true]);
        WarehouseInventory::create(['product_id' => $product->id, 'warehouse_id' => $other->id, 'quantity_on_hand' => 2]);
        $this->actingAs($this->staff(['inventory.view', 'inventory.manage']));
        $filters = ['warehouse' => $other->id, 'category' => $product->category_id, 'stock' => 'low'];
        $this->getJson(route('admin.inventory.summary', $filters))->assertOk()
            ->assertJsonPath('quantity', '2')->assertJsonPath('quantity_times_five', '10')->assertJsonPath('records', 1);
        $this->postJson(route('admin.inventory.adjust', $product), ['warehouse_id' => $other->id,
            'action' => 'set', 'quantity' => 5, 'expected_quantity' => 2, 'low_stock_threshold' => 3, 'reason' => 'New stock received'])
            ->assertOk();
        $this->getJson(route('admin.inventory.summary', $filters))->assertOk()
            ->assertJsonPath('quantity', '0')->assertJsonPath('quantity_times_five', '0')->assertJsonPath('records', 0);
        $this->getJson(route('admin.inventory.summary', ['warehouse' => $other->id]))->assertOk()
            ->assertJsonPath('quantity', '5')->assertJsonPath('quantity_times_five', '25');
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
