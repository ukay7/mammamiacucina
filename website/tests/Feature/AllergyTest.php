<?php
namespace Tests\Feature;
use App\Models\{Allergy,Product,Role,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class AllergyTest extends TestCase {
 use RefreshDatabase;
 public function test_product_assignment_ajax_filter_and_detail():void {
  $this->actingAs(User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]));
  $this->assertDatabaseCount('allergies',11);foreach(Allergy::all() as $a)$this->assertFileExists(public_path($a->asset_path));
  $a=Allergy::where('name','Contains Milk')->firstOrFail();
  $data=['supplier'=>'Test Bakery','qr_code'=>'ALLERGY-TEST','premium_marketing_name'=>'Allergy Cake','category_ids'=>[1],'is_active'=>1,'allergies_present'=>1,'allergy_ids'=>[$a->id]];
  $this->get('/admin/products/create')->assertOk()->assertSee('Contains Milk');
  $this->post('/admin/products',$data)->assertSessionHasNoErrors();$p=Product::firstOrFail();$this->assertCount(1,$p->allergies);
  $this->get('/admin/products/'.$p->id.'/edit')->assertOk();
  $this->getJson('/product-grid?allergy='.$a->id)->assertOk()->assertJsonPath('total',1);
  $this->getJson('/product-grid?allergy='.Allergy::where('name','Egg')->value('id'))->assertOk()->assertJsonPath('total',0);
  $this->get('/products/'.$p->slug)->assertOk()->assertSee('Allergies & Dietary Information',false)->assertSee('CONTAINS MILK.png',false);
  $this->delete('/admin/allergies/'.$a->id)->assertSessionHasErrors('allergy');
  unset($data['allergy_ids']);$this->put('/admin/products/'.$p->id,$data)->assertSessionHasNoErrors();$this->assertCount(0,$p->fresh()->allergies);
  $this->get('/products/'.$p->slug)->assertOk()->assertDontSee('mmc-product-allergies');
 }
 public function test_multiple_labels_match_any_without_duplicates_and_preserve_selection():void {
  $ids=Allergy::orderBy('id')->limit(2)->pluck('id')->all();
  $p=Product::create(['category_id'=>1,'slug'=>'multi-label','premium_marketing_name'=>'Multi Label Cake','is_active'=>true,'total_selling_price_cad'=>10]);$p->allergies()->sync($ids);
  $query=http_build_query(['allergies'=>$ids,'sort'=>'price-low','min'=>5]);
  $this->getJson('/product-grid?'.$query)->assertOk()->assertJsonPath('total',1);
  $this->get('/product-grid?'.$query)->assertOk()->assertSee('name="allergies[]"',false)->assertSee('Search categories');
  $this->getJson('/product-grid?allergies[]=999999')->assertUnprocessable();
 }
 public function test_crud_upload_validation_and_access():void {
  Storage::fake('local');$this->get('/admin/allergies')->assertRedirect();
  $this->actingAs(User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]));
  $this->post('/admin/allergies',['name'=>'Test Label','icon'=>UploadedFile::fake()->create('bad.txt',1)])->assertSessionHasErrors('icon');
  $image=UploadedFile::fake()->createWithContent('icon.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0ZkAAAAASUVORK5CYII='));
  $this->post('/admin/allergies',['name'=>'Test Label','icon'=>$image])->assertSessionHasNoErrors();$a=Allergy::where('name','Test Label')->firstOrFail();Storage::disk('local')->assertExists($a->icon_path);
  $this->get(route('allergy.icon',$a))->assertOk();$this->get('/admin/allergies')->assertOk();$this->get('/admin/allergies/'.$a->id.'/edit')->assertOk();
  $this->put('/admin/allergies/'.$a->id,['name'=>'Updated Label'])->assertSessionHasNoErrors();$this->delete('/admin/allergies/'.$a->id)->assertSessionHasNoErrors();Storage::disk('local')->assertMissing($a->icon_path);
 }
}
