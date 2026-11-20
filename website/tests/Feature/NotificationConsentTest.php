<?php
namespace Tests\Feature;

use App\Models\{NotificationCampaign, NotificationConsentEvent, NotificationSubscription, NotificationSuppression, Role, SmtpSetting, User};
use App\Services\Notifications\{Audience, Campaigns, Consents, EmailChannel, Events, Outbox};
use App\Services\{OutgoingEmail, StaffPermissions};
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationConsentTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url'=>'https://mmc.example.test']);
        Storage::fake('local');
        $this->admin=User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);
        $this->actingAs($this->admin);
        SmtpSetting::forceCreate(['id'=>1,'enabled'=>true,'delivery_method'=>'resend','api_key'=>'re_fake','from_address'=>'sender@example.test','from_name'=>'Mamma Mia','revision'=>0,'tested_revision'=>0,'tested_at'=>now()]);
        $this->provider();
    }
    private function provider($callback=null): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory); Http::preventStrayRequests();
        Http::fake(['api.resend.com/emails'=>$callback ?? fn()=>Http::response(['id'=>(string)Str::uuid()],200)]);
    }
    private function draft(string $email='buyer@example.test'): NotificationCampaign
    {
        return app(Campaigns::class)->save(null,['title'=>'Product news','subject'=>'New arrivals','body'=>'Our product update.',
            'audience_mode'=>'selected','customer_type'=>'all','audience'=>['email:'.$email],'include_alternate'=>true,'track_opens'=>true,'track_clicks'=>true],$this->admin->id);
    }
    private function reviewed(?NotificationCampaign $c=null): array
    {
        $c??=$this->draft(); app(Campaigns::class)->test($c,0,'tester@example.test',(string)Str::uuid());
        return app(Campaigns::class)->review($c,0,$this->admin->id,null);
    }
    private function queued(string $email='buyer@example.test'): NotificationCampaign
    {
        $review=$this->reviewed($this->draft($email));
        return app(Campaigns::class)->queue($review['campaign'],$review['token'],$this->admin->id);
    }
    private function sent(string $email='buyer@example.test')
    {
        $c=$this->queued($email); $d=$c->deliveries()->where('is_test',false)->firstOrFail();
        $this->assertTrue(app(Outbox::class)->deliver($d->id)); return $d->fresh();
    }
    private function consentData(NotificationSubscription $s, array $replace=[]): array
    {
        return array_replace(['version'=>$s->version,'request_key'=>(string)Str::uuid(),'source'=>'email_request','consented_at'=>BusinessTime::now()->format('Y-m-d\TH:i:s'),
            'evidence'=>'Email from '.$s->address.': Please subscribe me again to Mamma Mia promotional updates.','confirmed'=>1],$replace);
    }
    private function consent(NotificationSubscription $s, array $replace=[])
    {
        return $this->post(route('admin.notifications.recipient.consent',$s),$this->consentData($s,$replace));
    }
    public function test_unsubscribe_is_durable_immediate_and_blocks_bulk_and_test_routes(): void
    {
        $sent=$this->sent(); $pending=$this->queued()->deliveries()->where('is_test',false)->firstOrFail();
        $this->provider();
        $url='/notifications/unsubscribe/'.$sent->token;
        $this->get($url)->assertOk(); $this->assertDatabaseCount('notification_consent_events',0);
        $this->post($url)->assertOk()->assertSee('You are unsubscribed');
        $this->post($url,['List-Unsubscribe'=>'One-Click'])->assertOk();
        $s=app(Consents::class)->subscription('BUYER@EXAMPLE.TEST');
        $this->assertSame('unsubscribed',$s->status); $this->assertSame(1,$s->version);
        $this->assertSame(1,$s->events()->count()); $this->assertSame('suppressed',$pending->fresh()->status);
        $this->travel(5)->years();
        $this->assertSame(0,app(Audience::class)->resolve($this->draft('BUYER@EXAMPLE.TEST'))['eligible']);
        $c=$this->draft('buyer@example.test');
        $this->post(route('admin.notifications.test',$c),['recipient'=>'buyer@example.test','revision'=>0,'test_key'=>(string)Str::uuid()])->assertSessionHasErrors('notification');
        $this->assertFalse(app(Outbox::class)->deliver($pending->id)); Http::assertNothingSent();
    }
    public function test_old_unsubscribe_links_work_without_login_after_campaign_archiving(): void
    {
        $d=$this->sent(); $d->campaign->delete(); $this->travel(100)->days();
        auth()->logout();
        $this->get('/notifications/unsubscribe/'.$d->token)->assertOk();
        $this->post('/notifications/unsubscribe/'.$d->token)->assertOk()->assertSee('You are unsubscribed');
        $this->assertSame('unsubscribed',app(Consents::class)->subscription($d->address)->status);
        $this->post('/notifications/unsubscribe/'.Str::random(64))->assertNotFound();
    }
    public function test_unsubscribe_confirmation_uses_the_configured_contact_for_consent_requests(): void
    {
        $d=$this->sent();
        \App\Models\GeneralSetting::updateOrCreate(['id'=>1],['email'=>'consent-office@example.test']);
        $this->post('/notifications/unsubscribe/'.$d->token)->assertOk()
            ->assertSee('mailto:consent-office@example.test',false)->assertSee('Our team will record your consent');
        $this->assertSame('unsubscribed',app(Consents::class)->subscription($d->address)->status);
    }
    public function test_admin_resubscribe_requires_evidence_and_preserves_a_private_audit_trail(): void
    {
        $s=app(Consents::class)->unsubscribe('buyer@example.test',actor:$this->admin,notes:'Recipient asked us by phone to stop.');
        $this->travel(1)->minutes(); $this->provider();
        $this->consent($s,['evidence'=>''])->assertSessionHasErrors('evidence');
        $this->consent($s,['confirmed'=>0])->assertSessionHasErrors('confirmed');
        $this->consent($s,['evidence'=>'   '])->assertSessionHasErrors('evidence');
        $file=UploadedFile::fake()->createWithContent('Recipient request.pdf',"%PDF-1.4\nConsent evidence\n%%EOF");
        $this->consent($s,['evidence_files'=>[$file]])->assertSessionHasNoErrors()->assertRedirect();
        $s->refresh(); $this->assertSame('subscribed',$s->status); $this->assertSame(2,$s->version);
        $event=$s->events()->where('action','resubscribed')->sole();
        $this->assertSame($this->admin->id,$event->actor_id); $this->assertSame($this->admin->name,$event->actor_name);
        $this->assertStringContainsString('Please subscribe me again',$event->evidence);
        $this->assertStringNotContainsString('Please subscribe me again',DB::table('notification_consent_events')->where('id',$event->id)->value('evidence'));
        $this->assertArrayNotHasKey('evidence',$event->toArray());
        $proof=$event->files[0]; Storage::disk('local')->assertExists($proof['path']);
        $this->get(route('admin.notifications.recipient.evidence',[$s,$event,$proof['id']]))->assertOk()->assertDownload('Recipient request.pdf');
        $this->get(route('admin.notifications.recipient',$s))->assertOk()->assertSee('Resubscribed with consent')->assertSee('Recipient request.pdf');
        Http::assertNothingSent();
    }
    public function test_old_or_future_consent_and_stale_forms_cannot_reactivate_an_address(): void
    {
        $this->travelTo(now()->startOfMinute()->addSeconds(10));
        $s=app(Consents::class)->unsubscribe('buyer@example.test'); $version=$s->version;
        $this->consent($s,['consented_at'=>BusinessTime::now()->subDay()->format('Y-m-d\TH:i:s')])->assertSessionHasErrors('consent');
        $this->consent($s,['consented_at'=>BusinessTime::now()->addDay()->format('Y-m-d\TH:i:s')])->assertSessionHasErrors('consent');
        $this->travel(1)->minutes(); $this->consent($s)->assertSessionHasNoErrors();
        $s=app(Consents::class)->unsubscribe($s->address); $this->travel(1)->minutes();
        $this->consent($s,['version'=>$version])->assertSessionHasErrors('consent');
        $this->assertSame('unsubscribed',$s->fresh()->status);
    }
    public function test_repeated_consent_submission_cannot_undo_a_newer_unsubscribe(): void
    {
        $s=app(Consents::class)->unsubscribe('buyer@example.test'); $this->travel(1)->minutes();
        $data=$this->consentData($s);
        $this->post(route('admin.notifications.recipient.consent',$s),$data)->assertSessionHasNoErrors();
        $this->post(route('admin.notifications.recipient.consent',$s),$data)->assertSessionHasNoErrors();
        $this->assertSame(2,$s->events()->count());
        $this->travel(1)->minutes(); app(Consents::class)->unsubscribe($s->address);
        $this->post(route('admin.notifications.recipient.consent',$s),$data)->assertSessionHasNoErrors()->assertSessionHas('status',fn($text)=>str_contains($text,'newer unsubscribe'));
        $this->assertSame('unsubscribed',$s->fresh()->status);
        $other=app(Consents::class)->subscription('other@example.test');
        $this->post(route('admin.notifications.recipient.consent',$other),$data)->assertSessionHasErrors('consent');
    }
    public function test_resubscribe_allows_new_campaigns_but_never_restarts_old_queued_emails(): void
    {
        $old=$this->queued(); $d=$old->deliveries()->where('is_test',false)->firstOrFail();
        $s=app(Consents::class)->unsubscribe($d->address); $this->travel(1)->minutes(); $this->consent($s)->assertSessionHasNoErrors();
        $this->assertSame('suppressed',$d->fresh()->status);
        // Even a delayed/stale worker claim must not resurrect the old consent version.
        $d->refresh()->update(['status'=>'queued']); $this->provider();
        $this->assertFalse(app(Outbox::class)->deliver($d->id)); Http::assertNothingSent();
        $this->assertStringContainsString('Consent changed',$d->fresh()->note);
        $new=$this->queued(); $fresh=$new->deliveries()->where('is_test',false)->firstOrFail();
        $this->assertSame($s->fresh()->version,(int)$fresh->consent_version);
        $this->assertTrue(app(Outbox::class)->deliver($fresh->id));
    }
    public function test_consent_changes_invalidate_an_existing_review(): void
    {
        $r=$this->reviewed(); $s=app(Consents::class)->unsubscribe('buyer@example.test');
        $this->post(route('admin.notifications.queue',$r['campaign']),['review_token'=>$r['token'],'confirmed'=>1])->assertSessionHasErrors('notification');
        $this->travel(1)->minutes(); $this->consent($s)->assertSessionHasNoErrors();
        $this->post(route('admin.notifications.queue',$r['campaign']),['review_token'=>$r['token'],'confirmed'=>1])->assertSessionHasErrors('notification');
        $this->assertSame(0,$r['campaign']->deliveries()->where('is_test',false)->count());
    }
    public function test_final_channel_check_catches_an_unsubscribe_after_the_worker_claim(): void
    {
        $c=$this->queued(); $d=$c->deliveries()->where('is_test',false)->firstOrFail(); $this->provider();
        $channel=\Mockery::mock(EmailChannel::class);
        $channel->shouldReceive('send')->once()->andReturnUsing(function($delivery,$settings) {
            app(Consents::class)->unsubscribe($delivery->address);
            return (new EmailChannel)->send($delivery,$settings);
        });
        $this->app->instance(EmailChannel::class,$channel);
        $this->assertFalse(app(Outbox::class)->deliver($d->id));
        $this->assertSame('suppressed',$d->fresh()->status); Http::assertNothingSent();
    }
    public function test_provider_blocks_survive_unsubscribe_and_renewed_consent(): void
    {
        $d=$this->sent(); $s=app(Consents::class)->unsubscribe($d->address);
        app(Events::class)->ingest(['type'=>'email.bounced','created_at'=>now()->toIso8601String(),'data'=>['email_id'=>$d->provider_message_id]],'bounced-after-unsubscribe');
        $this->assertSame('bounced',NotificationSuppression::where('address',$d->address)->sole()->reason);
        $this->travel(1)->minutes(); $this->consent($s)->assertSessionHasNoErrors();
        $this->assertSame('subscribed',$s->fresh()->status);
        $this->assertSame('bounced',NotificationSuppression::where('address',$d->address)->sole()->reason);
        $this->assertSame(0,app(Audience::class)->resolve($this->draft())['eligible']);
        $this->get(route('admin.notifications.recipient',$s))->assertOk()->assertSee('Provider block:');
    }
    public function test_global_and_campaign_reports_show_unsubscribers_and_the_source_email(): void
    {
        $d=$this->sent(); $this->post('/notifications/unsubscribe/'.$d->token)->assertOk();
        $s=app(Consents::class)->subscription($d->address);
        $this->get(route('admin.notifications.recipients',['status'=>'unsubscribed','q'=>'buyer']))->assertOk()->assertSee($d->address)->assertSee('Via '.$d->campaign->number);
        $this->get(route('admin.notifications.show',[$d->campaign,'preference'=>'unsubscribed']))->assertOk()->assertSee('Unsubscribed via this email')->assertSee('Unsubscribed via this campaign');
        $this->get(route('admin.notifications.recipient',$s))->assertOk()->assertSee('Recipient used email unsubscribe link')->assertSee($d->campaign->subject);
        $this->get(route('admin.notifications.recipients.export',['status'=>'unsubscribed']))->assertOk()->assertDownload('notification-recipients.csv')->assertStreamedContent($this->exportContent($s));
        $this->get(route('admin.notifications.recipients',['status'=>'subscribed']))->assertOk()->assertDontSee($d->address);
    }
    private function exportContent(NotificationSubscription $s): string
    {
        $out=fopen('php://temp','w+');fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['Email','Subscription status','Accepted campaign emails','Last accepted (Toronto)','Last unsubscribe (Toronto)','Consent received (Toronto)'],',','"','');
        $at=$s->deliveries()->where('is_test',false)->max('accepted_at');
        fputcsv($out,[$s->address,'Unsubscribed',1,BusinessTime::format($at),BusinessTime::format($s->unsubscribed_at),''],',','"','');
        rewind($out);return stream_get_contents($out);
    }
    public function test_permission_boundary_protects_consent_changes_and_evidence(): void
    {
        $s=app(Consents::class)->subscription('buyer@example.test');
        $this->consent($s,['evidence_files'=>[UploadedFile::fake()->image('Private evidence.png')]])->assertSessionHasNoErrors();
        $event=$s->events()->first();$file=$event->files[0];$url=route('admin.notifications.recipient.evidence',[$s,$event,$file['id']]);
        $other=app(Consents::class)->subscription('other@example.test');
        $this->get(route('admin.notifications.recipient.evidence',[$other,$event,$file['id']]))->assertNotFound();
        $role=Role::create(['name'=>'Notification viewer','permissions'=>StaffPermissions::normalize(['access.admin.notifications.index','notifications.manage','notifications.send'])]);
        $staff=User::factory()->create(['is_active'=>true,'role_id'=>$role->id]); $this->actingAs($staff);
        $this->get(route('admin.notifications.recipients'))->assertOk();
        $this->get(route('admin.notifications.recipient',$s))->assertOk()->assertDontSee('Please subscribe me again')->assertDontSee('Private evidence.png')->assertDontSee('Save consent evidence');
        $this->get($url)->assertForbidden(); $this->consent($s->fresh())->assertForbidden();
        $this->post(route('admin.notifications.recipient.unsubscribe',$s),['version'=>$s->fresh()->version,'notes'=>'Please stop sending marketing.','confirmed'=>1])->assertForbidden();
        $role->update(['permissions'=>StaffPermissions::normalize(['access.admin.notifications.index','notifications.consent'])]); $this->actingAs($staff->fresh());
        $this->get($url)->assertOk();
        $this->post(route('admin.notifications.recipient.unsubscribe',$s),['version'=>$s->fresh()->version,'notes'=>'Recipient called and asked to stop.','confirmed'=>1])->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->create(['account_type'=>'individual','is_active'=>true]));
        $this->get(route('admin.notifications.recipient',$s))->assertRedirect(route('customer.orders'));
    }
    public function test_receipts_and_customer_password_reset_continue_after_unsubscribe(): void
    {
        $user=User::factory()->create(['email'=>'buyer@example.test','account_type'=>'individual','is_active'=>true,'email_verified_at'=>now()]);
        app(Consents::class)->unsubscribe($user->email);
        app(OutgoingEmail::class)->raw('Your payment was received.',fn($m)=>$m->to($user->email)->subject('Payment receipt'),'payment_received',$user);
        auth()->logout(); $this->post(route('customer.password.send'),['email'=>$user->email])->assertSessionHas('status');
        $this->assertDatabaseHas('email_history',['recipient'=>$user->email,'type'=>'payment_received','status'=>'accepted']);
        $this->assertDatabaseHas('email_history',['recipient'=>$user->email,'type'=>'password_reset','status'=>'accepted']);
        Http::assertSentCount(2);
        $this->assertSame('unsubscribed',app(Consents::class)->subscription($user->email)->status);
    }
    public function test_reentering_an_email_or_creating_a_customer_does_not_reset_the_unsubscribe(): void
    {
        $s=app(Consents::class)->unsubscribe('buyer@example.test');
        $this->post(route('admin.notifications.recipients.create'),['address'=>'BUYER@EXAMPLE.TEST'])->assertRedirect(route('admin.notifications.recipient',$s));
        User::factory()->create(['email'=>'buyer@example.test','account_type'=>'business','is_active'=>true]);
        $this->assertSame('unsubscribed',$s->fresh()->status); $this->assertSame(1,NotificationSubscription::where('address','buyer@example.test')->count());
        $this->assertSame(0,app(Audience::class)->resolve($this->draft())['eligible']); Http::assertNothingSent();
    }
    public function test_invalid_evidence_upload_and_stale_state_do_not_leave_files_or_consent_changes(): void
    {
        $s=app(Consents::class)->unsubscribe('buyer@example.test'); $this->travel(1)->minutes();
        $this->consent($s,['evidence_files'=>[UploadedFile::fake()->create('program.exe',1,'application/x-msdownload')]])->assertSessionHasErrors('attachments.0');
        $this->consent($s,['version'=>0,'evidence_files'=>[UploadedFile::fake()->image('proof.png')]])->assertSessionHasErrors('consent');
        $this->assertSame([],Storage::disk('local')->allFiles()); $this->assertSame('unsubscribed',$s->fresh()->status);
    }
}
