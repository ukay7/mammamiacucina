<?php
namespace App\Services;
use App\Models\{Order,CustomerInvoice};
use Illuminate\Support\Facades\DB;
class CustomerInvoicing {
 public const ELIGIBLE=['ready_to_dispatch','out_for_delivery','delivered','completed'];
 public function issue(Order $order,bool $historical=false):?CustomerInvoice {
  return DB::transaction(function()use($order,$historical){
   $o=Order::lockForUpdate()->findOrFail($order->id);
   if($existing=CustomerInvoice::where('order_id',$o->id)->first())return $existing;
   if(!in_array($o->status,self::ELIGIBLE)||$o->final_total_cents===null)return null;
   $items=$o->items()->get();if($items->isEmpty())return null;
   $sequence=DB::table('invoice_sequences')->where('id',1)->lockForUpdate()->first();
   $number=$sequence->last_number+1;
   DB::table('invoice_sequences')->where('id',1)->update(['last_number'=>$number]);
   $invoice=CustomerInvoice::create(['order_id'=>$o->id,'number'=>$number,'total_cents'=>$o->final_total_cents,'issued_at'=>now(),'historical'=>$historical,'snapshot'=>['order'=>$o->only(['number','first_name','last_name','email','phone','address','city','province','postal_code','country','fulfillment','subtotal_cents','delivery_cents','tax_cents','order_discount_cents','order_discount_bps','currency','cheque_due_date','ordered_at','created_at']),'items'=>$items->map(fn($i)=>$i->only(['name','product_code','qr_code','quantity','unit_cents','original_unit_cents','discount_bps','line_cents']))->all()]]);
   $o->events()->create(['description'=>'Invoice '.$invoice->label.' issued'.($historical?' for an existing packed/delivered order.': ' after packing / handover confirmation.'),'created_at'=>now()]);
   return $invoice;
  },3);
 }
 public function balances(\App\Models\Customer $customer):array {
  $settlements=DB::table('order_settlements')->selectRaw('order_id, SUM(amount_cents) as settled')->groupBy('order_id');
  $net="(CASE WHEN p.id IS NOT NULL THEN (CASE WHEN p.paid_at IS NOT NULL THEN p.amount_cents ELSE 0 END) - p.refunded_cents ELSE COALESCE(o.manual_collected_cents,CASE WHEN o.payment_status IN ('paid','refunded') THEN COALESCE(o.subtotal_cents+o.delivery_cents+o.tax_cents,0) ELSE 0 END) - COALESCE(o.manual_refunded_cents,CASE WHEN o.payment_status='refunded' THEN COALESCE(o.manual_collected_cents,o.subtotal_cents+o.delivery_cents+o.tax_cents,0) ELSE 0 END) END + COALESCE(s.settled,0))";
  $due="CASE WHEN o.status='cancelled' OR (o.payment_status='refunded' AND $net=0) THEN 0 ELSE COALESCE(o.subtotal_cents+o.delivery_cents+o.tax_cents,0)-$net END";
  $q=DB::table('orders as o')->whereNull('o.deleted_at')->leftJoin('payments as p',fn($join)=>$join->on('p.order_id','=','o.id')->whereNull('p.superseded_at'))->leftJoinSub($settlements,'s','s.order_id','=','o.id')->leftJoin('customer_invoices as i','i.order_id','=','o.id')->where('o.customer_id',$customer->id)->selectRaw("o.payment_method,i.id as invoice_id,($due) as due");
  $totals=DB::query()->fromSub($q,'b')->selectRaw("COALESCE(SUM(CASE WHEN due>0 THEN due ELSE 0 END),0) as pending, COALESCE(SUM(CASE WHEN due>0 AND invoice_id IS NOT NULL THEN due ELSE 0 END),0) as receivables, COALESCE(SUM(CASE WHEN due>0 AND payment_method='credit' THEN due ELSE 0 END),0) as used")->first();
  $allowance=(int)$customer->credit_limit_cents+(int)$customer->overage_cents;
  return ['pending'=>(int)$totals->pending,'receivables'=>(int)$totals->receivables,'used'=>(int)$totals->used,'allowance'=>$allowance,'available'=>$customer->credit_limit_cents>0?max(0,$allowance-$totals->used):0];
 }
}
