<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class DeliveryQuote {
 public static function postal(string $postal):string {
  $value=strtoupper(preg_replace('/\s+/','',trim($postal)));
  if(!preg_match('/^[ABCEGHJ-NPRSTVXY][0-9][ABCEGHJ-NPRSTVWXYZ]([0-9][ABCEGHJ-NPRSTVWXYZ][0-9])?$/',$value))throw ValidationException::withMessages(['postal_code'=>'Enter a valid Canadian postal code.']);
  return strlen($value)===6?substr($value,0,3).' '.substr($value,3):$value;
 }
 public function options(string $from,string $to,string $country):array {
  if(!in_array(strtolower(trim($country)),['canada','ca']))throw ValidationException::withMessages(['country'=>'Delivery is available only in supported Canadian postal areas.']);
  $from=self::postal($from);$to=self::postal($to);
  $fz=DB::table('delivery_postal_zones')->where('prefix',substr($from,0,3))->value('zone');
  $tz=DB::table('delivery_postal_zones')->where('prefix',substr($to,0,3))->value('zone');
  if(!$fz||!$tz)throw ValidationException::withMessages(['postal_code'=>'One or both postal codes are outside the delivery coverage area.']);
  $rates=DB::table('delivery_rates')->where('from_zone',$fz)->where('to_zone',$tz)->get()->keyBy('service_code');
  return ['from_postal'=>$from,'to_postal'=>$to,'from_zone'=>(int)$fz,'to_zone'=>(int)$tz,'services'=>DB::table('delivery_services')->where('is_active',true)->get()->map(fn($s)=>['code'=>$s->code,'name'=>$s->name,'description'=>trim($s->description??''),'notes'=>trim($s->notes??''),'amount_cents'=>isset($rates[$s->code])?$rates[$s->code]->amount_cents:null])->all()];
 }
 public function quote(string $from,string $to,string $service):array {
  if(!DB::table('delivery_services')->where('code',$service)->where('is_active',true)->exists())throw ValidationException::withMessages(['delivery_service'=>'This delivery service is inactive. Choose another service.']);
  $from=self::postal($from);$to=self::postal($to);
  $fz=DB::table('delivery_postal_zones')->where('prefix',substr($from,0,3))->value('zone');
  $tz=DB::table('delivery_postal_zones')->where('prefix',substr($to,0,3))->value('zone');
  if(!$fz)throw ValidationException::withMessages(['delivery'=>'The warehouse postal code is not in the delivery coverage area.']);
  if(!$tz)throw ValidationException::withMessages(['postal_code'=>'Delivery is not available for this postal code.']);
  $rate=DB::table('delivery_rates')->where(['service_code'=>$service,'from_zone'=>$fz,'to_zone'=>$tz])->first();
  if(!$rate || $rate->amount_cents===null)throw ValidationException::withMessages(['delivery_service'=>'This service is unavailable for the selected route. Choose another service.']);
  return ['delivery_to_postal'=>$to,'delivery_service_name'=>DB::table('delivery_services')->where('code',$service)->value('name'),'delivery_rate_cents'=>(int)$rate->amount_cents,'delivery_service'=>$service,'delivery_from_postal'=>$from,'delivery_from_zone'=>(int)$fz,'delivery_to_zone'=>(int)$tz,'delivery_cents'=>(int)$rate->amount_cents];
 }
}
