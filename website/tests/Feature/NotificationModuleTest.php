<?php
namespace Tests\Feature;

use App\Models\{Customer, NotificationCampaign, NotificationDelivery, NotificationEvent, NotificationSetting, NotificationSuppression, Role, SmtpSetting, User};
use App\Services\{StaffPermissions, OutgoingEmail};
use App\Services\Notifications\{Audience, Campaigns, EmailChannel, Events, Message, Outbox};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, DB, Http, Mail};
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url'=>'https://mmc.example.test']);
        $this->actingAs(User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]));
        SmtpSetting::forceCreate(['id'=>1,'enabled'=>true,'delivery_method'=>'resend','api_key'=>'re_notification_secret','from_address'=>'sender@example.test','from_name'=>'Mamma Mia','revision'=>0,'tested_revision'=>0,'tested_at'=>now()]);
        $this->fakeProvider(['api.resend.com/emails'=>fn()=>Http::response(['id'=>(string)Str::uuid()],200)]);
    }
    private function fakeProvider($responses=null): void
    {
        // Laravel merges successive fake callbacks; reset the factory so each scenario replaces the provider.
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake($responses ?? ['api.resend.com/emails'=>fn()=>Http::response(['id'=>(string)Str::uuid()],200)]);
    }
    private function draft(array $replace=[]): NotificationCampaign
    {
        return app(Campaigns::class)->save(null,array_replace(['title'=>'November news','subject'=>'New pastries','body'=>"Hello!\nSee https://example.test/menu for details.",'audience_mode'=>'selected','customer_type'=>'all','audience'=>['email:recipient@example.test'],'include_alternate'=>true,'track_opens'=>true,'track_clicks'=>true],$replace),auth()->id());
    }
    private function customer(string $email, array $replace=[]): Customer
    {
        $u=User::factory()->create(array_replace(['name'=>'Sample Customer','email'=>$email,'account_type'=>'individual','is_active'=>true],$replace));
        return $u->customerRecord();
    }
    private function tested(?NotificationCampaign $campaign=null): NotificationCampaign
    {
        $campaign??=$this->draft();
        app(Campaigns::class)->test($campaign,$campaign->revision,'tester@example.test',(string)Str::uuid());
        return $campaign->fresh();
    }
    private function queued(?NotificationCampaign $campaign=null): NotificationCampaign
    {
        $campaign=$this->tested($campaign);
        $review=app(Campaigns::class)->review($campaign,$campaign->revision,auth()->id(),null);
        return app(Campaigns::class)->queue($campaign,$review['token'],auth()->id());
    }
    private function bulk(NotificationCampaign $campaign): NotificationDelivery { return $campaign->deliveries()->where('is_test',false)->firstOrFail(); }
    private function webhook(string $type, string $messageId, string $eventId, ?string $at=null)
    {
        $secret='whsec_'.base64_encode('test-webhook-signing-key');
        NotificationSetting::updateOrCreate(['id'=>1],['webhook_secret'=>$secret]);
        $body=json_encode(['type'=>$type,'created_at'=>$at??now()->toIso8601String(),'data'=>['email_id'=>$messageId]]);
        $time=(string)now()->timestamp;
        $signature='v1,'.base64_encode(hash_hmac('sha256',$eventId.'.'.$time.'.'.$body,base64_decode(substr($secret,6)),true));
        return $this->call('POST','/notifications/webhooks/resend',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_SVIX_ID'=>$eventId,'HTTP_SVIX_TIMESTAMP'=>$time,'HTTP_SVIX_SIGNATURE'=>$signature],$body);
    }
    public function test_draft_screens_preview_and_audience_validation_never_send(): void
    {
        $c=$this->customer('buyer@example.test');
        $this->get(route('admin.notifications.index'))->assertOk()->assertSee('New notification');
        $this->get(route('admin.notifications.create'))->assertOk()->assertSee('select2.full.min.js')->assertSee('All customers');
        $this->getJson(route('admin.notifications.customers',['q'=>(string)$c->id]))->assertOk()->assertJsonPath('results.0.id','customer:'.$c->id);
        $draft=$this->draft(['body'=>'<script>alert(1)</script> https://example.test/menu']);
        $this->get(route('admin.notifications.edit',$draft))->assertOk()->assertSee('Test before sending');
        $this->get(route('admin.notifications.show',$draft))->assertOk()->assertSee('Recipient report');
        $this->postJson(route('admin.notifications.preview'),['subject'=>'Preview','body'=>$draft->body])->assertOk()->assertSee('&lt;script&gt;',false)->assertDontSee('<script>',false)->assertDontSee('/notifications/open/',false);
        $data=$draft->only(['title','subject','body','audience_mode','customer_type','audience','include_alternate','track_opens','track_clicks','revision']);
        $this->post(route('admin.notifications.store'),array_replace($data,['audience'=>['email:not-an-email']]))->assertSessionHasErrors('audience');
        $this->post(route('admin.notifications.store'),array_replace($data,['subject'=>"Hello\r\nBcc: wrong@example.test"]))->assertSessionHasErrors('subject');
        Http::assertNothingSent();
    }
    public function test_all_customers_deduplicates_alternates_excludes_inactive_and_reports_suppressions(): void
    {
        $a=$this->customer('first@example.test'); $a->update(['alternate_email'=>'SHARED@example.test']);
        $b=$this->customer('second@example.test',['account_type'=>'business']); $b->update(['alternate_email'=>'shared@example.test']);
        $this->customer('inactive@example.test',['is_active'=>false]);
        NotificationSuppression::create(['channel'=>'email','address'=>'second@example.test','reason'=>'unsubscribed']);
        $draft=$this->draft(['audience_mode'=>'all','audience'=>['email:shared@example.test','email:new@example.test']]);
        $result=app(Audience::class)->resolve($draft);
        $this->assertSame(4,count($result['rows'])); $this->assertSame(3,$result['eligible']); $this->assertSame(1,$result['suppressed']); $this->assertSame(2,$result['duplicates']);
        $this->assertNotContains('inactive@example.test',array_column($result['rows'],'address'));
        $draft->customer_type='business'; $draft->include_alternate=false; $draft->audience=[];
        $this->assertSame(['second@example.test'],array_column(app(Audience::class)->resolve($draft)['rows'],'address'));
    }
    public function test_test_send_is_explicit_idempotent_and_required_for_exact_revision(): void
    {
        $customer=$this->customer('tester@example.test'); $customer->update(['alternate_email'=>'not-a-test-recipient@example.test']);
        $draft=$this->draft(); $key=(string)Str::uuid();
        $this->post(route('admin.notifications.review',$draft),['revision'=>0])->assertSessionHasErrors('notification');
        foreach(range(1,2) as $_) $this->post(route('admin.notifications.test',$draft),['recipient'=>'tester@example.test','test_key'=>$key,'revision'=>0])->assertSessionHasNoErrors();
        Http::assertSentCount(1);
        Http::assertSent(fn($r)=>$r['to']===['tester@example.test'] && str_starts_with($r['subject'],'[TEST]') && !str_contains($r['html'],'/notifications/open/') && !isset($r['headers']));
        $this->assertSame(0,$draft->fresh()->tested_revision);
        app(Campaigns::class)->save($draft,$draft->only(['title','subject','body','audience_mode','customer_type','audience','include_alternate','track_opens','track_clicks'])+['revision'=>0],auth()->id());
        $this->assertNull($draft->fresh()->tested_revision);
        $this->post(route('admin.notifications.review',$draft),['revision'=>1])->assertSessionHasErrors('notification');
    }
    public function test_queue_uses_reviewed_snapshot_and_double_confirmation_cannot_duplicate(): void
    {
        $customer=$this->customer('before@example.test');
        $draft=$this->tested($this->draft(['audience_mode'=>'all','audience'=>[]]));
        $review=app(Campaigns::class)->review($draft,0,auth()->id(),null);
        $customer->update(['email'=>'after@example.test']); $this->customer('later@example.test');
        for($i=0;$i<2;$i++) $this->post(route('admin.notifications.queue',$draft),['review_token'=>$review['token'],'confirmed'=>1])->assertSessionHasNoErrors();
        $this->assertSame(['before@example.test'],$draft->deliveries()->where('is_test',false)->pluck('address')->all());
        $this->assertSame('queued',$draft->fresh()->status);
        $this->assertSame('New pastries',$draft->fresh()->snapshot['subject']);
        $this->get(route('admin.notifications.edit',$draft))->assertStatus(409);
        Http::assertSentCount(1);
    }
    public function test_review_tokens_expire_and_are_bound_to_staff(): void
    {
        $draft=$this->tested(); $review=app(Campaigns::class)->review($draft,0,auth()->id(),null);
        $this->actingAs(User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]));
        $this->post(route('admin.notifications.queue',$draft),['review_token'=>$review['token'],'confirmed'=>1])->assertSessionHasErrors('notification');
        $this->get(route('admin.notifications.review-export',$draft))->assertForbidden();
        $this->travel(16)->minutes();
        $this->post(route('admin.notifications.queue',$draft),['review_token'=>$review['token'],'confirmed'=>1])->assertSessionHasErrors('notification');
        $this->assertSame(0,$draft->deliveries()->where('is_test',false)->count());
    }
    public function test_bulk_messages_are_private_tracked_and_sender_content_is_frozen(): void
    {
        $draft=$this->queued($this->draft(['audience'=>['email:one@example.test','email:two@example.test']]));
        $this->fakeProvider(['api.resend.com/emails'=>fn()=>Http::response(['id'=>(string)Str::uuid()],200)]);
        foreach($draft->deliveries()->where('is_test',false)->get() as $d) $this->assertTrue(app(Outbox::class)->deliver($d->id));
        Http::assertSentCount(2);
        Http::assertSent(fn($r)=>count($r['to'])===1 && isset($r['headers']['List-Unsubscribe-Post']) && str_contains($r['html'],'https://mmc.example.test/notifications/open/') && str_contains($r['html'],'/notifications/click/'));
        $this->assertSame('completed',$draft->fresh()->status);
        $this->assertSame(2,$draft->deliveries()->where('is_test',false)->where('status','accepted')->count());
        $this->assertNull($this->bulk($draft)->payload);
    }
    public function test_resend_retry_preserves_payload_key_and_respects_backoff(): void
    {
        $draft=$this->queued(); $d=$this->bulk($draft);
        $this->fakeProvider(['api.resend.com/emails'=>Http::sequence()->push(['error'=>'provider secret re_bad'],500)->push(['id'=>'recovered'],200)]);
        $this->assertFalse(app(Outbox::class)->deliver($d->id));
        $d->refresh(); $this->assertSame('retry',$d->status); $this->assertStringNotContainsString('provider secret',$d->note);
        $payload=$d->payload;
        $this->assertStringNotContainsString('Hello!',DB::table('notification_deliveries')->where('id',$d->id)->value('payload'));
        $this->assertFalse(app(Outbox::class)->deliver($d->id)); Http::assertSentCount(1);
        $this->travelTo($d->available_at->copy()->addSecond());
        $this->assertTrue(app(Outbox::class)->deliver($d->id));
        $requests=Http::recorded(); $this->assertSame($requests[0][0]->data(),$requests[1][0]->data());
        $this->assertSame($requests[0][0]->header('Idempotency-Key'),$requests[1][0]->header('Idempotency-Key'));
        $this->assertSame($payload['html'],$requests[1][0]['html']);
        $this->assertFalse(app(Outbox::class)->deliver($d->id)); Http::assertSentCount(2);
    }
    public function test_changed_credentials_and_uncertain_smtp_are_not_retried(): void
    {
        $campaign=$this->queued(); $d=$this->bulk($campaign);
        $d->update(['status'=>'sending','provider'=>'smtp','attempts'=>1,'first_attempt_at'=>now()->subMinutes(10),'started_at'=>now()->subMinutes(10)]);
        $this->fakeProvider(); $this->assertFalse(app(Outbox::class)->deliver($d->id));
        $this->assertSame('needs_review',$d->fresh()->status); Http::assertNothingSent();
        $other=$this->queued(); $entry=$this->bulk($other);
        SmtpSetting::find(1)->update(['revision'=>1,'tested_revision'=>1]);
        $this->assertFalse(app(Outbox::class)->deliver($entry->id));
        $this->assertSame('needs_review',$entry->fresh()->status);
    }
    public function test_pause_cancel_schedule_and_dry_run_do_not_send(): void
    {
        $draft=$this->tested(); $review=app(Campaigns::class)->review($draft,0,auth()->id(),now()->addHour());
        $campaign=app(Campaigns::class)->queue($draft,$review['token'],auth()->id()); $d=$this->bulk($campaign);
        $this->fakeProvider(); $this->assertFalse(app(Outbox::class)->deliver($d->id));
        $this->artisan('notifications:send --dry-run')->expectsOutputToContain('No email sent')->assertSuccessful();
        app(Campaigns::class)->transition($campaign,'pause'); $this->travel(2)->hours();
        $this->assertFalse(app(Outbox::class)->deliver($d->id));
        app(Campaigns::class)->transition($campaign,'cancel');
        $this->assertSame('cancelled',$d->fresh()->status); Http::assertNothingSent();
    }
    public function test_open_click_unsubscribe_and_suppression_are_separate_from_transactional_email(): void
    {
        $campaign=$this->queued(); $d=$this->bulk($campaign); app(Outbox::class)->deliver($d->id); $d->refresh();
        $open='/notifications/open/'.$d->token.'.gif';
        $this->get($open)->assertOk()->assertHeader('Content-Type','image/gif'); $this->get($open)->assertOk();
        $this->assertSame(2,$d->fresh()->open_count); $this->assertSame(1,$d->events()->where('type','open')->count());
        $link=$campaign->links()->first();
        $this->get('/notifications/click/'.$d->token.'/'.$link->token)->assertRedirect('https://example.test/menu');
        $this->assertSame(1,$d->fresh()->click_count);
        $unsubscribe='/notifications/unsubscribe/'.$d->token;
        $this->get($unsubscribe)->assertOk(); $this->assertDatabaseCount('notification_suppressions',0);
        $this->post($unsubscribe,['List-Unsubscribe'=>'One-Click'])->assertOk()->assertSee('You are unsubscribed');
        $this->post($unsubscribe)->assertOk(); $this->assertDatabaseCount('notification_suppressions',1);
        $this->assertSame(0,app(Audience::class)->resolve($this->draft())['eligible']);
        app(OutgoingEmail::class)->raw('Order receipt',fn($m)=>$m->to($d->address)->subject('Receipt'),'payment_received');
        Http::assertSent(fn($r)=>$r['subject']==='Receipt');
    }
    public function test_unsubscribe_after_review_is_checked_again_before_dispatch(): void
    {
        $campaign=$this->queued(); $d=$this->bulk($campaign);
        NotificationSuppression::create(['channel'=>'email','address'=>$d->address,'reason'=>'unsubscribed']);
        $this->fakeProvider(); $this->assertFalse(app(Outbox::class)->deliver($d->id));
        $this->assertSame('suppressed',$d->fresh()->status); Http::assertNothingSent();
    }
    public function test_webhooks_are_verified_deduplicated_and_cannot_regress_outcomes(): void
    {
        $campaign=$this->queued(); $d=$this->bulk($campaign); app(Outbox::class)->deliver($d->id); $d->refresh();
        $this->postJson('/notifications/webhooks/resend',['type'=>'email.delivered'])->assertStatus(503);
        $this->webhook('email.delivered',$d->provider_message_id,'event-delivered')->assertOk();
        $this->webhook('email.delivered',$d->provider_message_id,'event-delivered')->assertOk();
        $this->assertSame(1,NotificationEvent::where('event_key','event-delivered')->count());
        $this->assertNotNull($d->fresh()->delivered_at);
        $this->webhook('email.complained',$d->provider_message_id,'event-complaint')->assertOk();
        $this->webhook('email.sent',$d->provider_message_id,'event-late-sent')->assertOk();
        $this->webhook('email.delivered',$d->provider_message_id,'event-late-delivered')->assertOk();
        $this->assertSame('complained',$d->fresh()->outcome);
        $this->assertDatabaseHas('notification_suppressions',['address'=>$d->address,'reason'=>'complained']);
        $this->postJson('/notifications/webhooks/resend',['type'=>'email.delivered'])->assertStatus(400);
    }
    public function test_svix_official_signature_vector_and_expired_signatures(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::createFromTimestampUTC(1731705121));
        $service=app(Events::class); $body='{"event_type":"ping","data":{"success":true}}'; $secret='whsec_plJ3nmyCDGBKInavdOK15jsl';
        $signature='v1,rAvfW3dJ/X/qxhsaXPOyyCGmRKsaKWcsNccKXlIktD0=';
        $this->assertTrue($service->verify($body,'msg_loFOjxBNrRLzqYUf','1731705121',$signature,$secret));
        $this->assertFalse($service->verify($body.' ','msg_loFOjxBNrRLzqYUf','1731705121',$signature,$secret));
        $this->travel(6)->minutes();
        $this->assertFalse($service->verify($body,'msg_loFOjxBNrRLzqYUf','1731705121',$signature,$secret));
    }
    public function test_webhook_arriving_before_send_response_is_reconciled(): void
    {
        $campaign=$this->queued(); $d=$this->bulk($campaign);
        $this->fakeProvider(['api.resend.com/emails'=>function() { $this->webhook('email.delivered','raced-message','early-event')->assertOk(); return Http::response(['id'=>'raced-message'],200); }]);
        $this->assertTrue(app(Outbox::class)->deliver($d->id));
        $this->assertSame('delivered',$d->fresh()->outcome);
        $this->assertDatabaseHas('notification_events',['event_key'=>'early-event','delivery_id'=>$d->id]);
    }
    public function test_tests_and_disabled_tracking_do_not_record_engagement(): void
    {
        $campaign=$this->queued($this->draft(['track_opens'=>false,'track_clicks'=>false]));
        $test=$campaign->deliveries()->where('is_test',true)->first(); $d=$this->bulk($campaign); app(Outbox::class)->deliver($d->id);
        foreach([$test,$d] as $entry) $this->get('/notifications/open/'.$entry->token.'.gif')->assertOk();
        $this->assertNull($test->fresh()->opened_at); $this->assertNull($d->fresh()->opened_at);
        $this->post('/notifications/unsubscribe/'.$test->token)->assertNotFound();
        $this->assertDatabaseCount('notification_suppressions',0);
    }
    public function test_reporting_csv_and_permission_boundaries(): void
    {
        $campaign=$this->queued(); $d=$this->bulk($campaign); $d->update(['recipient_name'=>'=HYPERLINK("bad")']);
        $this->get(route('admin.notifications.export',$campaign))->assertOk();
        $csv=$this->get(route('admin.notifications.export',$campaign))->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK",$csv);
        $this->get(route('admin.notifications.delivery',[$this->draft(),$d]))->assertNotFound();
        $role=Role::create(['name'=>'Notification viewer','permissions'=>StaffPermissions::normalize(['access.admin.notifications.index'])]);
        $viewer=User::factory()->create(['is_active'=>true,'role_id'=>$role->id]); $this->actingAs($viewer);
        $this->get(route('admin.notifications.show',$campaign))->assertOk();
        $this->get(route('admin.notifications.create'))->assertForbidden();
        $this->post(route('admin.notifications.queue',$campaign),[])->assertForbidden();
        $this->post(route('admin.notifications.test',$campaign),[])->assertForbidden();
        $this->get(route('admin.notifications.settings'))->assertForbidden();
        $role->update(['permissions'=>StaffPermissions::normalize(['access.admin.notifications.index','notifications.send'])]);
        $this->actingAs($viewer->fresh());
        $draft=$this->draft();
        $this->get(route('admin.notifications.show',$draft))->assertOk()->assertSee('Test recipient');
        $this->get(route('admin.notifications.edit',$draft))->assertForbidden();
        $this->post(route('admin.notifications.test',$draft),['recipient'=>'tester@example.test','test_key'=>(string)Str::uuid(),'revision'=>0])->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->create(['account_type'=>'individual','is_active'=>true]));
        $this->get(route('admin.notifications.index'))->assertRedirect(route('customer.orders'));
    }
    public function test_webhook_secret_is_encrypted_hidden_and_not_flashed(): void
    {
        $secret='whsec_'.base64_encode('secret-value');
        $this->put(route('admin.notifications.settings.update'),['webhook_secret'=>$secret,'per_run'=>20])->assertSessionHasNoErrors();
        $this->assertNotSame($secret,DB::table('notification_settings')->value('webhook_secret'));
        $this->assertArrayNotHasKey('webhook_secret',NotificationSetting::find(1)->toArray());
        $this->get(route('admin.notifications.settings'))->assertOk()->assertDontSee($secret);
        $this->put(route('admin.notifications.settings.update'),['webhook_secret'=>$secret,'per_run'=>0])->assertSessionHasErrors('per_run')->assertSessionMissing('_old_input.webhook_secret');
    }
    public function test_claim_lease_retry_expiry_and_worker_lock_prevent_duplicate_sends(): void
    {
        $campaign=$this->queued(); $d=$this->bulk($campaign);
        $d->update(['status'=>'sending','provider'=>'resend','started_at'=>now(),'first_attempt_at'=>now(),'attempts'=>1]);
        $this->fakeProvider();
        $this->assertFalse(app(Outbox::class)->deliver($d->id));
        $lock=Cache::lock('notifications:worker',120); $this->assertTrue($lock->get());
        try { $this->artisan('notifications:send')->expectsOutputToContain('Another notification worker')->assertSuccessful(); }
        finally { $lock->release(); }
        $d->update(['started_at'=>now()->subHours(13),'first_attempt_at'=>now()->subHours(13)]);
        $this->assertFalse(app(Outbox::class)->deliver($d->id));
        $this->assertSame('needs_review',$d->fresh()->status);
        Http::assertNothingSent();
    }
    public function test_pause_resume_and_cancellation_during_inflight_send(): void
    {
        $campaign=$this->queued($this->draft(['audience'=>['email:one@example.test','email:two@example.test']]));
        $d=$this->bulk($campaign);
        app(Campaigns::class)->transition($campaign,'pause');
        $this->assertFalse(app(Outbox::class)->deliver($d->id));
        app(Campaigns::class)->transition($campaign,'resume');
        $this->fakeProvider(['api.resend.com/emails'=>function() use($campaign) {
            app(Campaigns::class)->transition($campaign,'cancel');
            return Http::response(['id'=>'inflight-accepted'],200);
        }]);
        $this->assertTrue(app(Outbox::class)->deliver($d->id));
        $this->assertSame('accepted',$d->fresh()->status);
        $this->assertSame('cancelled',$campaign->fresh()->status);
        $this->assertSame(1,$campaign->deliveries()->where('is_test',false)->where('status','cancelled')->count());
        Http::assertSentCount(1);
    }
    public function test_sender_changes_invalidate_review_and_cross_campaign_links_are_rejected(): void
    {
        $campaign=$this->tested(); $review=app(Campaigns::class)->review($campaign,0,auth()->id(),null);
        SmtpSetting::find(1)->update(['revision'=>1,'tested_revision'=>1]);
        $this->post(route('admin.notifications.queue',$campaign),['review_token'=>$review['token'],'confirmed'=>1])->assertSessionHasErrors('notification');
        $one=$this->queued(); $two=$this->queued(); $d=$this->bulk($one); app(Outbox::class)->deliver($d->id);
        $this->get('/notifications/click/'.$d->token.'/'.$two->links()->first()->token)->assertNotFound();
    }
    public function test_smtp_channel_sends_html_and_text_once_and_does_not_retry_uncertain_failure(): void
    {
        SmtpSetting::find(1)->update(['delivery_method'=>'smtp','host'=>'smtp.example.test','port'=>587,'username'=>'test','password'=>'secret','encryption'=>'tls']);
        $messages=[];
        $mailer=\Mockery::mock(\Illuminate\Mail\Mailer::class);
        Mail::shouldReceive('purge')->with('configured_smtp');
        Mail::shouldReceive('mailer')->with('configured_smtp')->andReturn($mailer);
        $mailer->shouldReceive('html')->twice()->andReturnUsing(function($html,$callback) use(&$messages) {
            $email=new \Symfony\Component\Mime\Email;
            $email->html($html); $callback(new \Illuminate\Mail\Message($email)); $messages[]=$email;
            return new \Illuminate\Mail\SentMessage(new \Symfony\Component\Mailer\SentMessage($email,\Symfony\Component\Mailer\Envelope::create($email)));
        });
        $campaign=$this->queued(); $d=$this->bulk($campaign);
        $this->assertTrue(app(Outbox::class)->deliver($d->id));
        $this->assertCount(1,$messages[1]->getTo());
        $this->assertSame($d->address,$messages[1]->getTo()[0]->getAddress());
        $this->assertStringContainsString('Hello!',$messages[1]->getTextBody());
        $this->assertStringContainsString('/notifications/open/',$messages[1]->getHtmlBody());
        $this->assertTrue($messages[1]->getHeaders()->has('List-Unsubscribe'));
        $this->assertFalse(app(Outbox::class)->deliver($d->id));
        $mailer->shouldReceive('html')->once()->andThrow(new \RuntimeException('Uncertain SMTP response'));
        $test=app(Campaigns::class)->test($this->draft(),0,'test@example.test',(string)Str::uuid());
        $this->assertSame('needs_review',$test->status);
        $this->assertFalse(app(Outbox::class)->deliver($test->id));
        Http::assertNothingSent();
    }
}
