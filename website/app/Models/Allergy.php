<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Allergy extends Model {
 protected $guarded=['id'];
 public function products(){return $this->belongsToMany(Product::class);}
 public function iconUrl(){return $this->icon_path?route('allergy.icon',$this):($this->asset_path?asset($this->asset_path):null);}
}
