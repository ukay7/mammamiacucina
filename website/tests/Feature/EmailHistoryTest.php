<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail,DB};
use App\Models\{User,Role,EmailHistory};
use App\Services\OutgoingEmail;
class EmailHistoryTest extends TestCase {
 use RefreshDatabase;
 private function customer(){return User::factory()->create(['role_id'=>Role::where('name','Customer')->value('id'),'is_active'=>true,'email_verified_at'=>null,'account_type'=>'individual']);}
 private function admin(){return User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);}
 public function test_shared_resend_cooldown_survives_refresh_and_allows_after_ten_minutes():void {
  $this->freezeTime();config(['mail.default'=>'array']);$user=$this->customer();
  $this->actingAs($user)->post(route('customer.verify.resend'))->assertSessionHasNoErrors();
  $this->assertDatabaseHas('email_history',['recipient'=>$user->email,'type'=>'verification','status'=>'logged_only']);
  $this->actingAs($user->fresh())->get(route('customer.verify.notice'))->assertOk()->assertSee('data-verification-cooldown="600"',false)->assertSee('disabled',false);
  $this->travel(2)->minutes();
  $this->actingAs($user)->post(route('customer.verify.resend'))->assertSessionHasErrors('email');
  $this->actingAs($this->admin())->post(route('admin.customers.resend',$user->customerRecord()))->assertSessionHasErrors('email');
  $this->assertDatabaseCount('email_history',1);
  $this->travel(8)->minutes();
  $this->post(route('admin.customers.resend',$user->customerRecord()))->assertSessionHasNoErrors();
  $this->assertDatabaseCount('email_history',2);
  $this->assertSame('admin_verification',EmailHistory::latest('id')->first()->type);
 }
 public function test_history_has_metadata_without_private_links_and_is_permission_protected():void {
  $this->freezeTime();config(['mail.default'=>'array']);$user=$this->customer();
  app(OutgoingEmail::class)->raw('secret-link?token=private-token',fn($m)=>$m->to($user->email)->subject('Reset password'),'password_reset',$user);
  $entry=EmailHistory::first();$this->assertNotNull($entry->sent_at);$this->assertNotNull($entry->message_id);
  $this->assertStringNotContainsString('private-token',$entry->toJson());
  $this->get(route('admin.email.history'))->assertRedirect();
  $this->actingAs($user)->get(route('admin.email.history'))->assertRedirect();
  $staff=User::factory()->create(['role_id'=>Role::create(['name'=>'Restricted history','permissions'=>[]])->id,'is_active'=>true]);
  $this->actingAs($staff)->get(route('admin.email.history'))->assertForbidden();
  $this->actingAs($this->admin())->get(route('admin.email.history',['search'=>$user->email]))->assertOk()->assertSee($user->email)->assertDontSee('private-token');
  $this->get(route('admin.email.history',['search'=>'missing@example.test']))->assertOk()->assertSee('No email attempts');
 }
 public function test_failed_send_is_recorded_without_secrets_and_allows_retry():void {
  $user=$this->customer();Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('smtp-password private-token'));
  try {app(OutgoingEmail::class)->raw('private-token',fn($m)=>$m->to($user->email)->subject('Verify'),'verification',$user);$this->fail('Expected failure');}
  catch(\Illuminate\Validation\ValidationException $e){$this->assertStringNotContainsString('smtp-password',$e->getMessage());}
  $this->assertDatabaseHas('email_history',['recipient'=>$user->email,'status'=>'failed','sent_at'=>null]);
  $this->assertNull($user->fresh()->verification_last_sent_at);
  $this->assertStringNotContainsString('private-token',EmailHistory::first()->toJson());
 }
 public function test_pos_invitation_starts_the_same_cooldown():void {
  $this->freezeTime();config(['mail.default'=>'array']);$user=$this->customer();
  app(OutgoingEmail::class)->raw('Verify account',fn($m)=>$m->to($user->email)->subject('Invitation'),'pos_invitation',$user);
  $this->actingAs($this->admin())->post(route('admin.customers.resend',$user->customerRecord()))->assertSessionHasErrors('email');
  $this->assertDatabaseCount('email_history',1);
 }
}
