<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StockCountTask extends Model {
 protected $guarded=['id']; protected function casts():array{return ['starts_at'=>'datetime','due_at'=>'datetime','last_saved_at'=>'datetime','submitted_at'=>'datetime'];}
 public function warehouse(){return $this->belongsTo(Warehouse::class);} public function assignee(){return $this->belongsTo(User::class,'assigned_to');}
 public function creator(){return $this->belongsTo(User::class,'created_by');} public function items(){return $this->hasMany(StockCountItem::class);}
 public function getDisplayStatusAttribute():string{return $this->status!=='submitted'&&$this->status!=='cancelled'&&$this->due_at?->isPast()?'overdue':str_replace('_',' ',$this->status);}
}
