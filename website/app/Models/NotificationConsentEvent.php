<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationConsentEvent extends Model
{
    public $timestamps=false;
    protected $guarded=['id'];
    protected $hidden=['evidence','files','request_key'];
    protected function casts(): array { return ['evidence'=>'encrypted','files'=>'encrypted:array','version'=>'integer','consented_at'=>'datetime','created_at'=>'datetime']; }
    public function subscription() { return $this->belongsTo(NotificationSubscription::class); }
    public function delivery() { return $this->belongsTo(NotificationDelivery::class); }
}
