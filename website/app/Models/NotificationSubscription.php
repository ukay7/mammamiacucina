<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSubscription extends Model
{
    protected $guarded=['id'];
    protected function casts(): array { return ['version'=>'integer','unsubscribed_at'=>'datetime','consented_at'=>'datetime']; }
    public function events() { return $this->hasMany(NotificationConsentEvent::class,'subscription_id'); }
    public function deliveries() { return $this->hasMany(NotificationDelivery::class,'address','address')->where('channel','email'); }
    public function suppression() { return $this->hasOne(NotificationSuppression::class,'address','address')->where('channel','email'); }
    public function latestUnsubscribe() { return $this->hasOne(NotificationConsentEvent::class,'subscription_id')->ofMany(['id'=>'max'],fn($q)=>$q->where('action','unsubscribed')); }
    public function latestEmail() { return $this->hasOne(NotificationDelivery::class,'address','address')->ofMany(['id'=>'max'],fn($q)=>$q->where('channel','email')->where('is_test',false)); }
    public function getStatusLabelAttribute(): string { return ['unknown'=>'Consent not recorded','subscribed'=>'Subscribed','unsubscribed'=>'Unsubscribed'][$this->status] ?? $this->status; }
}
