<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Customer extends Model {
 protected $guarded=['id'];
 protected function casts(): array {return ['payment_permissions'=>'array'];}
 public function user(){return $this->belongsTo(User::class);}
 public function orders(){return $this->hasMany(Order::class);}
}
