<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SitePolicy extends Model { protected $guarded=['id']; protected function casts():array{return ['is_active'=>'boolean','effective_date'=>'date'];} }
