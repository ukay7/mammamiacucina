<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerInvoice extends Model {
 public $timestamps=false;
 protected $guarded=['id'];
 protected function casts():array{return ['snapshot'=>'array','issued_at'=>'datetime','historical'=>'boolean'];}
 public function order(){return $this->belongsTo(Order::class);}
 public function getLabelAttribute():string{return 'INV-'.$this->number;}
}
