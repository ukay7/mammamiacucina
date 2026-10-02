<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Role,Product,Quotation};
class QuotationTest extends TestCase {
 use RefreshDatabase;
 private function admin(){return User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);}
 public function test_admin_customer_creation_and_business_approval_are_separate():void {
  $this->actingAs($this->admin());$this->get(route('admin.customers.create'))->assertOk();
  $d=['name'=>'New Customer','email'=>'NEW@example.test','password'=>'safe-password-123','password_confirmation'=>'safe-password-123','account_type'=>'individual','address'=>'12 Test Street','website'=>'https://example.test'];
  $this->post(route('admin.customers.store'),$d)->assertSessionHasNoErrors()->assertRedirect();$u=User::where('email','new@example.test')->firstOrFail();$this->assertTrue(\Illuminate\Support\Facades\Hash::check($d['password'],$u->password));$this->assertNotNull($u->email_verified_at);$this->assertSame('12 Test Street',$u->customer->address);$this->assertNull($u->role_id);
  $this->post(route('admin.customers.store'),$d)->assertSessionHasErrors('email');
  $d=array_replace($d,['email'=>'biz@example.test','account_type'=>'business','business_bin'=>'123','business_name'=>'Business','business_phone'=>'123','business_email'=>'biz@example.test']);$this->post(route('admin.customers.store'),$d)->assertSessionHasNoErrors();$u=User::where('email','biz@example.test')->firstOrFail();$this->assertNull($u->business_approved_at);$this->assertNull($u->email_verified_at);
 }
 public function test_quote_calculations_snapshots_edit_print_and_delete():void {
  $this->actingAs($this->admin());$buyer=User::factory()->create(['account_type'=>'business','is_active'=>true,'email_verified_at'=>now(),'business_approved_at'=>now()]);$c=$buyer->customerRecord();
  $p=Product::create(['category_id'=>1,'slug'=>'quote-cake','premium_marketing_name'=>'Quote Cake','is_active'=>true,'total_selling_price_cad'=>15,'business_selling_price_cad'=>12]);
  $d=['customer_id'=>$c->id,'due_date'=>now()->addDays(20)->format('Y-m-d'),'pricing_tier'=>'auto','items'=>[['product_id'=>$p->id,'quantity'=>2,'unit_price'=>'20.00','discount_percent'=>'10.00']],'discount_percent'=>'10.00','charge_percent'=>'10','tax_percent'=>'13','notes'=>'Special offer'];
  $this->get(route('admin.quotations.create'))->assertOk();$this->post(route('admin.quotations.store'),$d)->assertSessionHasNoErrors();$q=Quotation::firstOrFail();$this->assertSame('business',$q->pricing_tier);$this->assertEquals(4027,$q->total_cents);$this->assertEquals(2000,$q->items[0]['unit_cents']);
  foreach(['index','show','edit','print'] as $route)$this->get(route('admin.quotations.'.$route,$route==='index'?[]:$q))->assertOk();
  $p->update(['business_selling_price_cad'=>99]);$this->put(route('admin.quotations.update',$q),$d+['revision'=>1])->assertSessionHasNoErrors();$this->assertEquals(4027,$q->fresh()->total_cents);
  $this->put(route('admin.quotations.update',$q),$d+['revision'=>1])->assertSessionHasErrors('revision');
  $this->post(route('admin.quotations.store'),array_replace($d,['discount_percent'=>101]))->assertSessionHasErrors('discount_percent');
  $this->post(route('admin.quotations.store'),array_replace($d,['items'=>[]]))->assertSessionHasErrors('items');
   $bad=$d;$bad['items'][0]['unit_price']='-1';$this->post(route('admin.quotations.store'),$bad)->assertSessionHasErrors('items.0.unit_price');$bad=$d;$bad['items'][0]['discount_percent']=101;$this->post(route('admin.quotations.store'),$bad)->assertSessionHasErrors('items.0.discount_percent');$this->assertEquals(1000,$q->fresh()->discount_basis_points);$this->assertEquals(1000,$q->fresh()->items[0]['discount_basis_points']);$this->assertEquals(15,(float)$p->fresh()->total_selling_price_cad);$this->assertDatabaseCount('orders',0);$this->delete(route('admin.quotations.destroy',$q),['revision'=>1])->assertStatus(409);$this->delete(route('admin.quotations.destroy',$q),['revision'=>2])->assertRedirect();$this->assertDatabaseCount('quotations',0);
 }
 public function test_permissions_and_pending_business_default_to_individual():void {
  $staff=User::factory()->create(['is_active'=>true,'role_id'=>Role::create(['name'=>'Restricted','permissions'=>[]])->id]);$this->actingAs($staff)->get(route('admin.quotations.index'))->assertForbidden();$this->post(route('admin.customers.store'),[])->assertForbidden();
  $this->actingAs($this->admin());$buyer=User::factory()->create(['account_type'=>'business','business_approved_at'=>null]);$p=Product::create(['category_id'=>1,'slug'=>'pending-cake','premium_marketing_name'=>'Pending Cake','is_active'=>true,'total_selling_price_cad'=>15,'business_selling_price_cad'=>12]);
  $d=['customer_id'=>$buyer->customerRecord()->id,'due_date'=>now()->format('Y-m-d'),'pricing_tier'=>'auto','items'=>[['product_id'=>$p->id,'quantity'=>1,'unit_price'=>'15.00','discount_percent'=>0]],'discount_percent'=>0,'charge_percent'=>0,'tax_percent'=>0];$this->post(route('admin.quotations.store'),$d)->assertSessionHasNoErrors();$this->assertSame('individual',Quotation::first()->pricing_tier);$this->assertEquals(1500,Quotation::first()->total_cents);
 }
}
