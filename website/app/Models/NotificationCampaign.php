<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationCampaign extends Model
{
    use SoftDeletes;
    protected $guarded = ['id'];
    protected $hidden = ['body', 'audience', 'review', 'snapshot'];
    protected function casts(): array { return ['body'=>'encrypted', 'audience'=>'encrypted:array', 'review'=>'encrypted:array', 'snapshot'=>'encrypted:array', 'include_alternate'=>'boolean', 'track_opens'=>'boolean', 'track_clicks'=>'boolean', 'revision'=>'integer', 'tested_revision'=>'integer', 'tested_settings_revision'=>'integer', 'tested_at'=>'datetime', 'scheduled_at'=>'datetime', 'queued_at'=>'datetime', 'completed_at'=>'datetime']; }
    public function deliveries() { return $this->hasMany(NotificationDelivery::class, 'campaign_id'); }
    public function links() { return $this->hasMany(NotificationLink::class, 'campaign_id'); }
    public function author() { return $this->belongsTo(User::class, 'created_by'); }
    public function getNumberAttribute(): string { return 'NT-'.str_pad((string)$this->id, 5, '0', STR_PAD_LEFT); }
}
