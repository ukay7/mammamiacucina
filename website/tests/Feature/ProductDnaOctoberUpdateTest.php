<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,DB,File};
use App\Models\Product;
use App\Services\ProductDnaOctoberUpdate;
class ProductDnaOctoberUpdateTest extends TestCase {
 use RefreshDatabase;
 private string $backupRoot;
 protected function setUp():void {parent::setUp();$this->backupRoot=storage_path('framework/testing/dna-'.bin2hex(random_bytes(5)));config(['product_dna_update.backup_root'=>$this->backupRoot]);Storage::fake('local');}
 protected function tearDown():void {File::deleteDirectory($this->backupRoot);parent::tearDown();}
 public function test_update_preserves_images_status_identifiers_and_runs_once():void {
  $p=Product::create(['category_id'=>1,'slug'=>'keep-slug','premium_marketing_name'=>'Old name','qr_code'=>'100020','product_code'=>'0010821','status'=>'Keep status','is_active'=>false,'total_selling_price_cad'=>99,'business_selling_price_cad'=>88,'dna'=>['image_present'=>'1','image_link'=>'https://example.test/image','ingredients_text'=>'Keep recipe']]);
  Storage::disk('local')->put('products/keep.jpg','image-bytes');$p->media()->create(['path'=>'products/keep.jpg','kind'=>'image','mime_type'=>'image/jpeg','original_name'=>'keep.jpg','sort_order'=>0]);
  $service=app(ProductDnaOctoberUpdate::class);$preview=$service->run();$this->assertSame(0,$preview['updated']);$this->assertEquals(99,$p->fresh()->total_selling_price_cad);
  $report=$service->run(true);$p->refresh();$this->assertSame(1,$report['updated']);$this->assertDatabaseCount('products',1);$this->assertSame('100020',$p->qr_code);$this->assertSame('keep-slug',$p->slug);$this->assertSame('Keep status',$p->status);$this->assertFalse($p->is_active);
  $this->assertEquals(25.92,$p->business_selling_price_cad);$this->assertEquals(43.20,$p->total_selling_price_cad);$this->assertSame('Keep recipe',$p->dna['ingredients_text']);$this->assertSame('https://example.test/image',$p->dna['image_link']);$this->assertSame('image-bytes',Storage::disk('local')->get('products/keep.jpg'));$this->assertDatabaseCount('product_media',1);
  $this->assertSame('150',$p->pricingDraft->inputs['profit_percent']);$this->assertSame('50',$p->pricingDraft->inputs['business_profit_percent']);
  $backup=json_decode(file_get_contents($report['backup'].'/tables.json'),true);$this->assertEquals(99,$backup['products'][0]['total_selling_price_cad']);$zip=new \ZipArchive;$zip->open($report['backup'].'/product-files.zip');$this->assertSame('image-bytes',$zip->getFromName('products/keep.jpg'));$zip->close();
  $p->update(['total_selling_price_cad'=>123]);$this->assertTrue($service->run(true)['already_applied']);$this->assertEquals(123,$p->fresh()->total_selling_price_cad);
 }
 public function test_incomplete_prices_are_preserved_and_unmatched_rows_never_insert():void {
  $p=Product::create(['category_id'=>1,'slug'=>'missing-price','qr_code'=>'100050','total_selling_price_cad'=>44,'business_selling_price_cad'=>33]);
  $report=app(ProductDnaOctoberUpdate::class)->run(true);$this->assertSame(1,$report['updated']);$this->assertSame(77,$report['skipped']);$this->assertDatabaseCount('products',1);$this->assertEquals(44,$p->fresh()->total_selling_price_cad);$this->assertEquals(33,$p->fresh()->business_selling_price_cad);
 }
 public function test_missing_image_aborts_before_any_product_update():void {
  $p=Product::create(['category_id'=>1,'slug'=>'missing-image','qr_code'=>'100020','total_selling_price_cad'=>99]);$p->media()->create(['path'=>'missing.jpg','kind'=>'image','mime_type'=>'image/jpeg','original_name'=>'missing.jpg','sort_order'=>0]);
  try{app(ProductDnaOctoberUpdate::class)->run(true);$this->fail('Must stop without a complete backup');}catch(\RuntimeException $e){$this->assertStringContainsString('file missing',$e->getMessage());}
  $this->assertEquals(99,$p->fresh()->total_selling_price_cad);$this->assertDatabaseCount('product_pricing_drafts',0);
 }
}
