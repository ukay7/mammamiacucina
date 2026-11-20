<?php
namespace App\Services\Notifications;

use App\Models\{NotificationConsentEvent, NotificationDelivery, NotificationEvent, NotificationSubscription, NotificationSuppression, User};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;

class Consents
{
    public const SOURCES=['email_request'=>'Email request','website_signup'=>'Website signup','signed_form'=>'Signed form','phone_request'=>'Phone request','other'=>'Other explicit request'];
    public static function address(string $address): string { return strtolower(trim($address)); }

    public function register(array $addresses): void
    {
        $now=now();
        foreach (array_chunk(array_unique(array_map(self::address(...),$addresses)),500) as $chunk) {
            DB::table('notification_subscriptions')->insertOrIgnore(array_map(fn($address)=>[
                'channel'=>'email','address'=>$address,'status'=>'unknown','version'=>0,'created_at'=>$now,'updated_at'=>$now,
            ],$chunk));
        }
    }
    public function subscription(string $address, bool $lock=false): NotificationSubscription
    {
        $address=self::address($address);
        $this->register([$address]);
        return NotificationSubscription::where('channel','email')->where('address',$address)->when($lock,fn($q)=>$q->lockForUpdate())->firstOrFail();
    }
    public function reason(NotificationSubscription $subscription, ?int $version=null): ?string
    {
        if ($subscription->status==='unsubscribed') return 'Unsubscribed from promotional email.';
        if (NotificationSuppression::where('channel','email')->where('address',$subscription->address)->exists()) return 'Address is unsubscribed or blocked by the email provider.';
        if ($version!==null && $version!==$subscription->version) return 'Consent changed after this email was prepared. Prepare a new campaign; old sends are not restarted.';
        return null;
    }
    public function assertCanDeliver(NotificationDelivery $delivery): void
    {
        if ($reason=$this->reason($this->subscription($delivery->address),$delivery->consent_version ?? 0)) throw new ConsentBlocked($reason);
    }
    public function unsubscribe(string $address, ?NotificationDelivery $delivery=null, ?User $actor=null, ?string $notes=null, ?int $version=null): NotificationSubscription
    {
        $subscription=DB::transaction(function() use($address,$delivery,$actor,$notes,$version) {
            // Consent locks always precede delivery locks. No campaign locks are taken here.
            $s=$this->subscription($address,true);
            if ($version!==null && $s->version!==$version) $this->fail('This recipient changed. Reload before recording the request.');
            if ($s->status==='unsubscribed') return $s;
            $s->update(['status'=>'unsubscribed','version'=>$s->version+1,'unsubscribed_at'=>now()]);
            NotificationSuppression::firstOrCreate(['channel'=>'email','address'=>$s->address],['reason'=>'unsubscribed']);
            $s->events()->create(['action'=>'unsubscribed','source'=>$actor?'admin':'email_link','actor_id'=>$actor?->id,
                'actor_name'=>$actor?->name,'delivery_id'=>$delivery?->id,'version'=>$s->version,'evidence'=>$notes,'created_at'=>now()]);
            NotificationDelivery::where('channel','email')->where('address',$s->address)->whereIn('status',['queued','retry'])
                ->update(['status'=>'suppressed','note'=>'Unsubscribed. Pending promotional sends stopped; any earlier provider attempt may already have been accepted.','updated_at'=>now()]);
            if ($delivery) NotificationEvent::firstOrCreate(['event_key'=>'unsubscribe:'.$delivery->id.':'.$s->version],[
                'delivery_id'=>$delivery->id,'type'=>'unsubscribed','source'=>'recipient','description'=>'Recipient unsubscribed from promotional emails.',
                'occurred_at'=>now(),'processed_at'=>now(),
            ]);
            return $s;
        });
        return $subscription;
    }
    public function record(NotificationSubscription $subscription, array $data, User $actor, array $uploads=[]): NotificationSubscription
    {
        $data['evidence']=trim($data['evidence']??'');
        Validator::make($data,[
            'source'=>'required|in:'.implode(',',array_keys(self::SOURCES)), 'evidence'=>'required|string|min:20|max:10000',
            'version'=>'required|integer|min:0','request_key'=>'required|uuid','confirmed'=>'required|accepted',
        ],['evidence.min'=>'Include the recipient’s actual request or a detailed evidence description (at least 20 characters).'])->validate();
        if (!(($data['consented_at']??null) instanceof \DateTimeInterface)) $this->fail('Enter the date and time the recipient gave consent.');
        $created=[];
        try {
            return DB::transaction(function() use($subscription,$data,$actor,$uploads,&$created) {
                $s=NotificationSubscription::lockForUpdate()->findOrFail($subscription->id);
                if ($existing=NotificationConsentEvent::where('request_key',$data['request_key'])->first()) {
                    if ($existing->subscription_id!==$s->id || $existing->actor_id!==$actor->id) $this->fail('This consent request is no longer current.');
                    return $s; // Repeated form submission never reactivates a later unsubscribe.
                }
                if ($s->version!==(int)$data['version']) $this->fail('This recipient changed. Reload and review the current status.');
                $at=Carbon::instance($data['consented_at']);
                $legacy=NotificationSuppression::where('channel','email')->where('address',$s->address)->where('reason','unsubscribed')->first();
                $last=$s->unsubscribed_at ?? $legacy?->created_at;
                if ($at->isFuture() || ($last && $at->lt($last))) $this->fail('Consent must be received after the latest unsubscribe and cannot be in the future.');
                $files=app(Attachments::class)->update([],$uploads,[],$created);
                $action=($s->status==='unsubscribed' || $legacy)?'resubscribed':'consent_recorded';
                $s->update(['status'=>'subscribed','version'=>$s->version+1,'consented_at'=>$at]);
                $s->events()->create(['action'=>$action,'source'=>$data['source'],'actor_id'=>$actor->id,'actor_name'=>$actor->name,
                    'version'=>$s->version,'request_key'=>$data['request_key'],'consented_at'=>$at,'evidence'=>$data['evidence'],'files'=>$files,'created_at'=>now()]);
                // Renewed consent never clears an independent bounce, complaint or provider block.
                NotificationSuppression::where('channel','email')->where('address',$s->address)->where('reason','unsubscribed')->delete();
                return $s;
            });
        } catch (\Throwable $e) { app(Attachments::class)->discardUncommitted($created); throw $e; }
    }
    private function fail(string $message): never { throw ValidationException::withMessages(['consent'=>$message]); }
}
