<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['payload', 'test_snapshot', 'credential_hash', 'token', 'idempotency_key'];
    protected function casts(): array { return ['is_test'=>'boolean', 'campaign_revision'=>'integer', 'settings_revision'=>'integer', 'attempts'=>'integer', 'payload'=>'encrypted:array', 'test_snapshot'=>'encrypted:array', 'available_at'=>'datetime', 'started_at'=>'datetime', 'first_attempt_at'=>'datetime', 'accepted_at'=>'datetime', 'delivered_at'=>'datetime', 'opened_at'=>'datetime', 'last_opened_at'=>'datetime', 'clicked_at'=>'datetime']; }
    public function campaign() { return $this->belongsTo(NotificationCampaign::class, 'campaign_id')->withTrashed(); }
    public function events() { return $this->hasMany(NotificationEvent::class, 'delivery_id'); }
    public function customer() { return $this->belongsTo(Customer::class); }
}
