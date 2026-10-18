<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerNote extends Model {
 protected $guarded=['id'];
 public function author(){return $this->belongsTo(User::class,'user_id');}
 public function customer(){return $this->belongsTo(Customer::class);}
}
