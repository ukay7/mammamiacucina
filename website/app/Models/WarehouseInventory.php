<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WarehouseInventory extends Model {
 protected $guarded=['id']; protected function casts():array{return ['quantity_on_hand'=>'decimal:3'];}
 public function warehouse(){return $this->belongsTo(Warehouse::class);} public function product(){return $this->belongsTo(Product::class);}
}
