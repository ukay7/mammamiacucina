<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EmailHistory extends Model {
 protected $table='email_history';
 protected $guarded=['id'];
 protected function casts():array{return ['sent_at'=>'datetime'];}
}
