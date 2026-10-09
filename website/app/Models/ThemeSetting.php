<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ThemeSetting extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['website'=>'array','admin'=>'array','revision'=>'integer'];}
 public function colors(string $scope):array{return array_replace(config('theme_colors.colors'),$this->$scope??[]);}
}
