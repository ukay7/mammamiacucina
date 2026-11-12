<?php
namespace App\Services\Notifications;

use App\Models\{NotificationCampaign, NotificationDelivery, NotificationEvent, NotificationSuppression, SmtpSetting};
use Illuminate\Support\Facades\DB;

class Outbox
{
    public function deliver(int $id): bool
    {
        $settings = SmtpSetting::find(1);
        if (!$settings?->ready()) return false;
        $candidate = NotificationDelivery::find($id);
        if (!$candidate) return false;
        // Always lock campaign before delivery, including pause/cancel and completion.
        $entry = DB::transaction(function() use ($candidate,$settings) {
            $campaign = NotificationCampaign::withTrashed()->lockForUpdate()->find($candidate->campaign_id);
            $entry = NotificationDelivery::lockForUpdate()->find($candidate->id);
            if (!$entry || !in_array($entry->status,['queued','retry','sending']) || $entry->available_at?->isFuture()) return null;
            if ($entry->status==='sending' && $entry->started_at?->gt(now()->subMinutes(5))) return null;
            if (!$entry->is_test && ($campaign->trashed() || $campaign->status!=='queued')) {
                if ($entry->status==='sending') $this->review($entry,'The campaign stopped during a send. Check the provider before sending again.');
                return null;
            }
            if ($entry->is_test && ($campaign->trashed() || $campaign->status!=='draft' || $campaign->revision!==$entry->campaign_revision)) {
                $entry->update(['status'=>$entry->attempts?'needs_review':'cancelled','note'=>'Draft changed after this test was requested.']);
                return null;
            }
            if (!$entry->is_test && NotificationSuppression::where('channel',$entry->channel)->where('address',$entry->address)->exists()) {
                if ($entry->attempts) $this->review($entry,'Recipient opted out after a delivery attempt. No automatic retry.');
                else $entry->update(['status'=>'suppressed','note'=>'Recipient is unsubscribed or suppressed.']);
                return null;
            }
            $snapshot = $entry->is_test ? $entry->test_snapshot : $campaign->snapshot;
            if ($entry->attempts && ($entry->provider!=='resend' || $entry->attempts>=5 || $entry->first_attempt_at?->lt(now()->subHours(12)))) {
                $this->review($entry,'Delivery outcome needs review. Automatic retry is no longer safe.'); return null;
            }
            $credentialHash = hash('sha256', $settings->delivery_method.'|'.($settings->delivery_method==='resend'?$settings->api_key:$settings->password));
            if ($settings->revision!==$entry->settings_revision || ($entry->attempts && !hash_equals($entry->credential_hash??'',$credentialHash))) {
                $this->review($entry,'Email settings changed. Review the provider log and prepare a newly tested campaign.'); return null;
            }
            $payload = $entry->payload ?? app(Message::class)->render($snapshot,$entry);
            $entry->update(['payload'=>$payload,'provider'=>$settings->delivery_method,'credential_hash'=>$credentialHash,'status'=>'sending',
                'attempts'=>$entry->attempts+1,'first_attempt_at'=>$entry->first_attempt_at??now(),'started_at'=>now(),'note'=>'Sending to provider.']);
            $this->event($entry,'attempt','Delivery attempt '.$entry->attempts.'.');
            return $entry;
        });
        if (!$entry) { app(Campaigns::class)->finish($candidate->campaign_id); return false; }
        try {
            $channel = config('notifications.channels.'.$entry->channel);
            if (!$channel || !(app($channel) instanceof Channel)) throw new \RuntimeException('Unknown delivery channel.');
            $messageId = app($channel)->send($entry,$settings);
            DB::transaction(function() use ($entry,$messageId) {
                $campaign = NotificationCampaign::withTrashed()->lockForUpdate()->find($entry->campaign_id);
                $entry = NotificationDelivery::lockForUpdate()->findOrFail($entry->id);
                $entry->update(['status'=>'accepted','provider_message_id'=>$messageId,'accepted_at'=>$entry->accepted_at??now(),'payload'=>null,'note'=>'Provider accepted the message; inbox placement is not guaranteed.']);
                $this->event($entry,'accepted','Provider accepted the message.');
                if ($entry->is_test && $campaign->status==='draft' && !$campaign->trashed() && $campaign->revision===$entry->campaign_revision) {
                    $campaign->update(['tested_revision'=>$entry->campaign_revision,'tested_settings_revision'=>$entry->settings_revision,'tested_at'=>now()]);
                }
            });
            app(Events::class)->pending($entry->id);
            return true;
        } catch (\Throwable $e) {
            DB::transaction(function() use ($entry) {
                $entry = NotificationDelivery::lockForUpdate()->findOrFail($entry->id);
                if ($entry->accepted_at) return;
                $retry = $entry->provider==='resend' && $entry->attempts<5 && $entry->first_attempt_at->gt(now()->subHours(12));
                $entry->update(['status'=>$retry?'retry':'needs_review','available_at'=>now()->addMinutes(2 ** $entry->attempts),
                    'note'=>$retry?'Provider request failed; retry scheduled with the same message and idempotency key.':'Outcome uncertain. Check provider logs before sending again.']);
                $this->event($entry,$retry?'retry':'needs_review',$entry->note);
            });
            return false;
        } finally { app(Campaigns::class)->finish($entry->campaign_id); }
    }
    private function review(NotificationDelivery $entry, string $note): void
    {
        $entry->update(['status'=>'needs_review','note'=>$note]);
        $this->event($entry,'needs_review',$note);
    }
    private function event(NotificationDelivery $entry, string $type, string $description): void
    {
        NotificationEvent::create(['delivery_id'=>$entry->id,'type'=>$type,'source'=>'worker','description'=>$description,'occurred_at'=>now(),'processed_at'=>now()]);
    }
}
