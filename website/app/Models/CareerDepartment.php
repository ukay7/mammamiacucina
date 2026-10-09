<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CareerDepartment extends Model { protected $guarded=['id']; protected function casts():array{return ['is_active'=>'boolean'];} public function jobs(){return $this->hasMany(CareerJob::class,'department_id');} }
