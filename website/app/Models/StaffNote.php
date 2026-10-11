<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StaffNote extends Model {
 protected $guarded=['id','user_id'];
 protected function casts(): array { return ['due_date'=>'date','pinned'=>'boolean','completed_at'=>'datetime']; }
}
