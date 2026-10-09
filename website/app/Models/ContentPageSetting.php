<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ContentPageSetting extends Model {
 protected $guarded=['id'];
 public static function forPage(string $page):self{return static::firstOrNew(['page'=>$page],$page==='policies'?['title'=>'Policies','eyebrow'=>'INFORMATION & GUIDANCE','heading'=>'Our policies','introduction'=>'Clear information to help you shop with confidence. Find the policy you need below.']:['title'=>'Careers','eyebrow'=>'GROW WITH MAMMA MIA CUCINA','heading'=>'Find your place on our team','introduction'=>'Explore our departments and discover opportunities to bring your skills, care and creativity to our work.']);}
}
