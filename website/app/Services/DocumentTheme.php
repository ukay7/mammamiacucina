<?php
namespace App\Services;
use App\Models\{GeneralSetting,Order};
use Illuminate\Support\Facades\Storage;
class DocumentTheme {
 public function brand():array {
  $s=GeneralSetting::find(1);$path=$s?->logo_path?Storage::disk('local')->path($s->logo_path):public_path('logo.png');
  $logo=null;
  if(is_file($path)) {
   $cache=storage_path('framework/cache/document-logo-'.sha1_file($path).'.jpg');
   if(is_file($cache)) {$path=$cache;}
   $bytes=file_get_contents($path);$mime=mime_content_type($path);
   $previousLimit=ini_get('memory_limit');
   if(!is_file($cache) && extension_loaded('gd')) ini_set('memory_limit','512M');
   if(!is_file($cache) && extension_loaded('gd') && ($source=@imagecreatefromstring($bytes))) {
    $w=imagesx($source);$h=imagesy($source);$scale=min(1,240/max($w,$h));$small=imagecreatetruecolor(max(1,(int)round($w*$scale)),max(1,(int)round($h*$scale)));
    imagefill($small,0,0,imagecolorallocate($small,255,255,255));imagecopyresampled($small,$source,0,0,0,0,imagesx($small),imagesy($small),$w,$h);
    ob_start();imagejpeg($small,null,90);$bytes=ob_get_clean();$mime='image/jpeg';imagedestroy($source);imagedestroy($small);unset($source,$small);file_put_contents($cache,$bytes,LOCK_EX);
   }
   ini_set('memory_limit',$previousLimit);
   $logo='data:'.$mime.';base64,'.base64_encode($bytes);
  }
  return ['name'=>'Mamma Mia Cucina','logo'=>$logo,'address'=>$s?->pickup_address,'email'=>$s?->email,'phone'=>$s?->phone,'registration'=>$s?->business_number,'tax_number'=>$s?->tax_number];
 }
 public function order(Order $order,?\App\Models\CustomerInvoice $invoice=null,?\App\Models\OrderSettlement $receipt=null):array {
  $order->loadMissing('items','customer','payment','settlements');$money=fn($n)=>$n===null?'To be confirmed':'$'.number_format($n/100,2);
  $snapshot=$invoice?->snapshot;$o=$snapshot['order']??$order->getAttributes();$items=$snapshot['items']??$order->items->toArray();$c=$order->customer;
  $isReceipt=$receipt || ($order->source==='pos' && $order->collected_cents>0 && !$invoice);
  $title=$invoice?'Invoice':($isReceipt?($receipt?->amount_cents<0?'Refund Receipt':'Payment Receipt'):'Order Confirmation');
  $number=$invoice?->label??($receipt?'RCPT-'.$receipt->id:$order->number);
  $summary=['This document'=>$money($invoice?->total_cents??($receipt?->amount_cents??$order->final_total_cents)),'Net received on order'=>$money($order->net_received_cents),'Order balance due'=>$money(max(0,$order->balance_cents))];
  if($c){$b=app(CustomerInvoicing::class)->balances($c);$summary=['Other open order balances'=>$money(max(0,$b['pending']-max(0,$order->balance_cents)))]+$summary+['Current account outstanding'=>$money($b['pending']),'Credit allowance'=>$money($b['allowance']),'Available credit'=>$money($b['available'])];}
  $instructions=match($order->payment_method){'credit'=>'Customer credit: the outstanding balance remains payable until payment is recorded.','cheque'=>'Cheque payment is confirmed only after admin clearance.'.($order->cheque_due_date?' Due date: '.$order->cheque_due_date.'.':''),'etransfer'=>'Include the order number with your transfer. Uploaded proof does not confirm payment until verified.',default=>'Keep the order number as your payment reference. Any outstanding balance remains payable.'};
  $totals=['Product subtotal'=>$money($o['subtotal_cents']),'Delivery'=>$money($o['delivery_cents']),'Tax'=>$money($o['tax_cents']),'Total CAD'=>$money($invoice?->total_cents??$order->final_total_cents),'Net received'=>$money($order->net_received_cents),'Balance due'=>$money(max(0,$order->balance_cents))];
  if($receipt)$totals=['This payment'=>$money(abs($receipt->amount_cents))]+$totals;
  if($order->balance_cents<0)$totals['Refund due']=$money(-$order->balance_cents);
  $billing=array_filter([$c?->business_name?:trim(($o['first_name']??'').' '.($o['last_name']??'')),$c?'Customer account #'.$c->id:null,$c?->address?:($o['address']??null),implode(', ',array_filter([$c?->city?:($o['city']??null),$c?->province?:($o['province']??null),$c?->postal_code?:($o['postal_code']??null)])),$o['email']??null,$o['phone']??null]);
  return ['brand'=>$this->brand(),'title'=>$title,'number'=>$number,'balance_date'=>now()->format('d M Y H:i').' '.config('app.timezone'),'date'=>($invoice?->issued_at??$receipt?->created_at??$order->ordered_at)->format('d M Y H:i').' '.config('app.timezone'),'order_number'=>$order->number,'billing'=>$billing,'summary'=>$summary,'items'=>collect($items)->map(fn($i)=>['code'=>$i['qr_code']?:$i['product_code'],'name'=>$i['name'],'quantity'=>$i['quantity'],'original'=>($i['discount_bps']??0)?$money($i['original_unit_cents']):null,'unit'=>$money($i['unit_cents']),'total'=>$money($i['line_cents']),'discount'=>($i['discount_bps']??0)?($i['discount_bps']/100).'% admin special discount':null])->all(),'totals'=>$totals,'instructions'=>$instructions,'payment'=>$receipt?((CustomerTerms::METHODS[$receipt->method]??ucfirst($receipt->method)).' / '.($receipt->amount_cents<0?'Refund recorded':'Payment received').' / Reference: '.($receipt->reference?:'Cash')):$order->payment_label.' / '.$order->payment_status_label,'delivery'=>$order->fulfillment==='pickup'?'Pickup: '.$order->pickup_address:'Delivery: '.implode(', ',array_filter([$order->address,$order->city,$order->province,$order->postal_code,$order->country])),'notes'=>$order->notes,'notice'=>($invoice?'Invoice amounts are fixed at issuance. Account and payment balances below are current.':'An unpaid or pending order is not proof of payment.').($order->status==='cancelled'?' Order cancelled. No further payment due.':'').($invoice && $order->final_total_cents!==null && $order->final_total_cents!=$invoice->total_cents?' Subsequent order adjustment: '.$money($order->final_total_cents-$invoice->total_cents).'.':''),'discount'=>$o['order_discount_cents']?'Additional order discount: '.$money($o['order_discount_cents']).' (included in product subtotal).':null];
 }
}
