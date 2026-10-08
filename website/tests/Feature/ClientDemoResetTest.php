<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\{User,Role};
use App\Services\ClientDemoReset;
class ClientDemoResetTest extends TestCase {
 use RefreshDatabase;
 public function test_preview_and_execute_guard_preserve_data():void {
  $u=User::factory()->create(['account_type'=>'individual']);
  $this->artisan('app:reset-client-demo')->assertSuccessful();
  $this->assertDatabaseHas('users',['id'=>$u->id]);
  $this->artisan('app:reset-client-demo --execute --force')->assertFailed();
  $this->assertDatabaseHas('users',['id'=>$u->id]);
 }
 public function test_reset_removes_customers_and_preserves_staff_and_settings():void {
  $admin=User::factory()->create(['account_type'=>null,'role_id'=>Role::where('is_super',true)->value('id')]);
  $u=User::factory()->create(['account_type'=>'individual']);
  $c=$u->customerRecord();
  DB::table('customer_email_verifications')->insert(['user_id'=>$u->id,'verified_by'=>$admin->id,'reason'=>'Test','created_at'=>now()]);
  DB::table('password_reset_tokens')->insert(['email'=>$u->email,'token'=>'test','created_at'=>now()]);
  $order=DB::table('orders')->insertGetId(array_fill_keys(['first_name','last_name','phone','address','city','province','postal_code','country'],'Test')+['email'=>$u->email,'checkout_token'=>'reset-test','number'=>'mmc-test','subtotal_cents'=>100,'customer_id'=>$c->id]);
  DB::table('order_items')->insert(['order_id'=>$order,'name'=>'Test item','quantity'=>1,'unit_cents'=>100,'line_cents'=>100]);
  DB::table('order_events')->insert(['order_id'=>$order,'user_id'=>$admin->id,'description'=>'Test','created_at'=>now()]);
  $payment=DB::table('payments')->insertGetId(['order_id'=>$order,'reference'=>'test-ref','provider'=>'stripe','mode'=>'test','amount_cents'=>100,'currency'=>'CAD','expires_at'=>now()]);
  DB::table('payment_refunds')->insert(['payment_id'=>$payment,'provider_refund_id'=>'test-refund','amount_cents'=>10,'status'=>'succeeded']);
  DB::table('payment_events')->insert(['provider'=>'stripe','mode'=>'test','event_id'=>'keep-replay-guard']);
  DB::table('quotations')->insert(array_fill_keys(['subtotal_cents','line_discount_cents','discount_cents','charge_cents','tax_cents','total_cents','charge_basis_points','tax_basis_points'],0)+['customer_id'=>$c->id,'created_by'=>$admin->id,'due_date'=>now()->toDateString(),'pricing_tier'=>'individual','customer_snapshot'=>'{}','items'=>'[]']);
  $settings=DB::table('general_settings')->first();
  app(ClientDemoReset::class)->run();
  $this->assertDatabaseMissing('users',['id'=>$u->id]);
  $this->assertDatabaseHas('users',['id'=>$admin->id]);
  foreach(['customers','orders','order_items','order_events','payments','payment_refunds','quotations'] as $table)$this->assertDatabaseCount($table,0);
  $this->assertDatabaseCount('payment_events',1);
  $this->assertDatabaseCount('customer_email_verifications',0);
  $this->assertDatabaseCount('password_reset_tokens',0);
  $this->assertEquals($settings,DB::table('general_settings')->first());
 }
}
