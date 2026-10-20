<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Warehouse extends Model {
 protected $guarded=['id']; protected function casts():array{return ['is_default'=>'boolean','is_active'=>'boolean'];}
 public function inventories(){return $this->hasMany(WarehouseInventory::class);}
 public function countTasks(){return $this->hasMany(StockCountTask::class);}
}
