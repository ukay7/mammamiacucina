<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\Role;
use App\Models\User;
use App\Services\CatalogueImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class CatalogueAdminTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }parent::tearDown();
    }

    private function actor(string $role = 'Super Admin'): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->value('id'), 'is_active' => true]);
    }

    private function data(array $override = []): array
    {
        return array_replace(array_fill_keys(array_keys(config('product_fields')), null), ['supplier' => 'Test Bakery', 'product_code' => '0010821', 'qr_code' => '00100020', 'premium_marketing_name' => 'Sicilian Cake', 'total_selling_price_cad' => '10.56899999', 'invoice_quantity' => '150', 'uom' => 'PZ'], $override);
    }

    private function workbook(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mmctest').'.xlsx';
        $this->files[] = $path;
        $w = new Writer;
        $w->openToFile($path);
        $w->addRow(Row::fromValues(['Catalogue title']));
        $w->addRow(Row::fromValues(array_values(array_map(fn ($f) => $f[0], config('product_fields')))));
        foreach ($rows as $data) {
            $w->addRow(Row::fromValues(array_values($data)));
        }$w->close();

        return $path;
    }

    private function preview(array $rows): ProductImport
    {
        return app(CatalogueImporter::class)->preview($this->workbook($rows), 'test.xlsx', null, Category::where('slug', 'general')->value('id'));
    }

    private function product(): Product
    {
        $product = Product::create([...$this->data(), 'category_id' => 1, 'slug' => 'test-product', 'is_active' => true]);
        $product->inventory()->create([]);

        return $product;
    }

    public function test_import_keeps_codes_prices_nulls_and_all_fields_and_reimport_preserves_stock_category(): void
    {
        $this->product();
        $import = $this->preview([$this->data(), $this->data(['qr_code' => '002', 'product_code' => null, 'premium_marketing_name' => 'No Supplier Code'])]);
        $this->assertSame(2, $import->total_rows);
        $this->assertDatabaseCount('products', 1);
        app(CatalogueImporter::class)->commit($import);
        $product = Product::where('qr_code', '00100020')->firstOrFail();
        $this->assertSame('0010821', $product->product_code);
        $this->assertSame('10.56899999', $product->total_selling_price_cad);
        $this->assertNull($product->unit_weight_g);
        $this->assertNull($product->inventory->quantity_on_hand);
        $this->assertSame('General', $product->category->name);
        $category = Category::create(['name' => 'Cakes', 'slug' => 'cakes']);
        $product->update(['category_id' => $category->id, 'is_active' => false]);
        $product->inventory->update(['quantity_on_hand' => 12]);
        $second = $this->preview([$this->data(['premium_marketing_name' => 'New name'])]);
        app(CatalogueImporter::class)->commit($second);
        app(CatalogueImporter::class)->commit($second);
        $this->assertDatabaseCount('products', 2);
        $this->assertSame(1, $second->fresh()->updated_count);
        $this->assertSame($category->id, $product->fresh()->category_id);
        $this->assertFalse($product->fresh()->is_active);
        $this->assertSame('12.000', $product->fresh()->inventory->quantity_on_hand);
        $this->assertCount(23, $second->rows()->where('status', 'updated')->first()->normalized_data);
    }

    public function test_invalid_duplicate_and_negative_values_block_entire_import(): void
    {
        $this->product();
        $import = $this->preview([$this->data(), $this->data(['total_selling_price_cad' => '-2'])]);
        $this->assertSame('invalid', $import->status);
        $this->assertSame(1, $import->failed_count);
        try {
            app(CatalogueImporter::class)->commit($import);
            $this->fail('Invalid import was committed');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }$this->assertDatabaseCount('products', 1);
    }

    public function test_non_product_reference_rows_are_preserved(): void
    {
        $reference = $this->data(array_fill_keys(array_keys(config('product_fields')), null));
        $reference['original_description'] = 'https://example.test/reference';
        $import = $this->preview([$this->data(), $reference]);
        $this->assertSame(1, $import->total_rows);
        $this->assertSame(3, $import->skipped_count);
        $this->assertDatabaseCount('product_import_rows', 4);
    }

    public function test_all_catalogue_pages_render_and_upload_preview_commits(): void
    {
        Storage::fake('local');
        $this->product();
        $this->actingAs($this->actor());
        $path = $this->workbook([$this->data()]);
        $response = $this->post('/admin/imports', ['category_id' => 1, 'file' => new UploadedFile($path, 'catalogue.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)]);
        $import = ProductImport::firstOrFail();
        $response->assertRedirect('/admin/imports/'.$import->id);
        Storage::disk('local')->assertExists($import->stored_path);
        $this->get('/admin/imports/'.$import->id)->assertOk()->assertSee('Import Products');
        $this->post('/admin/imports/'.$import->id.'/commit')->assertRedirect();
        $product = Product::firstOrFail();
        foreach (['/admin/products', '/admin/products/create', '/admin/products/'.$product->id, '/admin/products/'.$product->id.'/edit', '/admin/categories', '/admin/categories/create', '/admin/categories/1/edit', '/admin/imports', '/admin/imports/'.$import->id, '/admin/inventory', '/admin/inventory/'.$product->id] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin/imports/'.$import->id.'/download')->assertDownload('catalogue.xlsx');
        $response = $this->get('/admin/imports/template')->assertDownload('product-import-template.xlsx');
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_stock_set_remove_stale_and_negative_checks_and_history(): void
    {
        $product = $this->product();
        $actor = $this->actor();
        $this->actingAs($actor);
        $url = '/admin/inventory/'.$product->id;
        $base = ['action' => 'set', 'quantity' => '10', 'expected_quantity' => '', 'low_stock_threshold' => '2', 'reason' => 'Opening count'];
        $this->post($url, $base)->assertSessionHasNoErrors();
        $this->assertSame('10.000', $product->fresh()->inventory->quantity_on_hand);
        $this->assertSame($actor->id, InventoryMovement::first()->created_by);
        $this->post($url, [...$base, 'quantity' => '12'])->assertSessionHasErrors('quantity');
        $this->post($url, [...$base, 'action' => 'remove', 'quantity' => '11', 'expected_quantity' => '10'])->assertSessionHasErrors('quantity');
        $this->post($url, [...$base, 'action' => 'remove', 'quantity' => '1.125', 'expected_quantity' => '10'])->assertSessionHasNoErrors();
        $this->assertSame('8.875', $product->fresh()->inventory->quantity_on_hand);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_category_bulk_move_and_protection_and_product_validation(): void
    {
        $product = $this->product();
        $this->actingAs($this->actor());
        $this->post('/admin/categories', ['name' => 'Cakes', 'description' => 'Our cakes', 'is_active' => 1, 'sort_order' => 2])->assertRedirect();
        $category = Category::where('name', 'Cakes')->firstOrFail();
        $this->post('/admin/products/bulk-category', ['product_ids' => [$product->id], 'category_id' => $category->id])->assertRedirect();
        $this->assertSame($category->id, $product->fresh()->category_id);
        $this->delete('/admin/categories/'.$category->id)->assertSessionHasErrors('category');
        $this->delete('/admin/categories/1')->assertSessionHasErrors('category');
        $this->post('/admin/products', [...$this->data(), 'category_id' => 1, 'is_active' => 1])->assertSessionHasErrors('qr_code');
        $this->put('/admin/products/'.$product->id, [...$this->data(), 'premium_marketing_name' => 'Edited Cake', 'category_id' => $category->id, 'is_active' => 0])->assertRedirect();
        $this->assertSame('Edited Cake', $product->fresh()->premium_marketing_name);
        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_permissions_enforced_for_all_modules(): void
    {
        $product = $this->product();
        $import = $this->preview([$this->data()]);
        $this->actingAs($this->actor('Staff'));
        foreach (['/admin/products', '/admin/categories', '/admin/imports', '/admin/inventory', '/admin/imports/'.$import->id.'/download'] as $url) {
            $this->get($url)->assertForbidden();
        }foreach (['/admin/products', '/admin/categories', '/admin/imports', '/admin/inventory/'.$product->id, '/admin/imports/'.$import->id.'/commit', '/admin/products/bulk-category'] as $url) {
            $this->post($url, [])->assertForbidden();
        }$this->get('/admin')->assertDontSee('Product Uploader')->assertDontSee('Manage Products');
        $role = Role::create(['name' => 'Catalogue Reader', 'permissions' => ['dashboard.view', 'products.view', 'inventory.view']]);
        $reader = $this->actor('Staff');
        $reader->update(['role_id' => $role->id]);
        $this->actingAs($reader);
        $this->get('/admin/products')->assertOk()->assertDontSee('Add Product');
        $this->get('/admin/inventory/'.$product->id)->assertOk()->assertDontSee('Save Stock Adjustment');
        $this->post('/admin/inventory/'.$product->id, [])->assertForbidden();
        $this->get('/admin/products/'.$product->id.'/edit')->assertForbidden();
    }

    public function test_code_match_updates_name_qr_and_prices_without_erasing_blank_details(): void
    {
        $product = $this->product();
        $product->update(['product_notes' => 'Keep these notes', 'pack_weight_kg' => 2]);
        $import = $this->preview([$this->data(['premium_marketing_name' => 'Renamed Cake', 'qr_code' => 'NEW-QR', 'total_selling_price_cad' => '25.75', 'product_notes' => null, 'pack_weight_kg' => null, 'shipping_cost_cad' => '0'])]);
        $this->assertSame($product->id, $import->rows()->where('status', 'ready')->first()->product_id);
        app(CatalogueImporter::class)->commit($import);
        $this->assertDatabaseCount('products', 1);
        $this->assertSame(1, $import->fresh()->updated_count);
        $this->assertSame('Renamed Cake', $product->fresh()->premium_marketing_name);
        $this->assertSame('NEW-QR', $product->fresh()->qr_code);
        $this->assertSame('25.75000000', $product->fresh()->total_selling_price_cad);
        $this->assertSame('Keep these notes', $product->fresh()->product_notes);
        $this->assertSame('2.00000000', $product->fresh()->pack_weight_kg);
        $this->assertSame('0.00000000', $product->fresh()->shipping_cost_cad);
    }

    public function test_qr_match_handles_changed_or_missing_code_and_changed_names(): void
    {
        $product = $this->product();
        $import = $this->preview([$this->data(['product_code' => 'REVISED-CODE', 'premium_marketing_name' => 'New Product Name', 'qr_code' => '00100020'])]);
        app(CatalogueImporter::class)->commit($import);
        $this->assertDatabaseCount('products', 1);
        $this->assertSame('REVISED-CODE', $product->fresh()->product_code);
        $next = $this->preview([$this->data(['product_code' => null, 'premium_marketing_name' => 'Another Name', 'qr_code' => '00100020', 'invoice_quantity' => '20'])]);
        app(CatalogueImporter::class)->commit($next);
        $this->assertSame(1, $next->fresh()->updated_count);
        $this->assertSame('REVISED-CODE', $product->fresh()->product_code);
        $this->assertSame('20.00000000', $product->fresh()->invoice_quantity);
    }

    public function test_unmatched_rows_insert_and_cross_matches_or_qr_conflicts_are_invalid(): void
    {
        $product = $this->product();
        $new = $this->preview([$this->data(['product_code' => 'SECOND', 'premium_marketing_name' => 'Lemon Tart', 'qr_code' => 'SECOND-QR'])]);
        app(CatalogueImporter::class)->commit($new);
        $this->assertSame(1, $new->fresh()->created_count);
        $this->assertSame(0, $new->fresh()->unmatched_count);
        $conflict = $this->preview([$this->data(['premium_marketing_name' => 'Any Name', 'qr_code' => 'SECOND-QR'])]);
        $this->assertSame('invalid', $conflict->status);
        $this->assertStringContainsString('different products', implode(' ', $conflict->rows()->where('status', 'invalid')->first()->errors));
        $qrConflict = $this->preview([$this->data(['product_code' => 'THIRD', 'premium_marketing_name' => 'Cannoli'])]);
        $this->assertSame('preview', $qrConflict->status);
        $this->assertSame($product->id, $qrConflict->rows()->where('status', 'ready')->first()->product_id);
        $this->assertDatabaseCount('products', 2);
        $this->assertSame('Sicilian Cake', $product->fresh()->premium_marketing_name);
    }

    public function test_repeated_codes_are_checked_but_repeated_names_are_allowed(): void
    {
        $this->product();
        $code = $this->preview([$this->data(), $this->data(['qr_code' => 'OTHER', 'premium_marketing_name' => 'Another Name'])]);
        $this->assertSame('invalid', $code->status);
        $name = $this->preview([$this->data(), $this->data(['product_code' => 'OTHER', 'qr_code' => 'OTHER', 'premium_marketing_name' => '  SICILIAN   CAKE '])]);
        $this->assertSame('preview', $name->status);
        app(CatalogueImporter::class)->commit($name);
        $this->assertDatabaseCount('products', 2);
    }

    public function test_commit_rechecks_matches_after_another_import_and_conflicts_roll_back(): void
    {
        $this->product();
        $first = $this->preview([$this->data()]);
        $second = $this->preview([$this->data()]);
        app(CatalogueImporter::class)->commit($first);
        app(CatalogueImporter::class)->commit($second);
        $this->assertDatabaseCount('products', 1);
        $this->assertSame(0, $second->fresh()->created_count);
        $this->assertSame(1, $second->fresh()->unchanged_count);
        $pending = $this->preview([$this->data(['total_selling_price_cad' => '99']), $this->data(['product_code' => 'SECOND', 'premium_marketing_name' => 'Lemon Tart', 'qr_code' => 'SECOND-QR'])]);
        // A later edit introduces a conflicting QR owner after the preview.
        Product::create(['category_id' => 1, 'slug' => 'code-owner', 'product_code' => 'SECOND', 'premium_marketing_name' => 'Code Owner', 'qr_code' => 'CODE-OWNER-QR']);
        Product::create(['category_id' => 1, 'slug' => 'unrelated', 'product_code' => 'UNRELATED', 'premium_marketing_name' => 'Unrelated Product', 'qr_code' => 'SECOND-QR']);
        try {
            app(CatalogueImporter::class)->commit($pending);
            $this->fail('Conflicting import succeeded');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        $this->assertSame('10.56899999', Product::where('product_code', '0010821')->first()->total_selling_price_cad);
        $this->assertSame('preview', $pending->fresh()->status);
        $this->assertDatabaseCount('products', 3);
    }

    public function test_existing_variants_with_shared_codes_or_names_are_distinguished_safely(): void
    {
        $regular = $this->product();
        $gift = Product::create(['category_id' => 1, 'slug' => 'gift-cake', 'product_code' => $regular->product_code, 'premium_marketing_name' => $regular->premium_marketing_name, 'qr_code' => 'GIFT', 'status' => 'Gift']);
        $size = Product::create(['category_id' => 1, 'slug' => 'large-cake', 'product_code' => 'LARGE', 'premium_marketing_name' => $regular->premium_marketing_name, 'qr_code' => 'LARGE-QR']);
        $import = $this->preview([$this->data(['status' => 'Regular']), $this->data(['qr_code' => 'GIFT', 'status' => 'Gift']), $this->data(['product_code' => 'LARGE', 'qr_code' => 'LARGE-QR'])]);
        $this->assertSame('preview', $import->status);
        app(CatalogueImporter::class)->commit($import);
        $this->assertSame(3, $import->fresh()->updated_count);
        $this->assertSame(0, $import->fresh()->created_count);
        $this->assertDatabaseCount('products', 3);
        $this->assertSame('Gift', $gift->fresh()->status);
        $ambiguous = $this->preview([$this->data(['qr_code' => 'UNKNOWN'])]);
        $this->assertSame('invalid', $ambiguous->status);
        $this->assertStringContainsString('existing QR code', implode(' ', $ambiguous->rows()->where('status', 'invalid')->first()->errors));
    }

    public function test_uploader_always_assigns_general_without_accepting_category_overrides(): void
    {
        Storage::fake('local');
        $this->product();
        $this->actingAs($this->actor());
        $other = Category::create(['name' => 'Other category', 'slug' => 'other-category']);
        $this->get('/admin/imports')->assertOk()->assertSee('Insert new products / update matches')->assertDontSee('name="category_id"', false);
        foreach ([[], ['category_id' => $other->id]] as $extra) {
            $path = $this->workbook([$this->data()]);
            $this->post('/admin/imports', [...$extra, 'file' => new UploadedFile($path, 'catalogue.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)])->assertRedirect();
            $import = ProductImport::latest('id')->firstOrFail();
            $this->assertSame(Category::where('slug', 'general')->value('id'), $import->category_id);
        }
    }

    public function test_empty_catalogue_inserts_all_rows_and_repeat_leaves_identical_rows_unchanged(): void
    {
        $rows = [
            $this->data(['status' => 'Regular']),
            $this->data(['product_code' => 'LARGE', 'qr_code' => 'LARGE-QR', 'status' => 'Regular', 'size_diameter_cm' => '20']),
            $this->data(['product_code' => 'LARGE', 'qr_code' => 'GIFT-QR', 'status' => 'Gift', 'size_diameter_cm' => '20']),
        ];
        $first = $this->preview($rows);
        $this->assertSame('preview', $first->status);
        $this->assertSame(0, $first->failed_count);
        app(CatalogueImporter::class)->commit($first);
        $this->assertSame(3, $first->fresh()->created_count);
        $this->assertSame(0, $first->fresh()->unmatched_count);
        $this->assertSame(0, $first->fresh()->updated_count);
        $this->assertDatabaseCount('products', 3);
        $repeat = $this->preview(array_reverse($rows));
        app(CatalogueImporter::class)->commit($repeat);
        $this->assertSame(0, $repeat->fresh()->created_count);
        $this->assertSame(0, $repeat->fresh()->updated_count);
        $this->assertSame(3, $repeat->fresh()->unchanged_count);
        $this->assertDatabaseCount('products', 3);

    }

    public function test_batch_inserts_new_size_and_leaves_identical_existing_product_unchanged(): void
    {
        $regular = $this->product();
        $regular->update(['status' => 'Regular']);
        $regular->inventory->update(['quantity_on_hand' => 8]);
        $import = $this->preview([
            $this->data(['status' => 'Regular']),
            $this->data(['product_code' => 'LARGE', 'qr_code' => 'LARGE-QR', 'status' => 'Regular', 'size_diameter_cm' => '20']),
        ]);
        app(CatalogueImporter::class)->commit($import);
        $this->assertSame(1, $import->fresh()->created_count);
        $this->assertSame(0, $import->fresh()->unmatched_count);
        $this->assertSame(0, $import->fresh()->updated_count);
        $this->assertDatabaseCount('products', 2);
        $this->assertNull($regular->fresh()->size_diameter_cm);
        $this->assertSame('8.000', $regular->fresh()->inventory->quantity_on_hand);
    }

    public function test_revalidation_repairs_previous_false_duplicate_errors_without_importing(): void
    {
        $import = $this->preview([
            $this->data(['status' => 'Regular']),
            $this->data(['qr_code' => 'GIFT-QR', 'status' => 'Gift']),
        ]);
        $import->update(['status' => 'invalid', 'failed_count' => 1]);
        $import->rows()->whereNotNull('normalized_data')->update(['status' => 'invalid', 'errors' => json_encode(['Old duplicate validation'])]);
        $fixed = app(CatalogueImporter::class)->revalidate($import);
        $this->assertSame('preview', $fixed->status);
        $this->assertSame(0, $fixed->failed_count);
        $this->assertSame(2, $fixed->rows()->where('status', 'insert')->count());
        $this->assertDatabaseCount('products', 0);
    }

    public function test_same_name_with_new_identifiers_inserts_instead_of_updating(): void
    {
        $original = $this->product();
        $import = $this->preview([$this->data(['product_code' => 'NEW-CODE', 'qr_code' => 'NEW-QR'])]);
        app(CatalogueImporter::class)->commit($import);
        $this->assertSame(1, $import->fresh()->created_count);
        $this->assertSame(0, $import->fresh()->unmatched_count);
        $this->assertSame(0, $import->fresh()->updated_count);
        $this->assertDatabaseCount('products', 2);
        $this->assertSame('0010821', $original->fresh()->product_code);
    }

    public function test_identical_values_do_not_write_products_or_inventory(): void
    {
        $product = $this->product();
        $before = $product->fresh()->getRawOriginal();
        $stock = $product->fresh()->inventory->getRawOriginal();
        $this->travel(1)->hours();
        $import = $this->preview([$this->data(['invoice_quantity' => '150.00000000'])]);
        $this->assertSame(1, $import->unchanged_count);
        app(CatalogueImporter::class)->commit($import);
        $this->assertSame(0, $import->fresh()->updated_count);
        $this->assertSame($before, $product->fresh()->getRawOriginal());
        $this->assertSame($stock, $product->fresh()->inventory->getRawOriginal());
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_product_removed_after_preview_is_created_at_commit(): void
    {
        $product = $this->product();
        $import = $this->preview([$this->data(['total_selling_price_cad' => '99'])]);
        $product->inventory()->delete();
        $product->delete();
        app(CatalogueImporter::class)->commit($import);
        $this->assertSame(1, $import->fresh()->created_count);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_two_rows_cannot_assign_same_new_qr_to_different_products(): void
    {
        $this->product();
        Product::create([...$this->data(['product_code' => 'SECOND', 'qr_code' => 'SECOND-QR']), 'category_id' => 1, 'slug' => 'second']);
        $import = $this->preview([$this->data(['qr_code' => 'NEW']), $this->data(['product_code' => 'SECOND', 'qr_code' => 'NEW'])]);
        $this->assertSame('invalid', $import->status);
        $this->assertSame(1, $import->failed_count);
        $this->assertDatabaseMissing('products', ['qr_code' => 'NEW']);
    }

    public function test_invalid_decimal_on_matched_row_is_validation_error(): void
    {
        $this->product();
        $import = $this->preview([$this->data(['total_selling_price_cad' => 'not a number'])]);
        $this->assertSame('invalid', $import->status);
        $this->assertSame(1, $import->failed_count);
    }

    public function test_product_modals_upload_order_remove_and_serve_images_and_video(): void
    {
        Storage::fake('local');
        $product = $this->product();
        $this->actingAs($this->actor());
        $this->get('/admin/products')->assertOk()->assertSee('data-product-modal', false)->assertSee('Edit');
        $this->get('/admin/products/'.$product->id.'?modal=1')->assertOk()->assertDontSee('page-sidebar', false);
        $this->get('/admin/products/'.$product->id.'/edit?modal=1')->assertOk()->assertSee('media_files[]', false);
        $payload = [...$this->data(), 'category_id' => 1, 'is_active' => 1];
        $this->post('/admin/products/'.$product->id.'?modal=1', [...$payload, '_method' => 'PUT', 'media_files' => [UploadedFile::fake()->createWithContent('cake.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0ZkAAAAASUVORK5CYII=')), UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4')]])->assertSessionHasNoErrors()->assertRedirect('/admin/products/'.$product->id.'/edit?modal=1');
        $media = $product->media()->get();
        $this->assertCount(2, $media);
        $this->assertSame('image', $media[0]->kind);
        $this->assertSame('video', $media[1]->kind);
        foreach ($media as $item) {
            Storage::disk('local')->assertExists($item->path);
            $this->get('/admin/products/'.$product->id.'/media/'.$item->id)->assertOk()->assertHeader('Content-Type', $item->mime_type);
        }
        $this->put('/admin/products/'.$product->id, [...$payload, 'media_order' => [$media[0]->id => 2, $media[1]->id => 1]])->assertSessionHasNoErrors();
        $this->assertSame($media[1]->id, $product->media()->first()->id);
        $this->put('/admin/products/'.$product->id, [...$payload, 'remove_media' => [$media[0]->id]])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($media[0]->path);
        $this->assertCount(1, $product->media()->get());
    }

    public function test_media_rejects_bad_files_and_cross_product_removal_and_permissions(): void
    {
        Storage::fake('local');
        $product = $this->product();
        $this->actingAs($this->actor());
        $payload = [...$this->data(), 'category_id' => 1, 'is_active' => 1];
        $url = '/admin/products/'.$product->id;
        $this->put($url, [...$payload, 'media_files' => [UploadedFile::fake()->create('bad.php', 1, 'text/plain')]])->assertSessionHasErrors('media_files.0');
        $this->put($url, [...$payload, 'media_files' => [UploadedFile::fake()->create('large.mp4', 51201, 'video/mp4')]])->assertSessionHasErrors('media_files.0');
        $other = Product::create([...$this->data(['qr_code' => 'OTHER']), 'category_id' => 1, 'slug' => 'other']);
        $media = $other->media()->create(['path' => 'test.jpg', 'original_name' => 'test.jpg', 'mime_type' => 'image/jpeg', 'kind' => 'image', 'sort_order' => 1]);
        $this->put($url, [...$payload, 'remove_media' => [$media->id]])->assertSessionHasErrors('remove_media.0');
        $this->get($url.'/media/'.$media->id)->assertNotFound();
        $this->actingAs($this->actor('Staff'));
        $this->get('/admin/products/'.$other->id.'/media/'.$media->id)->assertForbidden();
        $this->put($url, [...$payload, 'media_files' => [UploadedFile::fake()->createWithContent('cake.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0ZkAAAAASUVORK5CYII='))]])->assertForbidden();
        $this->assertDatabaseCount('product_media', 1);
    }

    public function test_multiple_categories_filter_counts_and_import_preservation(): void
    {
        $product = $this->product();
        $this->actingAs($this->actor());
        $cakes = Category::create(['name' => 'Cakes', 'slug' => 'cakes']);
        $special = Category::create(['name' => 'Specials', 'slug' => 'specials']);
        $payload = [...$this->data(), 'category_ids' => [$cakes->id, $special->id], 'is_active' => 1];
        $this->put('/admin/products/'.$product->id.'?modal=1', $payload)->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$cakes->id, $special->id], $product->categories()->pluck('categories.id')->all());
        $this->assertSame(1, $cakes->products()->count());
        $this->assertSame(1, $special->products()->count());
        foreach ([$cakes, $special] as $category) {
            $this->get('/admin/products?category='.$category->id)->assertOk()->assertSee('Sicilian Cake');
        }
        $this->get('/admin/products?category=1')->assertOk()->assertSee('No matching products.');
        $this->get('/admin/products/'.$product->id.'?modal=1')->assertSee('Cakes, Specials');
        $import = $this->preview([$this->data(['total_selling_price_cad' => '20'])]);
        app(CatalogueImporter::class)->commit($import);
        $this->assertEqualsCanonicalizing([$cakes->id, $special->id], $product->categories()->pluck('categories.id')->all());
        $this->delete('/admin/categories/'.$special->id)->assertSessionHasErrors('category');
        $this->put('/admin/products/'.$product->id, [...$payload, 'category_ids' => []])->assertSessionHasErrors('category_ids');
        $this->put('/admin/products/'.$product->id, [...$payload, 'category_ids' => [999999]])->assertSessionHasErrors('category_ids.0');
    }

    public function test_bulk_add_keeps_categories_and_replace_resets_them(): void
    {
        $product = $this->product();
        $this->actingAs($this->actor());
        $category = Category::create(['name' => 'Featured', 'slug' => 'featured']);
        $payload = ['product_ids' => [$product->id], 'category_id' => $category->id, 'mode' => 'add'];
        $this->post('/admin/products/bulk-category', $payload)->assertSessionHasNoErrors();
        $this->post('/admin/products/bulk-category', $payload)->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([1, $category->id], $product->categories()->pluck('categories.id')->all());
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('inventories', 1);
        $this->post('/admin/products/bulk-category', [...$payload, 'mode' => 'replace'])->assertSessionHasNoErrors();
        $this->assertSame([$category->id], $product->categories()->pluck('categories.id')->all());
    }

    public function test_new_product_and_import_receive_category_links(): void
    {
        $this->actingAs($this->actor());
        $category = Category::create(['name' => 'Cakes', 'slug' => 'cakes']);
        $this->post('/admin/products', [...$this->data(), 'category_ids' => [1, $category->id], 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertCount(2, Product::first()->categories);
        $import = $this->preview([$this->data(['product_code' => 'NEW', 'qr_code' => 'NEW'])]);
        app(CatalogueImporter::class)->commit($import);
        $this->assertSame([1], Product::where('qr_code', 'NEW')->first()->categories()->pluck('categories.id')->all());
    }
}
