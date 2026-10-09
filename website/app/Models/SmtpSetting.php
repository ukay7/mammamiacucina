<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SmtpSetting extends Model {
 protected $guarded=['id'];
 public function ready():bool {return $this->enabled && $this->tested_at!==null && $this->tested_revision===$this->revision;}
 public static function autoVerifyIndividual():bool {return config('customer_accounts.auto_verify_individuals',true) && !(static::find(1)?->ready()??false);}
 protected $hidden=['password','api_key'];
 protected function casts():array {return ['enabled'=>'boolean','api_key'=>'encrypted','password'=>'encrypted','port'=>'integer','revision'=>'integer','tested_revision'=>'integer','tested_at'=>'datetime'];}
}
