<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class HomeSection extends Model {
 protected $primaryKey='key';public $incrementing=false;protected $keyType='string';protected $guarded=[];
 protected function casts():array {return ['content'=>'array','revision'=>'integer'];}
 public function imageUrl():string {return $this->image_path ? route('home-section.image',['section'=>$this->key,'v'=>$this->revision]) : asset($this->content['image']);}
}
