<?php
namespace Tests\Feature;
use App\Models\{User,Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CustomerDirectoryTest extends TestCase {
 use RefreshDatabase;
 private function admin(){return User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);}
 private function customer($name,$extra=[]){$u=User::factory()->create($extra+['name'=>$name,'account_type'=>'individual','is_active'=>true,'role_id'=>Role::where('name','Customer')->value('id')]);return $u->customerRecord();}
 public function test_directory_paginates_sorts_and_preserves_filters(){
  for($i=0;$i<26;$i++)$this->customer(sprintf('Directory %02d',$i));
  $this->actingAs($this->admin());
  $r=$this->get(route('admin.customers.index',['search'=>'Directory','per_page'=>10,'sort'=>'name','direction'=>'asc']));
  $r->assertOk()->assertViewHas('customers',fn($p)=>$p->count()===10 && $p->total()===26 && $p->first()->name==='Directory 00' && $p->first()->orders_count===0);
  $r->assertSee('per_page=10',false)->assertDontSee('Directory 10')->assertDontSee('Approve business');
  $this->get(route('admin.customers.index',['search'=>'Directory','per_page'=>10,'sort'=>'name','direction'=>'asc','page'=>2]))->assertOk()->assertSee('Directory 10')->assertDontSee('Directory 00');
  $this->getJson(route('admin.customers.index',['per_page'=>500000]))->assertUnprocessable();
  $this->getJson(route('admin.customers.index',['sort'=>'password']))->assertUnprocessable();
  $columns='id,identity,status';
  $this->get(route('admin.customers.index',['search'=>'Directory','per_page'=>10,'sort'=>'name','direction'=>'asc','columns'=>$columns]))
   ->assertOk()->assertViewHas('selectedColumns',['id','identity','status'])->assertSee('columns=id%2Cidentity%2Cstatus',false);
  $this->get(route('admin.customers.index',['search'=>'Directory','per_page'=>10,'sort'=>'name','direction'=>'asc','columns'=>$columns,'page'=>2]))
   ->assertOk()->assertViewHas('selectedColumns',['id','identity','status'])->assertSee('Directory 10')->assertDontSee('Directory 00');
  $this->get(route('admin.customers.index',['columns'=>'']))->assertOk()->assertViewHas('selectedColumns',[]);
  $this->getJson(route('admin.customers.index',['columns'=>'id,password']))->assertUnprocessable();
  $this->getJson(route('admin.customers.index',['columns'=>['id']]))->assertUnprocessable();
 }
 public function test_filters_search_and_separate_pending_approval_screen(){
  $pending=$this->customer('Pending Owner',['account_type'=>'business','email_verified_at'=>null,'is_active'=>false]);
  $pending->update(['business_name'=>'Unique Bakery','business_bin'=>'BIN-XY123','business_phone'=>'987654321','business_email'=>'billing@bakery.test']);
  $this->customer('Approved Owner',['account_type'=>'business','business_approved_at'=>now()]);$this->customer('Retail Owner');
  $this->actingAs($this->admin());
  foreach(['BIN-XY123','Unique Bakery','billing@bakery.test','987654321','#'.$pending->id] as $search)$this->get(route('admin.customers.index',['search'=>$search]))->assertOk()->assertViewHas('customers',fn($p)=>$p->total()===1 && $p->first()->id===$pending->id);
  $this->get(route('admin.customers.index',['account_type'=>'business','approval'=>'pending','active'=>'0','verified'=>'0']))->assertOk()->assertViewHas('customers',fn($p)=>$p->total()===1 && $p->first()->id===$pending->id);
  $this->get(route('admin.customers.business-requests'))->assertOk()->assertSee('Unique Bakery')->assertDontSee('Approved Owner')->assertDontSee('Retail Owner')->assertViewHas('businessRequests',fn($p)=>$p->total()===1);
  $this->get(route('admin.customers.index'))->assertOk()->assertDontSee('Business approval requests <small>',false);
  $staff=User::factory()->create(['is_active'=>true,'role_id'=>Role::where('name','Warehouse User')->value('id')]);
  $this->actingAs($staff)->get(route('admin.customers.business-requests'))->assertForbidden();
 }
}
