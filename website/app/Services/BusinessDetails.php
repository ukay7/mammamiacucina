<?php
namespace App\Services;
class BusinessDetails {
 public const FIELDS = ['business_bin','business_name','business_phone','business_email'];
 public static function rules(bool $business): array {
  $prefix=$business?'required':'exclude';
  return ['business_bin'=>"$prefix|string|max:100",'business_name'=>"$prefix|string|max:255",'business_phone'=>"$prefix|string|max:40",'business_email'=>"$prefix|email|max:255"];
 }
}
