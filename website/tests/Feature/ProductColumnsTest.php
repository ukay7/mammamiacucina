<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductColumnsTest extends TestCase
{
    use RefreshDatabase;

    private function setupProduct(): Product
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::where('name', 'Super Admin')->value('id'), 'is_active' => true]));

        return Product::create(['supplier' => 'Special Supplier', 'qr_code' => '001234', 'premium_marketing_name' => 'Column Cake', 'slug' => 'column-cake', 'category_id' => Category::first()->id, 'is_active' => true, 'total_selling_price_cad' => '29.50', 'dna' => ['manufacturer' => 'Test Factory']]);
    }

    public function test_column_picker_presets_and_exports_share_the_selected_columns(): void
    {
        $this->setupProduct();
        $this->get('/admin/products?columns=premium_marketing_name,published_price')->assertOk()->assertSee('Choose columns')->assertSee('Missing data')->assertSee('Name and price')->assertSee('data-product-column="manufacturer"  hidden', false);
        $this->get('/admin/products/export?format=print&columns=premium_marketing_name,published_price')->assertOk()->assertSee('Column Cake')->assertSee('29.50000000')->assertDontSee('Special Supplier')->assertDontSee('Test Factory');
        $this->get('/admin/products/export?format=pdf&columns=manufacturer')->assertOk()->assertSee('Test Factory')->assertDontSee('Column Cake');
        $this->get('/admin/products/export?format=print&columns=manufacturer&supplier=absent')->assertOk()->assertDontSee('Test Factory');
    }

    public function test_unknown_empty_and_non_string_columns_are_validated(): void
    {
        $this->setupProduct();
        $this->getJson('/admin/products?columns=password')->assertUnprocessable();
        $this->getJson('/admin/products?columns[]=manufacturer')->assertUnprocessable();
        $this->get('/admin/products?columns=')->assertOk();
        $this->getJson('/admin/products/export?format=xlsx&columns=')->assertUnprocessable();
    }

    public function test_missing_calculations_are_blank_and_saved_calculations_are_distinct_from_current_price(): void
    {
        $p = $this->setupProduct();
        $columns = app(ProductColumns::class);
        $this->assertNull($columns->values($p)['unit_cents']);
        $p->pricingDraft()->create(['inputs' => ['purchase_price' => '2', 'purchase_basis' => 'piece', 'currency' => 'EUR', 'pieces_per_carton' => '6', 'discount' => '38', 'fx' => '1.65', 'freight_carton' => '5', 'other_unit_cost' => '0', 'profit_mode' => 'markup', 'profit_percent' => '50', 'carton_status' => 'source'], 'options' => [], 'revision' => 1]);
        $values = $columns->values($p->fresh());
        $this->assertSame('4.32', $values['unit_cents']);
        $this->assertSame('25.92', $values['carton_cents']);
        $this->assertSame('29.50000000', $values['published_price']);
    }

    public function test_excel_contains_only_selected_columns_in_display_order(): void
    {
        $this->setupProduct();
        $response = $this->get('/admin/products/export?format=xlsx&columns=published_price,qr_code')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'columns');
        try {
            file_put_contents($path, $response->streamedContent());
            $zip = new \ZipArchive;
            $zip->open($path);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $this->assertStringContainsString('001234', $xml);
            $this->assertStringContainsString('Current selling price', $xml);
            $this->assertStringNotContainsString('Column Cake', $xml);
            $this->assertTrue(strpos($xml, 'Internal code') < strpos($xml, 'Current selling price'));
        } finally {
            unlink($path);
        }
    }
}
