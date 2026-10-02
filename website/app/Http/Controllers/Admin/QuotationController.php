<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{Quotation,Customer,Product,GeneralSetting};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class QuotationController extends Controller {
 public function index(Request $r){
  $search=trim((string)$r->query('search',''));
  $quotations=Quotation::with('customer')->when($search!=='',fn($q)=>$q->where(function($q)use($search){$q->where('id',(int)preg_replace('/^MMC-Q-/i','',$search))->orWhereHas('customer',fn($c)=>$c->where('name','like',"%{$search}%")->orWhere('email','like',"%{$search}%"));}))->latest()->paginate(20)->withQueryString();
  return view('admin.quotations.index',compact('quotations','search'));
 }
 public function create(){return $this->form(new Quotation);}
 public function edit(Quotation $quotation){return $this->form($quotation);}
 private function form(Quotation $quotation){
  $customers=Customer::with('user')->orderBy('name')->get()->map(fn($c)=>['id'=>$c->id,'name'=>$c->name,'email'=>$c->email,'tier'=>$c->user?->is_active && $c->user?->email_verified_at && $c->user?->business_approved_at && $c->user?->account_type==='business'?'business':'individual']);
  $products=Product::where('is_active',true)->orderBy('premium_marketing_name')->get()->map(fn($p)=>['id'=>$p->id,'name'=>$p->premium_marketing_name,'code'=>$p->product_code,'individual'=>(string)$p->total_selling_price_cad,'business'=>(string)($p->business_selling_price_cad??$p->total_selling_price_cad)]);
  $tax=GeneralSetting::find(1)?->tax_basis_points??0;
  return view('admin.quotations.form',compact('quotation','customers','products','tax'));
 }
 public static function cents($v):int {[$a,$b]=array_pad(explode('.',(string)$v,2),2,'');return (int)$a*100+(int)str_pad(substr($b,0,2),2,'0')+((int)($b[2]??0)>=5?1:0);}
 private function data(Request $r,?Quotation $existing=null):array {
  $money=['required','numeric','min:0','max:1000000','regex:/^\d+(\.\d{1,2})?$/'];
  $percent=['required','numeric','between:0,100','regex:/^\d+(\.\d{1,2})?$/'];
  $d=$r->validate(['customer_id'=>'required|exists:customers,id','due_date'=>'required|date_format:Y-m-d','pricing_tier'=>'required|in:auto,individual,business','discount_percent'=>$percent,'charge_percent'=>['required','numeric','between:0,100','regex:/^\d+(\.\d{1,2})?$/'],'tax_percent'=>['required','numeric','between:0,100','regex:/^\d+(\.\d{1,2})?$/'],'notes'=>'nullable|string|max:5000','items'=>'required|array|min:1|max:1000','items.*.product_id'=>'required|integer|distinct','items.*.quantity'=>'required|integer|between:1,10000','items.*.discount_percent'=>$percent,'items.*.unit_price'=>$money]);
  $customer=Customer::with('user')->findOrFail($d['customer_id']);
  $tier=$d['pricing_tier'];if($tier==='auto')$tier=$customer->user?->is_active && $customer->user?->email_verified_at && $customer->user?->business_approved_at && $customer->user?->account_type==='business'?'business':'individual';
  $products=Product::whereIn('id',array_column($d['items'],'product_id'))->get()->keyBy('id');$items=[];$subtotal=0;$lineDiscount=0;
  foreach($d['items'] as $row){
   $saved=$existing && $existing->customer_id==$customer->id && $existing->pricing_tier===$tier ? collect($existing->items)->firstWhere('product_id',(int)$row['product_id']):null;
   $p=$products->get($row['product_id']);
   if(!$saved && (!$p || !$p->is_active))throw ValidationException::withMessages(['items'=>'A selected product is unavailable. Remove it and try again.']);
   $price=self::cents($row['unit_price']);
   $gross=$price*(int)$row['quantity'];$lineBp=self::cents($row['discount_percent']);$discount=intdiv($gross*$lineBp+5000,10000);
   if($discount>$gross)throw ValidationException::withMessages(['items'=>'An item discount cannot exceed its line amount.']);
   $items[]=['product_id'=>(int)$row['product_id'],'name'=>$saved['name']??$p->premium_marketing_name,'code'=>$saved['code']??$p->product_code,'quantity'=>(int)$row['quantity'],'unit_cents'=>$price,'discount_basis_points'=>$lineBp,'discount_cents'=>$discount,'total_cents'=>$gross-$discount];$subtotal+=$gross;$lineDiscount+=$discount;
  }
  $discountBp=self::cents($d['discount_percent']);$net=$subtotal-$lineDiscount;$discount=intdiv($net*$discountBp+5000,10000);
  if($discount>$net)throw ValidationException::withMessages(['discount'=>'Overall discount cannot exceed the discounted subtotal.']);
  $net-=$discount;$chargeBp=self::cents($d['charge_percent']);$taxBp=self::cents($d['tax_percent']);$charge=intdiv($net*$chargeBp+5000,10000);$tax=intdiv(($net+$charge)*$taxBp+5000,10000);
  return ['customer_id'=>$customer->id,'customer_snapshot'=>$customer->only(['id','name','email','phone','address','city','province','postal_code','country','website','business_name','business_bin']),'due_date'=>$d['due_date'],'pricing_tier'=>$tier,'items'=>$items,'subtotal_cents'=>$subtotal,'line_discount_cents'=>$lineDiscount,'discount_cents'=>$discount,'discount_basis_points'=>$discountBp,'charge_basis_points'=>$chargeBp,'charge_cents'=>$charge,'tax_basis_points'=>$taxBp,'tax_cents'=>$tax,'total_cents'=>$net+$charge+$tax,'notes'=>$d['notes']??null];
 }
 public function store(Request $r){$q=DB::transaction(fn()=>Quotation::create($this->data($r)+['created_by'=>$r->user()->id]));return redirect()->route('admin.quotations.show',$q)->with('status','Quotation created.');}
 public function update(Request $r,Quotation $quotation){DB::transaction(function()use($r,$quotation){$q=Quotation::whereKey($quotation->id)->lockForUpdate()->firstOrFail();if((int)$r->input('revision')!==$q->revision)throw ValidationException::withMessages(['revision'=>'This quotation changed. Reload before editing.']);$q->update($this->data($r,$q)+['revision'=>$q->revision+1]);});return redirect()->route('admin.quotations.show',$quotation)->with('status','Quotation updated.');}
 public function show(Quotation $quotation){return view('admin.quotations.show',compact('quotation'));}
 public function print(Quotation $quotation){return view('admin.quotations.print',compact('quotation'));}
 public function destroy(Request $r,Quotation $quotation){DB::transaction(function()use($r,$quotation){$q=Quotation::whereKey($quotation->id)->lockForUpdate()->firstOrFail();abort_unless((int)$r->input('revision')===$q->revision,409,'Quotation changed. Reload before deleting.');$q->delete();});return redirect()->route('admin.quotations.index')->with('status','Quotation deleted.');}
}
