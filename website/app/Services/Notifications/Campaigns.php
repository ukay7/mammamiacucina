<?php
namespace App\Services\Notifications;

use App\Models\{NotificationCampaign, NotificationDelivery, NotificationSubscription, NotificationSuppression, SmtpSetting};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Campaigns
{
    public static function fail(string $message): never { throw ValidationException::withMessages(['notification'=>$message]); }
    public function settings(): SmtpSetting
    {
        $settings = SmtpSetting::find(1);
        if (!$settings?->ready()) self::fail('Enable and successfully test the current sender settings in Email Configuration first.');
        return $settings;
    }
    public function save(?NotificationCampaign $campaign, array $data, int $user, array $uploads = [], array $remove = [], ?array $copiedFiles = null): NotificationCampaign
    {
        $created = [];
        try { return DB::transaction(function() use ($campaign,$data,$user,$uploads,$remove,$copiedFiles,&$created) {
            if ($campaign) {
                $campaign = NotificationCampaign::lockForUpdate()->findOrFail($campaign->id);
                if ($campaign->status!=='draft' || $campaign->revision!==(int)$data['revision']) self::fail('This draft changed or has already been queued. Reload before editing.');
            } else $campaign = new NotificationCampaign(['created_by'=>$user, 'revision'=>-1]);
            $campaign->attachments = app(Attachments::class)->update($campaign->attachments ?? $copiedFiles ?? [], $uploads, $remove, $created);
            $campaign->fill(collect($data)->only(['title','subject','body','audience_mode','customer_type','audience','include_alternate','track_opens','track_clicks'])->all());
            $campaign->forceFill(['channel'=>'email', 'revision'=>$campaign->revision+1, 'tested_revision'=>null, 'tested_at'=>null, 'review'=>null])->save();
            return $campaign;
        }); } catch (\Throwable $e) { app(Attachments::class)->discardUncommitted($created); throw $e; }
    }
    public function duplicate(NotificationCampaign $source, int $user): NotificationCampaign
    {
        return DB::transaction(function() use ($source,$user) {
            $source = NotificationCampaign::lockForUpdate()->findOrFail($source->id);
            $files = $source->attachments ?? [];
            app(Attachments::class)->assertReady($files);
            $data = $source->only(['title','subject','body','audience_mode','customer_type','audience','include_alternate','track_opens','track_clicks']);
            $data['title'] = mb_substr($data['title'],0,140).' (copy)';
            return $this->save(null,$data,$user,copiedFiles:$files);
        });
    }
    public function test(NotificationCampaign $campaign, int $revision, string $address, string $key): NotificationDelivery
    {
        $settings = $this->settings();
        $delivery = DB::transaction(function() use ($campaign,$revision,$address,$key,$settings) {
            $campaign = NotificationCampaign::lockForUpdate()->findOrFail($campaign->id);
            if ($campaign->status!=='draft' || $campaign->revision!==$revision) self::fail('Reload the current draft before testing.');
            app(Attachments::class)->assertReady($campaign->attachments ?? []);
            $subscription=app(Consents::class)->subscription($address,true);
            if ($reason=app(Consents::class)->reason($subscription)) self::fail($reason.' Test emails cannot bypass this block.');
            $existing = NotificationDelivery::where('idempotency_key',$key)->first();
            if ($existing) {
                if ($existing->campaign_id!==$campaign->id || !$existing->is_test || $existing->campaign_revision!==$revision || $existing->address!==$address) self::fail('This test request is no longer current. Reload the page.');
                return $existing;
            }
            return $campaign->deliveries()->create(['address'=>$address, 'channel'=>'email', 'source'=>'test', 'is_test'=>true, 'campaign_revision'=>$revision,
                'settings_revision'=>$settings->revision, 'consent_version'=>$subscription->version, 'token'=>Str::random(64), 'idempotency_key'=>$key, 'available_at'=>now(),
                'test_snapshot'=>app(Message::class)->snapshot($campaign,$settings)]);
        });
        app(Outbox::class)->deliver($delivery->id);
        return $delivery->fresh();
    }
    public function review(NotificationCampaign $campaign, int $revision, int $user, $scheduledAt): array
    {
        $settings = $this->settings();
        return DB::transaction(function() use($campaign,$revision,$user,$scheduledAt,$settings) {
            $campaign = NotificationCampaign::lockForUpdate()->findOrFail($campaign->id);
            $this->assertTested($campaign,$revision,$settings);
            app(Attachments::class)->assertReady($campaign->attachments ?? []);
            $audience = app(Audience::class)->resolve($campaign);
            if (!$audience['eligible']) self::fail('There are no eligible recipients. Select customers or add valid email addresses.');
            $token = Str::random(64);
            $review = $audience + ['token_hash'=>hash('sha256',$token), 'user'=>$user, 'revision'=>$revision, 'expires'=>now()->addMinutes(15)->timestamp,
                'settings_revision'=>$settings->revision, 'scheduled_at'=>$scheduledAt?->toIso8601String(), 'attachments'=>$campaign->attachments ?? []];
            $campaign->update(['review'=>$review]);
            return ['review'=>$review,'token'=>$token,'campaign'=>$campaign];
        });
    }
    private function assertTested(NotificationCampaign $campaign, int $revision, SmtpSetting $settings): void
    {
        if ($campaign->status!=='draft' || $campaign->revision!==$revision) self::fail('This draft changed. Reload and review it again.');
        if ($campaign->tested_revision!==$revision || $campaign->tested_settings_revision!==$settings->revision) self::fail('Send a successful test of this draft using the current email settings before queuing it.');
    }
    public function queue(NotificationCampaign $campaign, string $token, int $user): NotificationCampaign
    {
        $settings = $this->settings();
        return DB::transaction(function() use ($campaign,$token,$user,$settings) {
            $campaign = NotificationCampaign::lockForUpdate()->findOrFail($campaign->id);
            if ($campaign->status!=='draft' && hash_equals($campaign->snapshot['queue_token_hash']??'',hash('sha256',$token)) && ($campaign->snapshot['queued_by']??null)===$user) return $campaign;
            $review = $campaign->review;
            if (!$review || !hash_equals($review['token_hash'],hash('sha256',$token)) || $review['user']!==$user || $review['expires']<now()->timestamp || $review['settings_revision']!==$settings->revision) self::fail('The review expired or settings changed. Review the draft again.');
            $this->assertTested($campaign,$review['revision'],$settings);
            if (($review['attachments'] ?? []) !== ($campaign->attachments ?? [])) self::fail('Attachments changed. Test and review this draft again.');
            app(Attachments::class)->assertReady($campaign->attachments ?? []);
            // Recheck the reviewed consent state before freezing recipients into a campaign.
            foreach (array_chunk($review['rows'],500) as $chunk) {
                $addresses=array_column($chunk,'address');
                $subscriptions=NotificationSubscription::where('channel','email')->whereIn('address',$addresses)->orderBy('id')->lockForUpdate()->get()->keyBy('address');
                $blocks=NotificationSuppression::where('channel','email')->whereIn('address',$addresses)->pluck('reason','address');
                foreach ($chunk as $row) {
                    $s=$subscriptions->get($row['address']);
                    $block=$s?->status==='unsubscribed' ? 'unsubscribed' : $blocks->get($row['address']);
                    if (!$s || ($row['consent_version']??null)!==$s->version || $row['suppression']!==$block) self::fail('A recipient’s email preferences changed. Review recipients again before queuing.');
                }
            }
            $snapshot = app(Message::class)->snapshot($campaign,$settings) + ['queue_token_hash'=>hash('sha256',$token),'queued_by'=>$user];
            foreach (app(Message::class)->urls($campaign->body) as $url) $campaign->links()->create(['token'=>Str::random(40),'url_hash'=>hash('sha256',$url),'url'=>$url]);
            $now = now();
            $availableAt = $review['scheduled_at'] ? \Illuminate\Support\Carbon::parse($review['scheduled_at'])->utc() : $now;
            foreach (array_chunk($review['rows'],250) as $chunk) {
                // Insert bounded batches so large audiences do not require one database round trip per address.
                // These fields contain no encrypted casts; encrypted content remains on the campaign snapshot.
                $rows = array_map(fn($row)=>collect($row)->except(['suppression','consent_status'])->all() + ['campaign_id'=>$campaign->id,'channel'=>'email','campaign_revision'=>$campaign->revision,
                    'settings_revision'=>$settings->revision,'dedupe_key'=>hash('sha256',$row['address']), 'token'=>Str::random(64),'idempotency_key'=>(string)Str::uuid(),
                    'status'=>$row['suppression']?'suppressed':'queued', 'note'=>$row['suppression']?'Excluded: '.$row['suppression']:null,
                    'available_at'=>$availableAt,'created_at'=>$now,'updated_at'=>$now],$chunk);
                DB::table('notification_deliveries')->insert($rows);
            }
            $campaign->update(['status'=>'queued','queued_by'=>$user,'queued_at'=>now(),'scheduled_at'=>$review['scheduled_at'],'snapshot'=>$snapshot,'review'=>null]);
            return $campaign;
        });
    }
    public function transition(NotificationCampaign $campaign, string $action): void
    {
        DB::transaction(function() use ($campaign,$action) {
            $campaign = NotificationCampaign::lockForUpdate()->findOrFail($campaign->id);
            $allowed = ['pause'=>['queued'],'resume'=>['paused'],'cancel'=>['queued','paused']];
            if (!in_array($campaign->status,$allowed[$action]??[])) self::fail('The campaign status changed. Reload before continuing.');
            $campaign->update(['status'=>['pause'=>'paused','resume'=>'queued','cancel'=>'cancelled'][$action]]);
            if ($action==='cancel') $campaign->deliveries()->where('is_test',false)->whereIn('status',['queued','retry'])->update(['status'=>'cancelled','note'=>'Cancelled before sending.']);
        });
    }
    public function finish(int $campaignId): void
    {
        DB::transaction(function() use ($campaignId) {
            $campaign = NotificationCampaign::lockForUpdate()->find($campaignId);
            if ($campaign?->status==='queued' && !$campaign->deliveries()->where('is_test',false)->whereIn('status',['queued','retry','sending'])->exists()) $campaign->update(['status'=>'completed','completed_at'=>now()]);
        });
    }
}
