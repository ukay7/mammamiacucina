<?php
namespace Tests\Feature;
use App\Models\{Order,Payment,Role,User,EmailHistory};
use App\Services\{OrderArchive,OrderManagement,OrderAmendment,WarehousePacking,OnlinePayments};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class OrderArchiveTest extends TestCase {
 use RefreshDatabase;
 private function admin(): User {return User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);}
 private function order(array $extra=[]): Order {return Order::create($extra+['checkout_token'=>(string)Str::uuid(),'number'=>'archive-'.Str::random(6),'first_name'=>'Archive','last_name'=>'Buyer','email'=>'archive@example.test','phone'=>'123','address'=>'Address','city'=>'Toronto','province'=>'ON','postal_code'=>'M5V','country'=>'Canada','subtotal_cents'=>1000,'delivery_cents'=>0,'tax_cents'=>0,'currency'=>'CAD','payment_method'=>'cash','payment_status'=>'paid','manual_collected_cents'=>1000,'status'=>'completed','revision'=>0]);}
 public function test_archive_moves_order_to_read_only_list_and_preserves_finances_and_email_count(): void {
  $u=$this->admin();$this->actingAs($u);$o=$this->order(['created_at'=>now()->subMonths(2)]);
  $o->items()->create(['name'=>'Original Cake','product_code'=>'1001','quantity'=>1,'unit_cents'=>1000,'line_cents'=>1000]);
  $emails=EmailHistory::count();
  $this->get(route('admin.orders.show',$o))->assertOk()->assertSee('Archive order');
  $this->post(route('admin.orders.archive',$o),['revision'=>0,'confirm'=>1])->assertRedirect(route('admin.orders.archived'));
  $o->refresh();$this->assertNotNull($o->archived_at);$this->assertEquals($u->id,$o->archived_by);$this->assertSame('completed',$o->status);$this->assertSame(1000,$o->net_received_cents);$this->assertSame(0,$o->balance_cents);$this->assertSame($emails,EmailHistory::count());
  $this->get(route('admin.orders.archived'))->assertOk()->assertSee($o->number)->assertSee('Archived Orders');
  foreach(['admin.orders.index','admin.orders.completed'] as $route)$this->get(route($route,['from'=>now()->subYear()->toDateString()]))->assertOk()->assertDontSee($o->number);
  $this->get(route('admin.orders.show',$o))->assertOk()->assertSee('Read-only')->assertSee('Original Cake')->assertDontSee('Save Order')->assertDontSee('name="confirm"',false);
  $this->get(route('admin.orders.payments',$o))->assertOk()->assertDontSee('Record a payment');
  $this->get(route('admin.orders.export',['view'=>'archived','format'=>'print']))->assertOk()->assertSee($o->number);
  $this->assertSame(1,$o->events()->count());$this->assertSame(1,$o->items()->count());
 }
 public function test_archive_requires_permissions_confirmation_current_revision_and_settled_closed_order(): void {
  $u=$this->admin();$this->actingAs($u);$o=$this->order();
  $this->post(route('admin.orders.archive',$o),['revision'=>0])->assertSessionHasErrors('confirm');
  $this->post(route('admin.orders.archive',$o),['revision'=>99,'confirm'=>1])->assertSessionHasErrors('order');
  foreach([['status'=>'packing'],['manual_collected_cents'=>0],['manual_collected_cents'=>2000],['tax_cents'=>null]] as $extra){$bad=$this->order($extra);$this->post(route('admin.orders.archive',$bad),['revision'=>0,'confirm'=>1])->assertSessionHasErrors('order');$this->assertNull($bad->fresh()->archived_at);}
  $warehouse=User::factory()->create(['is_active'=>true,'role_id'=>Role::where('name','Warehouse User')->value('id')]);
  $this->actingAs($warehouse)->post(route('admin.orders.archive',$o),['revision'=>0,'confirm'=>1])->assertForbidden();
  $this->get(route('admin.orders.archived'))->assertForbidden();
 }
 public function test_archived_writes_are_rejected_from_http_and_locked_services(): void {
  $u=$this->admin();$this->actingAs($u);$o=$this->order();app(OrderArchive::class)->archive($o,0,$u->id);$o->refresh();
  foreach(['admin.orders.archive','admin.orders.amend','admin.orders.settlement','admin.orders.packing'] as $route)$this->postJson(route($route,$o),[])->assertUnprocessable()->assertJsonValidationErrors('order');
  $this->patchJson(route('admin.orders.status',$o),['revision'=>1,'status'=>'cancelled'])->assertUnprocessable();
  foreach([fn()=>app(OrderManagement::class)->update($o,[], $u->id),fn()=>app(OrderAmendment::class)->amend($o,[],$u->id),fn()=>app(OrderAmendment::class)->settle($o,[],$u->id),fn()=>app(WarehousePacking::class)->save($o,[],$u->id)] as $operation){try{$operation();$this->fail('Archived mutation was allowed');}catch(ValidationException $e){$this->assertStringContainsString('archived',$e->getMessage());}}
  $this->assertEquals(1,$o->fresh()->revision);
 }
 public function test_pending_gateway_cannot_be_archived_and_archived_refund_is_blocked(): void {
  $u=$this->admin();$this->actingAs($u);$o=$this->order();
  $p=Payment::create(['order_id'=>$o->id,'reference'=>(string)Str::uuid(),'provider'=>'helcim','mode'=>'sandbox','status'=>'pending','amount_cents'=>1000,'currency'=>'CAD','expires_at'=>now()->addHour(),'paid_at'=>now()]);
  $this->post(route('admin.orders.archive',$o),['revision'=>0,'confirm'=>1])->assertSessionHasErrors('order');
  $p->update(['status'=>'paid']);$this->post(route('admin.orders.archive',$o),['revision'=>0,'confirm'=>1])->assertSessionHasNoErrors();
  $this->postJson(route('admin.payments.refund',$p),['confirm'=>'REFUND','expected_cents'=>1000])->assertUnprocessable();
  $this->expectException(ValidationException::class);app(OnlinePayments::class)->refund($p,$u->id,1000);
 }
}
