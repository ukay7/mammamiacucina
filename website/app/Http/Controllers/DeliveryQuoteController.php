<?php
namespace App\Http\Controllers;
use App\Models\GeneralSetting;
use App\Services\{DeliveryQuote,StorefrontCart};
use Illuminate\Http\Request;
class DeliveryQuoteController extends Controller {
 public function options(Request $r,DeliveryQuote $service){
  $d=$r->validate(['postal_code'=>'required|string|max:30','country'=>'required|string|max:100']);
  $settings=GeneralSetting::findOrFail(1);
  return response()->json($service->options($settings->warehouse_postal_code??'',$d['postal_code'],$d['country']))->header('Cache-Control','no-store, private');
 }
 public function __invoke(Request $r,DeliveryQuote $service,StorefrontCart $cart){
  $d=$r->validate(['postal_code'=>'required|string|max:30','delivery_service'=>'required|string|max:20','country'=>'required|string|max:100']);
  if(!in_array(strtolower(trim($d['country'])),['canada','ca']))return response()->json(['message'=>'Delivery is available only in supported Canadian postal areas.'],422);
  $settings=GeneralSetting::findOrFail(1);abort_unless($settings->matrix_delivery_enabled,422);
  $quote=$service->quote($settings->warehouse_postal_code??'',$d['postal_code'],$d['delivery_service']);
  $subtotal=$cart->snapshot()['total'];$tax=$settings->taxFor($subtotal);
  $r->session()->put('delivery_quote',$quote+['postal_code'=>DeliveryQuote::postal($d['postal_code'])]);
  return response()->json($quote+['total_cents'=>$subtotal+$tax+$quote['delivery_cents']])->header('Cache-Control','no-store, private');
 }
}
