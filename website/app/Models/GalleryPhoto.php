<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GalleryPhoto extends Model {protected $guarded=['id'];public function event(){return $this->belongsTo(GalleryEvent::class,'gallery_event_id');}}
