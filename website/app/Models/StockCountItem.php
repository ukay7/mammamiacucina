<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StockCountItem extends Model {
 protected $guarded=['id']; protected function casts():array{return ['actual_quantity'=>'decimal:3','system_quantity_snapshot'=>'decimal:3','counted_at'=>'datetime'];}
 public function task(){return $this->belongsTo(StockCountTask::class,'stock_count_task_id');} public function product(){return $this->belongsTo(Product::class);}
 public function getVarianceAttribute():?float{return $this->actual_quantity===null||$this->system_quantity_snapshot===null?null:(float)$this->actual_quantity-(float)$this->system_quantity_snapshot;}
}
