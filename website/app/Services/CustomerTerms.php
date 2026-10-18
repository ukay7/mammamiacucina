<?php
namespace App\Services;
use App\Models\{Customer,Product,Order};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class CustomerTerms {
 public const METHODS=['cash'=>'Cash','etransfer'=>'E-transfer','cheque'=>'Cheque','card'=>'Card','credit'=>'Customer Credit'];
 public function allowed(Customer $c,string $source): array {
  $permissions=$c->payment_permissions;
  return array_values(array_filter(array_keys(self::METHODS),fn($m)=>($permissions[$source][$m]??true) && ($m!=='credit'||$c->credit_limit_cents>0) && !($source==='pos'&&$m==='card')));
 }
 public function price(Product $p,Customer $c): array {
  $base=app(PosCustomer::class)->price($p,$c);
  $rule=DB::table('customer_product_discounts')->where('customer_id',$c->id)->where('product_id',$p->id)->where('enabled',true)->first();
  $bps=(int)($rule->discount_bps??0);
  return ['original_unit_cents'=>$base,'discount_bps'=>$bps,'unit_cents'=>$base===null?null:$base-(int)round($base*$bps/10000)];
 }
 public function orderDiscount(Customer $c,int $subtotal): int {return (int)round($subtotal*(int)$c->order_discount_bps/10000);}
 public function used(Customer $c): int {
  $used=0;
  foreach($c->orders()->where('payment_method','credit')->whereNotIn('status',['cancelled'])->with(['payment','settlements'])->lazyById(200) as $o)$used+=max(0,$o->balance_cents);
  return $used;
 }
 public function warning(Customer $c,int $newTotal=0): ?string {
  $used=$this->used($c);$limit=(int)$c->credit_limit_cents+(int)$c->overage_cents;
  if($used+$newTotal<=$limit)return null;
  return 'Credit warning: allowance $'.number_format($limit/100,2).', unsettled credit $'.number_format($used/100,2).', this credit order $'.number_format($newTotal/100,2).', excess $'.number_format(($used+$newTotal-$limit)/100,2).'.';
 }
 // Caller holds the customer row lock until the new order is committed.
 public function check(Customer $c,string $source,string $method,int $total): ?string {
  if(!in_array($method,$this->allowed($c,$source),true))throw ValidationException::withMessages(['payment_method'=>'This payment method is disabled for this customer.']);
  if($method!=='credit')return $this->warning($c);
  $warning=$this->warning($c,$total);
  if($warning && $c->credit_policy==='block')throw ValidationException::withMessages(['payment_method'=>$warning.' Choose cash/card or contact administration.']);
  return $warning;
 }
 public function snapshot(Customer $c,string $method): array {return ['order_discount_bps'=>(int)$c->order_discount_bps,'cheque_days'=>$method==='cheque'?$c->cheque_days:null,'cheque_due_date'=>$method==='cheque'?now()->addDays($c->cheque_days)->toDateString():null];}
}
