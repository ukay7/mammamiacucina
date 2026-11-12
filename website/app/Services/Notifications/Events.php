<?php
namespace App\Services\Notifications;

use App\Models\{NotificationDelivery, NotificationEvent, NotificationSetting, NotificationSuppression};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Events
{
    public const TYPES = ['email.sent','email.delivered','email.delivery_delayed','email.bounced','email.complained','email.failed','email.suppressed'];

    public function verify(string $body, string $id, string $timestamp, string $signatures, string $secret): bool
    {
        if (!preg_match('/^\d{1,12}$/D',$timestamp) || abs(now()->timestamp-(int)$timestamp)>300 || !$id || strlen($id)>180 || strlen($signatures)>2048 || !str_starts_with($secret,'whsec_')) return false;
        $key = base64_decode(substr($secret,6),true);
        if (!$key) return false;
        $expected = base64_encode(hash_hmac('sha256',$id.'.'.$timestamp.'.'.$body,$key,true));
        foreach (explode(' ',$signatures) as $signature) if (str_starts_with($signature,'v1,') && hash_equals($expected,substr($signature,3))) return true;
        return false;
    }
    public function ingest(array $data, string $id): void
    {
        if (!in_array($data['type']??'',self::TYPES,true)) return;
        $messageId = $data['data']['email_id']??null;
        if (!is_string($messageId) || !$messageId || strlen($messageId)>255) return;
        try { $at = Carbon::parse($data['created_at']??'now')->utc(); } catch (\Throwable) { return; }
        NotificationEvent::firstOrCreate(['event_key'=>$id],['provider_message_id'=>$messageId,'type'=>$data['type'],'source'=>'resend','occurred_at'=>$at]);
        NotificationSetting::firstOrCreate(['id'=>1])->update(['webhook_received_at'=>now()]);
        $delivery = NotificationDelivery::where('provider','resend')->where('provider_message_id',$messageId)->first();
        if ($delivery) $this->pending($delivery->id);
    }
    public function pending(int $deliveryId): void
    {
        $delivery = NotificationDelivery::findOrFail($deliveryId);
        if (!$delivery->provider_message_id || $delivery->provider!=='resend') return;
        $ids = NotificationEvent::where('provider_message_id',$delivery->provider_message_id)->whereNull('processed_at')->orderBy('id')->pluck('id');
        foreach ($ids as $id) DB::transaction(function() use ($deliveryId,$id) {
            $d = NotificationDelivery::lockForUpdate()->findOrFail($deliveryId);
            $event = NotificationEvent::lockForUpdate()->findOrFail($id);
            if ($event->processed_at) return;
            $type = substr($event->type,6);
            $values = [];
            $rank = ['delayed'=>1,'delivered'=>2,'failed'=>3,'suppressed'=>4,'bounced'=>5,'complained'=>6];
            $outcome = $type==='delivery_delayed'?'delayed':$type;
            if (isset($rank[$outcome]) && $rank[$outcome]>($rank[$d->outcome]??0)) $values['outcome']=$outcome;
            if ($type==='delivered' && (!$d->delivered_at || $event->occurred_at->lt($d->delivered_at))) $values['delivered_at']=$event->occurred_at;
            if (in_array($type,['sent','delivered']) && !$d->accepted_at) $values['accepted_at']=$event->occurred_at;
            if ($values) $d->update($values);
            if (!$d->is_test && in_array($type,['bounced','complained','suppressed'])) NotificationSuppression::firstOrCreate(['channel'=>'email','address'=>$d->address],['reason'=>$type]);
            $event->update(['delivery_id'=>$d->id,'processed_at'=>now(),'description'=>'Provider reported: '.str_replace('_',' ',$type).'.']);
        });
    }
    public function engage(NotificationDelivery $delivery, string $kind): void
    {
        DB::transaction(function() use ($delivery,$kind) {
            $d = NotificationDelivery::lockForUpdate()->findOrFail($delivery->id);
            if ($d->is_test || !$d->attempts || !($d->campaign->snapshot[$kind==='open'?'track_opens':'track_clicks']??false)) return;
            $field = $kind==='open'?'opened_at':'clicked_at';
            $count = $kind==='open'?'open_count':'click_count';
            $values = [$field=>$d->$field??now(),$count=>$d->$count+1];
            if ($kind==='open') $values['last_opened_at']=now();
            if (!$d->$field) NotificationEvent::firstOrCreate(['event_key'=>'local:'.$d->id.':'.$kind],['delivery_id'=>$d->id,'type'=>$kind,'source'=>'tracking','description'=>$kind==='open'?'Tracking image requested (estimated open).':'Tracked link requested (may include automated scanners).','occurred_at'=>now(),'processed_at'=>now()]);
            $d->update($values);
        });
    }
}
