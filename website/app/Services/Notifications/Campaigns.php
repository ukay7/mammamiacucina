<?php
namespace App\Services\Notifications;

use App\Models\{NotificationCampaign, NotificationDelivery, SmtpSetting};
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
    public function save(?NotificationCampaign $campaign, array $data, int $user): NotificationCampaign
    {
        return DB::transaction(function() use ($campaign,$data,$user) {
            if ($campaign) {
                $campaign = NotificationCampaign::lockForUpdate()->findOrFail($campaign->id);
                if ($campaign->status!=='draft' || $campaign->revision!==(int)$data['revision']) self::fail('This draft changed or has already been queued. Reload before editing.');
            } else $campaign = new NotificationCampaign(['created_by'=>$user, 'revision'=>-1]);
            $campaign->fill(collect($data)->only(['title','subject','body','audience_mode','customer_type','audience','include_alternate','track_opens','track_clicks'])->all());
            $campaign->forceFill(['channel'=>'email', 'revision'=>$campaign->revision+1, 'tested_revision'=>null, 'tested_at'=>null, 'review'=>null])->save();
            return $campaign;
        });
    }
    public function test(NotificationCampaign $campaign, int $revision, string $address, string $key): NotificationDelivery
    {
        $settings = $this->settings();
        $delivery = DB::transaction(function() use ($campaign,$revision,$address,$key,$settings) {
            $campaign = NotificationCampaign::lockForUpdate()->findOrFail($campaign->id);
            if ($campaign->status!=='draft' || $campaign->revision!==$revision) self::fail('Reload the current draft before testing.');
            $existing = NotificationDelivery::where('idempotency_key',$key)->first();
            if ($existing) {
                if ($existing->campaign_id!==$campaign->id || !$existing->is_test || $existing->campaign_revision!==$revision || $existing->address!==$address) self::fail('This test request is no longer current. Reload the page.');
                return $existing;
            }
            return $campaign->deliveries()->create(['address'=>$address, 'channel'=>'email', 'source'=>'test', 'is_test'=>true, 'campaign_revision'=>$revision,
                'settings_revision'=>$settings->revision, 'token'=>Str::random(64), 'idempotency_key'=>$key, 'available_at'=>now(),
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
            $audience = app(Audience::class)->resolve($campaign);
            if (!$audience['eligible']) self::fail('There are no eligible recipients. Select customers or add valid email addresses.');
            $token = Str::random(64);
            $review = $audience + ['token_hash'=>hash('sha256',$token), 'user'=>$user, 'revision'=>$revision, 'expires'=>now()->addMinutes(15)->timestamp,
                'settings_revision'=>$settings->revision, 'scheduled_at'=>$scheduledAt?->toIso8601String()];
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
            $snapshot = app(Message::class)->snapshot($campaign,$settings) + ['queue_token_hash'=>hash('sha256',$token),'queued_by'=>$user];
            foreach (app(Message::class)->urls($campaign->body) as $url) $campaign->links()->create(['token'=>Str::random(40),'url_hash'=>hash('sha256',$url),'url'=>$url]);
            $now = now();
            $availableAt = $review['scheduled_at'] ? \Illuminate\Support\Carbon::parse($review['scheduled_at'])->utc() : $now;
            foreach (array_chunk($review['rows'],250) as $chunk) {
                // Insert bounded batches so large audiences do not require one database round trip per address.
                // These fields contain no encrypted casts; encrypted content remains on the campaign snapshot.
                $rows = array_map(fn($row)=>collect($row)->except('suppression')->all() + ['campaign_id'=>$campaign->id,'channel'=>'email','campaign_revision'=>$campaign->revision,
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
