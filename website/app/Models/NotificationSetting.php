<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NotificationSetting extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['webhook_secret'];
    protected function casts(): array { return ['webhook_secret'=>'encrypted', 'per_run'=>'integer', 'worker_ran_at'=>'datetime', 'webhook_received_at'=>'datetime']; }
}
