<?php
namespace App\Http\Middleware;
use App\Models\{Order,Payment};
use Closure;
use Illuminate\Http\Request;
class ArchivedOrderReadOnly {
 public function handle(Request $request, Closure $next) {
  if(!$request->isMethodSafe()) {
   $order=$request->route('order');$payment=$request->route('payment');
   if(!$order instanceof Order && $payment instanceof Payment) $order=$payment->order;
   if($order instanceof Order) $order->assertEditable();
  }
  return $next($request);
 }
}
