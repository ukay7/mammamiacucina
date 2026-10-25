<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EmailHistory extends Model {
 protected $table='email_history';
 protected $guarded=['id'];
 protected $hidden=['payload','archive'];
 public function order(){return $this->belongsTo(Order::class);}
 protected static function booted():void {
  static::addGlobalScope('active_order',fn($query)=>$query->where(fn($q)=>$q->whereNull('order_id')->orWhereHas('order')));
 }
 protected function casts():array{return ['sent_at'=>'datetime','available_at'=>'datetime','started_at'=>'datetime','archived_at'=>'datetime','payload'=>'encrypted:array','archive'=>'encrypted:array'];}
}
