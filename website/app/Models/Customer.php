<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Customer extends Model {
 protected $guarded=['id'];
 protected function casts(): array {return ['payment_permissions'=>'array','delivery_address'=>'array'];}
 protected function alternateEmail(): \Illuminate\Database\Eloquent\Casts\Attribute {
  return \Illuminate\Database\Eloquent\Casts\Attribute::make(set:fn($value)=>($email=strtolower(trim((string)$value)))!==''?$email:null);
 }
 public function user(){return $this->belongsTo(User::class);}
 public function orders(){return $this->hasMany(Order::class);}
}
