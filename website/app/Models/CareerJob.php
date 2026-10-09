<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CareerJob extends Model { protected $guarded=['id']; protected function casts():array{return ['is_active'=>'boolean','closing_date'=>'date'];} public function department(){return $this->belongsTo(CareerDepartment::class,'department_id');} public function scopeOpen($query){return $query->where('is_active',true)->whereHas('department',fn($q)=>$q->where('is_active',true))->where(fn($q)=>$q->whereNull('closing_date')->orWhereDate('closing_date','>=',now()->toDateString()));} }
