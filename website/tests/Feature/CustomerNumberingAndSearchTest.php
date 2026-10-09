<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\Facades\{DB,Mail};
use App\Models\{Customer,User,Role,Product};
class CustomerNumberingAndSearchTest extends TestCase {
 protected function setUp():void {parent::setUp();$this->artisan('migrate:fresh')->assertSuccessful();}
 protected function tearDown():void {\Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated=false;parent::tearDown();}
 private function customer($name){return User::factory()->create(['name'=>$name,'account_type'=>'individual','role_id'=>Role::where('name','Customer')->value('id'),'is_active'=>true]);}
 private function admin(){return User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);}
 public function test_numbering_is_repeatable_and_preserves_existing_records():void {
  $old=$this->customer('Previous')->customerRecord();
  $this->artisan('app:initialize-numbering')->assertSuccessful();
  $next=$this->customer('Next')->customerRecord();
  $this->assertSame(4000,$next->id);
  $this->artisan('app:initialize-numbering')->assertSuccessful();
  $later=$this->customer('Later')->customerRecord();
  $this->assertSame(4001,$later->id);$this->assertSame('Previous',$old->fresh()->name);
  $driver=DB::connection()->getDriverName();
  if($driver==='sqlite')$this->assertSame(2999,(int)DB::table('sqlite_sequence')->where('name','orders')->value('seq'));
  else $this->assertSame(3000,(int)DB::selectOne('SELECT AUTO_INCREMENT AS next_id FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [DB::connection()->getDatabaseName(),'orders'])->next_id);
 }
 public function test_business_fields_required_and_saved_on_profile_and_admin_edit():void {
  Mail::fake();$data=['name'=>'Owner','email'=>'owner@example.test','phone'=>'123456','account_type'=>'business','password'=>'strong-password-123','password_confirmation'=>'strong-password-123'];
  $this->post(route('customer.register.store'),$data)->assertSessionHasErrors(['business_bin','business_name','business_phone','business_email']);
  $fields=['business_bin'=>'BIN-123','business_name'=>'Company Ltd','business_phone'=>'55512345','business_email'=>'office@example.test'];
  $this->post(route('customer.register.store'),$data+$fields)->assertRedirect(route('theme.index'));
  $u=User::where('email',$data['email'])->firstOrFail();$u->forceFill(['email_verified_at'=>now(),'business_approved_at'=>now()])->save();
  $this->assertDatabaseHas('customers',$fields+['user_id'=>$u->id]);
  $this->actingAs($u->fresh())->get(route('customer.profile'))->assertOk()->assertSee('Company Ltd');
  $this->put(route('customer.profile.update'),['name'=>'Owner','phone'=>'123456']+$fields+['email'=>'changed@example.test','account_type'=>'individual'])->assertSessionHasNoErrors();
  $this->assertSame('business',$u->fresh()->account_type);$this->assertSame($data['email'],$u->fresh()->email);
  $this->actingAs($this->admin());$c=$u->customerRecord();
  $this->get(route('admin.customers.edit',$c))->assertOk()->assertSee('BIN-123');
  $fields['business_name']='Updated Company';
  $this->put(route('admin.customers.update',$c),['name'=>'Owner']+$fields)->assertSessionHasNoErrors();
  $this->assertSame('Updated Company',$c->fresh()->business_name);
  $this->get(route('admin.customers.index',['search'=>'#'.$c->id]))->assertOk()->assertSee('owner@example.test');
 }
 public function test_product_code_barcode_and_id_search_across_catalogue_and_admin():void {
  $p=Product::create(['category_id'=>1,'slug'=>'search-cake','premium_marketing_name'=>'Search Cake','product_code'=>'CODE-AB12','qr_code'=>'00998877','is_active'=>true,'total_selling_price_cad'=>10]);
  Product::create(['category_id'=>1,'slug'=>'other-cake','premium_marketing_name'=>'Other Cake','qr_code'=>'UNRELATED','is_active'=>true]);
  foreach(['CODE-AB12','00998877','#'.$p->id] as $q){
   $this->getJson('/product-grid?'.http_build_query(['q'=>$q]))->assertOk()->assertJsonPath('total',1);
   $this->assertSame([$p->id],Product::searchTerm($q)->pluck('id')->all());
  }
  $this->actingAs($this->admin());
  foreach(['CODE-AB12','00998877','#'.$p->id] as $q){
   $this->get('/admin/products?'.http_build_query(['q'=>$q]))->assertOk()->assertSee('Search Cake')->assertDontSee('Other Cake');
   $this->get('/admin/inventory?'.http_build_query(['q'=>$q]))->assertOk()->assertSee('Search Cake')->assertDontSee('Other Cake');
   $this->getJson(route('admin.pos.products',['customer_id'=>User::factory()->create(['account_type'=>'individual','is_active'=>true])->customerRecord()->id,'q'=>$q]))->assertOk()->assertJsonPath('products.0.id',$p->id)->assertJsonCount(1,'products');
  }
 }
}
