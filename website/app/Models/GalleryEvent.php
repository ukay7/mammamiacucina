<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GalleryEvent extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['is_active'=>'boolean','event_date'=>'date'];}
 public function photos(){return $this->hasMany(GalleryPhoto::class)->orderBy('id');}
}
