<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{Customer,Product,Order};
use App\Services\CustomerTerms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class CustomerTermsController extends Controller {
 public function save(Request $r,Customer $customer){
  $d=$r->validate(['revision'=>'required|integer','credit_limit'=>'required|numeric|min:0|max:9999999','overage'=>'required|numeric|min:0|max:9999999','credit_policy'=>'required|in:block,warn','cheque_days'=>'required|in:7,10,15','permissions'=>'nullable|array','permissions.*'=>'array','permissions.*.*'=>'boolean']);
  $permissions=[];foreach(['website','pos'] as $source)foreach(array_keys(CustomerTerms::METHODS) as $m)$permissions[$source][$m]=$r->boolean('permissions.'.$source.'.'.$m);
  $this->persist($customer,$d['revision'],['credit_limit_cents'=>(int)round($d['credit_limit']*100),'overage_cents'=>(int)round($d['overage']*100),'credit_policy'=>$d['credit_policy'],'cheque_days'=>(int)$d['cheque_days'],'payment_permissions'=>$permissions],$r);
  return redirect(route('admin.customers.show',$customer).'#customer-settings')->with('status','Manager settings saved.');
 }
 private function persist(Customer $customer,int $revision,array $values,Request $r,array $rows=[]):void {
  DB::transaction(function()use($customer,$revision,$values,$r,$rows){
   $c=Customer::lockForUpdate()->findOrFail($customer->id);
   if($c->commercial_revision!==$revision)throw ValidationException::withMessages(['revision'=>'Settings changed. Reload before saving.']);
   $before=$c->only(array_keys($values));$c->update($values+['commercial_revision'=>$c->commercial_revision+1]);
   foreach($rows as $row)DB::table('customer_product_discounts')->updateOrInsert(['customer_id'=>$c->id,'product_id'=>$row['id']],['discount_bps'=>($row['enabled']??false)?(int)round($row['percent']*100):0,'enabled'=>(bool)($row['enabled']??false),'created_at'=>now(),'updated_at'=>now()]);
   DB::table('customer_setting_events')->insert(['customer_id'=>$c->id,'user_id'=>$r->user()->id,'changes'=>json_encode(['before'=>$before,'after'=>$values,'products'=>$rows]),'created_at'=>now()]);
  });
 }
 public function discounts(Request $r,Customer $customer){
  $d=$r->validate(['q'=>'nullable|string|max:200','page'=>'nullable|integer|min:1','filter'=>'nullable|in:all,enabled','per_page'=>'nullable|in:10,25,50']);
  $q=Product::query()->searchTerm(trim($d['q']??''));
  if(($d['filter']??'all')==='enabled')$q->whereIn('id',DB::table('customer_product_discounts')->where('customer_id',$customer->id)->where('enabled',true)->select('product_id'));
  $products=$q->orderBy('premium_marketing_name')->paginate((int)($d['per_page']??25));
  $rules=DB::table('customer_product_discounts')->where('customer_id',$customer->id)->whereIn('product_id',$products->pluck('id'))->get()->keyBy('product_id');
  return response()->json(['revision'=>$customer->commercial_revision,'order_percent'=>$customer->order_discount_bps/100,'page'=>$products->currentPage(),'last_page'=>$products->lastPage(),'total'=>$products->total(),'rows'=>$products->map(function($p)use($customer,$rules){$r=$rules[$p->id]??null;return ['id'=>$p->id,'name'=>$p->premium_marketing_name,'price'=>app(\App\Services\PosCustomer::class)->price($p,$customer),'percent'=>($r->discount_bps??0)/100,'enabled'=>(bool)($r->enabled??false)];})]);
 }
 public function saveDiscounts(Request $r,Customer $customer){
  $d=$r->validate(['revision'=>'required|integer','order_percent'=>'required|numeric|min:0|max:100','rows'=>'present|array|max:500','rows.*.id'=>'required|integer|distinct|exists:products,id','rows.*.percent'=>'required|numeric|min:0|max:100','rows.*.enabled'=>'required|boolean']);
  $this->persist($customer,$d['revision'],['order_discount_bps'=>(int)round($d['order_percent']*100)],$r,$d['rows']);
  return response()->json(['message'=>'Customer discounts saved.','revision'=>$customer->fresh()->commercial_revision]);
 }
 public function overdue(Request $r){
  $d=$r->validate(['q'=>'nullable|string|max:200']);$q=trim($d['q']??'');
  $orders=Order::with('customer')->where('payment_method','cheque')->whereNotIn('status',['cancelled'])->where('payment_status','unpaid')->whereDate('cheque_due_date','<',today())->when($q,fn($b)=>$b->where(fn($b)=>$b->where('number','like','%'.$q.'%')->orWhereHas('customer',fn($c)=>$c->where('name','like','%'.$q.'%')->orWhere('email','like','%'.$q.'%'))))->orderBy('cheque_due_date')->paginate(25)->withQueryString();
  return view('admin.customers.overdue-cheques',compact('orders','q'));
 }
}
