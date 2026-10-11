<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\Role;
use App\Models\User;
use App\Services\DnaCatalogueImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ProductDnaImportTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public static function sample(array $override = []): array
    {
        return array_replace(array_fill_keys(config('product_import.headers'), ''), [
            'Status' => 'Inactive', 'Image' => 'new-image.png', 'Internal code' => '00100080',
            'Mamma Mia name' => 'Sample pastry', 'Supplier' => 'Sample supplier', 'Supplier SKU' => '00039',
            'Product name on source' => 'Pastry 110g 60 pieces', 'Manufacturer' => 'Sample bakery',
            'Category' => 'General', 'Gluten-free status' => 'Unknown', 'Weight per piece, g' => 110,
            'Pieces per carton' => 60, 'Net carton weight, kg' => 6.6, 'Sell as' => 'Carton',
            'Minimum sale' => '1 Carton = 60 Piece', 'Supplier quoted price' => 30,
            'Supplier price basis' => 'Carton', 'Purchase currency' => 'EUR', 'Price per piece, EUR' => .5,
            'Discount %' => '0', 'After discount, EUR' => .5, 'CAD per 1 EUR' => 1.65,
            'Product cost, CAD / piece' => .825, 'Freight, CAD / carton' => 5,
            'Freight, CAD / piece' => 5 / 60, 'Other cost, CAD / piece' => 0,
            'Total unit cost, CAD' => .825 + 5 / 60, 'Pricing method' => 'Markup on cost',
            'Profit setting %' => 65, 'Selling price / piece, CAD' => 1.5,
            'Selling price / carton, CAD' => 90, 'Calculated margin %' => 39.44444444,
            'Image status' => 'Missing', 'Ingredients file' => 'Missing', 'Review notes' => 'Example notes',
        ], $override);
    }

    public function workbook(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dna').'.xlsx';
        $this->files[] = $path;
        $w = new Writer;
        $w->openToFile($path);
        $w->addRow(Row::fromValues(config('product_import.headers')));
        foreach ($rows as $row) {
            $w->addRow(Row::fromValues(array_values($row)));
        }
        $w->close();

        return $path;
    }

    private function actor(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'Super Admin')->value('id'), 'is_active' => true]);
    }

    private function preview(array $rows): ProductImport
    {
        return app(DnaCatalogueImporter::class)->preview($this->workbook($rows), 'dna.xlsx', $this->actor()->id, Category::where('slug', 'general')->value('id'));
    }

    private function commit(ProductImport $import): ProductImport
    {
        return app(DnaCatalogueImporter::class)->commit($import);
    }

    public function test_preview_insert_update_prices_and_protected_data_and_idempotency(): void
    {
        $first = $this->preview([self::sample()]);
        $this->assertSame('preview', $first->status);
        $this->assertDatabaseCount('products', 0);
        $this->commit($first);
        $product = Product::firstOrFail();
        $this->assertSame('00100080', $product->qr_code);
        $this->assertSame('00039', $product->product_code);
        $this->assertEquals(90, $product->business_selling_price_cad);
        $this->assertEquals(136.2, $product->total_selling_price_cad);
        $this->assertSame(60, $product->dna['minimum_pieces']);
        $this->assertSame(1, $product->dna['minimum_cartons']);
        $this->assertEquals(65, $product->pricingDraft->inputs['business_profit_percent']);
        $this->assertTrue($product->is_active);
        $this->assertNull($product->inventory->quantity_on_hand);
        $product->update(['status' => 'Manual status', 'is_active' => false, 'dna' => [...$product->dna, 'ingredients_text' => 'Flour', 'image_link' => 'existing.jpg']]);
        $product->inventory->update(['quantity_on_hand' => 8]);
        $second = $this->preview([self::sample(['Mamma Mia name' => 'Updated name', 'Supplier SKU' => 'NEW-SKU']), self::sample(['Internal code' => 'NEW-CODE', 'Supplier SKU' => '00039'])]);
        $this->assertSame('update', $second->rows()->first()->status);
        $this->commit($second);
        $this->commit($second);
        $this->assertDatabaseCount('products', 2);
        $product->refresh();
        $this->assertSame('Updated name', $product->premium_marketing_name);
        $this->assertSame('NEW-SKU', $product->product_code);
        $this->assertSame('Manual status', $product->status);
        $this->assertFalse($product->is_active);
        $this->assertSame('Flour', $product->dna['ingredients_text']);
        $this->assertSame('existing.jpg', $product->dna['image_link']);
        $this->assertEquals(8, $product->inventory->quantity_on_hand);
        $third = $this->preview([self::sample(['Mamma Mia name' => 'Updated name', 'Supplier SKU' => 'NEW-SKU'])]);
        $this->assertSame('unchanged', $third->rows()->first()->status);
        $revision = $product->editor_revision;
        $this->commit($third);
        $this->assertSame($revision, $product->fresh()->editor_revision);
    }

    public function test_web_review_cancel_and_template(): void
    {
        Storage::fake('local');
        $this->actingAs($this->actor());
        $path = $this->workbook([self::sample()]);
        $this->post('/admin/imports', ['file' => new UploadedFile($path, 'dna.xlsx', null, null, true)])->assertSessionHasNoErrors()->assertRedirect();
        $import = ProductImport::firstOrFail();
        $this->get('/admin/imports/'.$import->id)->assertOk()->assertSee('Current value')->assertSee('After import')->assertSee('dna-new')->assertSee('Confirm &amp; Import Products', false);
        $this->post('/admin/imports/'.$import->id.'/cancel')->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $import->fresh()->status);
        $this->post('/admin/imports/'.$import->id.'/commit')->assertSessionHasErrors();
        $this->assertDatabaseCount('products', 0);
        $this->get('/admin/imports/template')->assertDownload('product-import-template.xlsx');
        $reader = new Reader;
        $reader->open(resource_path('templates/product-import-template.xlsx'));
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $this->assertSame(config('product_import.headers'), $row->toArray());
                break;
            }
            break;
        } $reader->close();
    }

    public function test_validation_duplicate_minimum_and_negative_values_block_whole_batch(): void
    {
        $import = $this->preview([self::sample(), self::sample(['Minimum sale' => '1 Carton = 75 Piece']), self::sample(['Internal code' => 'other', 'Selling price / carton, CAD' => -1])]);
        $this->assertSame('invalid', $import->status);
        $this->assertSame(2, $import->failed_count);
        try {
            $this->commit($import);
            $this->fail('Invalid batch committed');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        $this->assertDatabaseCount('products', 0);
    }

    public function test_stale_product_or_newly_claimed_code_cannot_change_reviewed_plan(): void
    {
        $first = $this->preview([self::sample()]);
        $this->commit($first);
        $next = $this->preview([self::sample(['Mamma Mia name' => 'Imported name'])]);
        Product::first()->update(['premium_marketing_name' => 'Another admin edit']);
        try {
            $this->commit($next);
            $this->fail('Stale import committed');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('changed since preview', $e->getMessage());
        }
        $this->assertSame('Another admin edit', Product::first()->premium_marketing_name);
        $new1 = $this->preview([self::sample(['Internal code' => 'new'])]);
        $new2 = $this->preview([self::sample(['Internal code' => 'new'])]);
        $this->commit($new2);
        try {
            $this->commit($new1);
            $this->fail('Claimed code committed');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('changed since preview', $e->getMessage());
        }
    }

    public function test_weight_warning_blank_preservation_and_red_change_comparison(): void
    {
        $first = $this->preview([self::sample()]);
        $this->commit($first);
        $next = $this->preview([self::sample(['Mamma Mia name' => '', 'Net carton weight, kg' => 6600, 'Review notes' => 'Changed notes'])]);
        $plan = $next->rows()->first()->normalized_data;
        $this->assertStringContainsString('Check net carton weight', implode(' ', $plan['warnings']));
        $this->assertSame('Sample pastry', $plan['fields']['premium_marketing_name']);
        $this->actingAs($this->actor())->get('/admin/imports/'.$next->id)->assertOk()->assertSee('dna-changed')->assertSee('Changed notes');
        $this->commit($next);
        $this->assertEquals(6600, Product::first()->pack_weight_kg);
    }

    public function test_uncached_excel_formula_blocks_import(): void
    {
        $path = $this->workbook([self::sample()]);
        $zip = new \ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $xml = preg_replace('/<c\b[^>]*\br="S2"[^>]*>.*?<\/c>/s', '<c r="S2"><f>15+15</f></c>', $xml, 1, $count);
        $this->assertSame(1, $count);
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();
        $import = app(DnaCatalogueImporter::class)->preview($path, 'formulas.xlsx', $this->actor()->id, 1);
        $this->assertSame('invalid', $import->status);
        $this->assertStringContainsString('unsaved formula', implode(' ', $import->rows()->first()->errors));
        $this->assertDatabaseCount('products', 0);
    }

    public function test_stale_pricing_draft_blocks_all_rows_even_new_ones(): void
    {
        $this->commit($this->preview([self::sample()]));
        $next = $this->preview([self::sample(['Internal code' => 'new-code']), self::sample(['Mamma Mia name' => 'Edited'])]);
        $draft = Product::first()->pricingDraft;
        $draft->update(['inputs' => [...$draft->inputs, 'profit_percent' => 170], 'revision' => $draft->revision + 1]);
        try {
            $this->commit($next);
            $this->fail('Stale pricing committed');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('changed since preview', $e->getMessage());
        }
        $this->assertDatabaseCount('products', 1);
        $this->assertSame('Sample pastry', Product::first()->premium_marketing_name);
    }

    public function test_exact_code_does_not_match_different_leading_zeros_or_supplier_sku(): void
    {
        $this->commit($this->preview([self::sample()]));
        $other = $this->preview([self::sample(['Internal code' => '100080'])]);
        $this->assertSame('insert', $other->rows()->first()->status);
        $this->commit($other);
        $this->assertDatabaseCount('products', 2);
        $this->assertSame(['00100080', '100080'], Product::orderBy('id')->pluck('qr_code')->all());
    }

    public function test_http_commit_and_review_history_work(): void
    {
        $import = $this->preview([self::sample()]);
        $this->actingAs($this->actor())->post('/admin/imports/'.$import->id.'/commit')->assertSessionHasNoErrors()->assertRedirect();
        $this->get('/admin/imports/'.$import->id)->assertOk()->assertSee('Import complete: 1 created');
        $review = Product::first()->priceReviews()->first();
        $this->assertEquals(9000, $review->snapshot['result']['business_carton_cents']);
        $this->assertEquals(13620, $review->snapshot['result']['carton_cents']);
        $this->post('/admin/imports/'.$import->id.'/cancel')->assertSessionHasErrors();
        $this->assertSame('completed', $import->fresh()->status);
    }

    public function test_review_table_search_filter_and_bounded_pagination(): void
    {
        $import = $this->preview([self::sample()]);
        $original = $import->rows()->first();
        for ($i = 2; $i <= 62; $i++) {
            $row = $original->replicate();
            $plan = $row->normalized_data;
            $plan['source']['Internal code'] = 'CODE-'.$i;
            $plan['source']['Mamma Mia name'] = 'Product '.$i;
            $plan['fields']['premium_marketing_name'] = 'Product '.$i;
            $row->normalized_data = $plan;
            $row->row_number = $i + 1;
            $row->status = $i === 62 ? 'update' : 'insert';
            $row->save();
        }
        $this->actingAs($this->actor());
        $url = '/admin/imports/'.$import->id;
        $this->get($url)->assertOk()->assertSee('dna-grid')->assertViewHas('rows', fn ($rows) => $rows->count() === 25 && $rows->total() === 62);
        $this->get($url.'?page=3')->assertOk()->assertSee('CODE-62')->assertViewHas('rows', fn ($rows) => $rows->count() === 12);
        $this->get($url.'?search=CODE-62')->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        $this->get($url.'?status=updates')->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        $this->get($url.'?status=new&search=CODE-62')->assertOk()->assertSee('No products match these filters');
        $this->get($url.'?per_page=50')->assertOk()->assertViewHas('rows', fn ($rows) => $rows->count() === 50);
        $this->getJson($url.'?per_page=100000')->assertUnprocessable();
        $this->assertDatabaseCount('products', 0);
    }
}
