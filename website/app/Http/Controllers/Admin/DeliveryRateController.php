<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralSetting;
use App\Services\DeliveryQuote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeliveryRateController extends Controller
{
 public function index(Request $request)
 {
  $data=$request->validate(['service'=>['nullable',Rule::exists('delivery_services','code')],'edit'=>'nullable|integer','from'=>'nullable|string|max:7','to'=>'nullable|string|max:7','search'=>'nullable|string|max:30']);
  $services=DB::table('delivery_services')->get();
  $selected=$data['service']??'bullet';
  $settings=GeneralSetting::findOrFail(1);
  $rates=DB::table('delivery_rates')->where('service_code',$selected)->orderBy('from_zone')->orderBy('to_zone')->get();
  $matrix=$rates->keyBy(fn($r)=>$r->from_zone.':'.$r->to_zone);
  $editing=isset($data['edit'])?$rates->firstWhere('id',(int)$data['edit']):null;
  $from=$data['from']??($settings->warehouse_postal_code??'');$to=$data['to']??'';
  $comparison=[];$quoteError=null;$fromZone=$toZone=null;
  if($to!==''){
   try {
    $from=DeliveryQuote::postal($from);$to=DeliveryQuote::postal($to);
    $fromZone=DB::table('delivery_postal_zones')->where('prefix',substr($from,0,3))->value('zone');
    $toZone=DB::table('delivery_postal_zones')->where('prefix',substr($to,0,3))->value('zone');
    if(!$fromZone||!$toZone)throw ValidationException::withMessages(['postal_code'=>'One or both postal codes are outside the delivery coverage area.']);
    $comparison=DB::table('delivery_rates')->where('from_zone',$fromZone)->where('to_zone',$toZone)->get()->keyBy('service_code');
   }catch(ValidationException $e){$quoteError=collect($e->errors())->flatten()->first();}
  }
  $search=strtoupper(trim($data['search']??''));
  $zones=DB::table('delivery_postal_zones')->when($search!=='',fn($q)=>$q->where('prefix','like',$search.'%'))->orderBy('prefix')->get();
  return response()->view('admin.delivery.index',compact('services','selected','settings','rates','matrix','editing','from','to','comparison','quoteError','fromZone','toZone','search','zones'))->header('Cache-Control','no-store, private');
 }

 public function updateDescription(Request $request,string $service)
 {
  abort_unless(DB::table('delivery_services')->where('code',$service)->exists(),404);
  $data=$request->validate(['description'=>'nullable|string|max:1000']);
  DB::table('delivery_services')->where('code',$service)->update(['description'=>trim($data['description']??'')]);
  return redirect()->route('admin.delivery.index',['service'=>$service])->with('status','Service description saved. Delivery dropdowns now use this description.');
 }

 public function update(Request $request,int $rate)
 {
  $data=$request->validate(['revision'=>'required|integer|min:0','available'=>'required|boolean','amount'=>['nullable','required_if:available,1','regex:/^\d{1,6}(\.\d{1,2})?$/']]);
  $service=DB::transaction(function()use($data,$rate){
   // Match the checkout/import lock order so an order sees a consistent rate.
   DB::table('general_settings')->where('id',1)->lockForUpdate()->first();
   $current=DB::table('delivery_rates')->where('id',$rate)->lockForUpdate()->first();abort_unless($current,404);
   if((int)$current->revision!==(int)$data['revision'])throw ValidationException::withMessages(['rate'=>'This rate changed since you opened it. Reload the page before saving.']);
   $amount=null;
   if($data['available']){$parts=explode('.',$data['amount']);$amount=(int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');}
   DB::table('delivery_rates')->where('id',$rate)->update(['amount_cents'=>$amount,'revision'=>$current->revision+1]);
   return $current->service_code;
  });
  return redirect()->route('admin.delivery.index',['service'=>$service])->with('status','Delivery rate saved. New checkout quotes use this price; existing orders keep their saved charges.');
 }
}
