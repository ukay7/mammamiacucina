<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Customer extends Model {
 public const TIERS = ['standard'=>'Standard','gold'=>'Gold','platinum'=>'Platinum'];

 public function getTierLabelAttribute(): string { return self::TIERS[$this->tier] ?? self::TIERS['standard']; }
 protected $guarded=['id'];
 protected function casts(): array {return ['payment_permissions'=>'array','delivery_address'=>'array'];}
 protected function alternateEmail(): \Illuminate\Database\Eloquent\Casts\Attribute {
  return \Illuminate\Database\Eloquent\Casts\Attribute::make(set:fn($value)=>($email=strtolower(trim((string)$value)))!==''?$email:null);
 }
 public function user(){return $this->belongsTo(User::class);}
 public function orders(){return $this->hasMany(Order::class);}
 public function profileRequests(){return $this->hasMany(CustomerProfileRequest::class);}
 public function creditApplications(){return $this->hasMany(CreditApplication::class);}
}
