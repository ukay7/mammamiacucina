<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Quotation extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['items'=>'array','customer_snapshot'=>'array','due_date'=>'date'];}
 public function customer(){return $this->belongsTo(Customer::class);}
 public function getNumberAttribute():string{return 'MMC-Q-'.str_pad((string)$this->id,5,'0',STR_PAD_LEFT);}
}
