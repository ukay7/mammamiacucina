<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductPricingTest extends TestCase
{
    use RefreshDatabase;

    private function inputs(array $override = []): array
    {
        return array_replace(['purchase_price' => '2', 'purchase_basis' => 'piece', 'currency' => 'EUR', 'pieces_per_carton' => '6', 'units_per_pack' => '6', 'grams' => '100', 'discount' => '38', 'fx' => '1.65', 'freight_carton' => '5', 'other_unit_cost' => '0', 'profit_mode' => 'markup', 'profit_percent' => '50', 'carton_status' => 'source'], $override);
    }

    private function user(bool $super = true): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $super ? 'Super Admin' : 'Staff')->value('id'), 'is_active' => true]);
    }

    private function product(string $code = 'DNA001'): Product
    {
        return Product::create(['supplier' => 'DNA Supplier', 'qr_code' => $code, 'product_code' => '0010821', 'premium_marketing_name' => 'DNA Cake', 'slug' => strtolower($code), 'category_id' => Category::firstOrFail()->id, 'is_active' => true, 'total_selling_price_cad' => '10.569']);
    }

    public function test_supplier_formula_rounding_and_dynamic_pack_discount(): void
    {
        $r = app(ProductPricing::class)->calculate($this->inputs(), [['label' => 'Six pieces', 'quantity' => 6, 'discount' => 10]]);
        $this->assertSame(432, $r['unit_cents']);
        $this->assertSame(2592, $r['carton_cents']);
        $this->assertSame(2333, $r['options'][0]['total_cents']);
        $this->assertEqualsWithDelta(2.8793333333, $r['landed_cad'], 0.00000001);
        $seq = app(ProductPricing::class)->calculate($this->inputs(['discount' => '35+3']));
        $this->assertEqualsWithDelta(36.95, $seq['discount_percent'], 0.000001);
        foreach ([['purchase_basis' => 'carton', 'purchase_price' => '12'], ['purchase_basis' => 'pack', 'purchase_price' => '12'], ['purchase_basis' => 'kg', 'purchase_price' => '20'], ['currency' => 'CAD', 'purchase_price' => '3.3']] as $change) {
            $this->assertSame(432, app(ProductPricing::class)->calculate($this->inputs($change))['unit_cents']);
        }
        $this->assertSame(576, app(ProductPricing::class)->calculate($this->inputs(['profit_mode' => 'margin']))['unit_cents']);
    }

    public function test_bad_calculation_inputs_are_rejected(): void
    {
        foreach ([['profit_mode' => 'margin', 'profit_percent' => '100'], ['profit_mode' => 'margin', 'profit_percent' => '99.9999999999999'], ['carton_status' => 'conflict'], ['discount' => '101'], ['discount' => 'abc'], ['pieces_per_carton' => '0'], ['fx' => '0'], ['purchase_basis' => 'pack', 'units_per_pack' => null], ['purchase_basis' => 'kg', 'grams' => null]] as $change) {
            try {
                app(ProductPricing::class)->calculate($this->inputs($change));
                $this->fail('Invalid calculation accepted.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_product_dna_private_files_and_filtered_exports(): void
    {
        Storage::fake('local');
        $p = $this->product();
        $this->actingAs($this->user());
        $data = ['supplier' => $p->supplier, 'qr_code' => $p->qr_code, 'premium_marketing_name' => $p->premium_marketing_name, 'category_ids' => [$p->category_id], 'is_active' => 1, 'editor_revision' => 0, 'dna' => ['manufacturer' => 'Factory', 'pieces_per_carton' => 6, 'country_of_origin' => 'Italy'], 'ingredient_documents' => [UploadedFile::fake()->create('ingredients.pdf', 10, 'application/pdf')]];
        $this->put('/admin/products/'.$p->id, $data)->assertSessionHasNoErrors();
        $this->assertSame('Factory', $p->fresh()->dna['manufacturer']);
        $this->assertSame('10.56900000', $p->fresh()->total_selling_price_cad);
        $doc = $p->documents()->firstOrFail();
        Storage::disk('local')->assertExists($doc->path);
        $this->get('/admin/products/'.$p->id.'/documents/'.$doc->id)->assertOk();
        $this->putJson('/admin/products/'.$p->id, array_diff_key($data, ['ingredient_documents' => 1]))->assertUnprocessable();
        $this->get('/admin/products?supplier=none')->assertOk();
        $this->get('/admin/products/export?format=print&supplier=DNA')->assertOk()->assertSee('DNA Cake');
        $this->get('/admin/products/export?format=pdf&supplier=absent')->assertOk()->assertDontSee('DNA Cake');
        $response = $this->get('/admin/products/export?format=xlsx&supplier=DNA')->assertOk();
        $this->assertStringStartsWith('PK', $response->streamedContent());
        $other = $this->product('DNA002');
        $this->get('/admin/products/'.$other->id.'/documents/'.$doc->id)->assertNotFound();
    }

    public function test_excel_exports_literal_text_and_preserves_leading_zero_codes(): void
    {
        $p = $this->product('001234');
        $p->update(['premium_marketing_name' => '=1+1']);
        $this->actingAs($this->user());
        $bytes = $this->get('/admin/products/export?format=xlsx')->assertOk()->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'dna-export');
        try {
            file_put_contents($path, $bytes);
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $this->assertStringNotContainsString('<f>', $sheet);
            $this->assertStringContainsString('001234', $sheet);
            $this->assertStringContainsString('=1+1', $sheet);
        } finally {
            unlink($path);
        }
    }

    public function test_preview_does_not_save_and_direct_save_updates_one_price(): void
    {
        $u = $this->user();
        $p = $this->product();
        $this->actingAs($u);
        $url = '/admin/products/'.$p->id.'/pricing/';
        $data = ['inputs' => $this->inputs(), 'revision' => 0, 'editor_revision' => 0, 'choice' => 'carton'];
        $this->postJson($url.'quote', $data)->assertOk()->assertJsonPath('unit_cents', 432);
        $this->assertSame('10.56900000', $p->fresh()->total_selling_price_cad);
        $this->assertDatabaseCount('product_pricing_drafts', 0);
        $this->postJson($url.'save', $data + ['proposed_cents' => 1])->assertOk()->assertJsonPath('revision', 1)->assertJsonPath('editor_revision', 1)->assertJsonPath('price', '25.92000000');
        $this->assertSame('25.92000000', $p->fresh()->total_selling_price_cad);
        $this->assertSame('saved', $p->priceReviews()->first()->status);
        $this->postJson($url.'save', $data)->assertUnprocessable();
        $this->postJson($url.'save', array_replace($data, ['revision' => 1, 'editor_revision' => 1, 'choice' => 'unit']))->assertOk();
        $this->assertSame('4.32000000', $p->fresh()->total_selling_price_cad);
    }

    public function test_invalid_or_stale_price_save_is_atomic(): void
    {
        $p = $this->product();
        $this->actingAs($this->user());
        $url = '/admin/products/'.$p->id.'/pricing/save';
        $data = ['inputs' => $this->inputs(), 'revision' => 0, 'editor_revision' => 0, 'choice' => 'unit'];
        foreach ([['choice' => 'option:0'], ['editor_revision' => 2], ['inputs' => $this->inputs(['fx' => '0'])], ['inputs' => []]] as $change) {
            $this->postJson($url, array_replace($data, $change))->assertUnprocessable();
            $this->assertSame('10.56900000', $p->fresh()->total_selling_price_cad);
            $this->assertDatabaseCount('product_pricing_drafts', 0);
        }
    }

    public function test_editor_permission_can_save_without_approval_and_removed_pages_are_unavailable(): void
    {
        $p = $this->product();
        $role = Role::create(['name' => 'Product editor', 'permissions' => ['products.view', 'products.manage']]);
        $u = $this->user(false);
        $u->update(['role_id' => $role->id]);
        $this->actingAs($u->fresh());
        $this->get('/admin/products/'.$p->id.'/edit')->assertOk()->assertSee('Save Price')->assertDontSee('New Pricing')->assertDontSee('Extracted source data')->assertDontSee('Save Pricing Draft')->assertSee('Notes and sources');
        $this->postJson('/admin/products/'.$p->id.'/pricing/save', ['inputs' => $this->inputs(), 'revision' => 0, 'editor_revision' => 0, 'choice' => 'unit'])->assertOk();
        $this->get('/admin/price-reviews')->assertNotFound();
        $this->postJson('/admin/products/'.$p->id.'/pricing/submit', [])->assertStatus(405);
        $role->update(['permissions' => ['products.view']]);
        $this->actingAs($u->fresh());
        $this->postJson('/admin/products/'.$p->id.'/pricing/save', [])->assertForbidden();
    }

    public function test_direct_save_preserves_source_and_legacy_options_and_general_edits_preserve_price(): void
    {
        $p = $this->product();
        $u = $this->user();
        $p->update(['dna' => ['source_text' => 'Original supplier document']]);
        $p->pricingDraft()->create(['inputs' => $this->inputs(), 'options' => [['label' => 'Old six', 'quantity' => 6, 'discount' => 10]], 'revision' => 1, 'updated_by' => $u->id]);
        $this->actingAs($u)->postJson('/admin/products/'.$p->id.'/pricing/save', ['inputs' => $this->inputs(), 'revision' => 1, 'editor_revision' => 0, 'choice' => 'unit'])->assertOk();
        $this->assertSame('Old six', $p->pricingDraft()->first()->options[0]['label']);
        $this->put('/admin/products/'.$p->id, ['supplier' => $p->supplier, 'qr_code' => $p->qr_code, 'premium_marketing_name' => $p->premium_marketing_name, 'category_ids' => [$p->category_id], 'is_active' => 1, 'editor_revision' => 1, 'dna' => ['owner_notes' => 'Checked']])->assertSessionHasNoErrors();
        $this->assertSame('Original supplier document', $p->fresh()->dna['source_text']);
        $this->assertSame('4.32000000',$p->fresh()->total_selling_price_cad);
    }
    public function test_manual_price_field_updates_existing_calculated_products_and_rejects_stale_or_invalid_edits():void {
        $p=$this->product();$p->update(['total_selling_price_cad'=>'29.50']);
        $p->pricingDraft()->create(['inputs'=>$this->inputs(),'options'=>[],'revision'=>1]);
        $this->actingAs($this->user());
        $this->get('/admin/products/'.$p->id.'/edit')->assertOk()->assertSee('name="total_selling_price_cad"',false)->assertSee('value="29.50000000"',false);
        $data=['supplier'=>$p->supplier,'qr_code'=>$p->qr_code,'premium_marketing_name'=>$p->premium_marketing_name,'category_ids'=>[$p->category_id],'is_active'=>1,'editor_revision'=>0,'total_selling_price_cad'=>'31.75'];
        $this->put('/admin/products/'.$p->id,$data)->assertSessionHasNoErrors();
        $this->assertSame('31.75000000',$p->fresh()->total_selling_price_cad);
        $this->putJson('/admin/products/'.$p->id,array_replace($data,['total_selling_price_cad'=>'40']))->assertUnprocessable();
        $this->putJson('/admin/products/'.$p->id,array_replace($data,['editor_revision'=>1,'total_selling_price_cad'=>'-1']))->assertUnprocessable();
        $this->assertSame('31.75000000',$p->fresh()->total_selling_price_cad);
    }

}
