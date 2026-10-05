<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
class CustomerController extends Controller {
 public function create(){return view('admin.customers.create');}
 public function store(Request $r){
  $r->merge(['email'=>strtolower(trim((string)$r->input('email')))]);
  $d=$r->validate(['name'=>'required|string|max:100','email'=>'required|email|max:255|unique:users,email|unique:customers,email','password'=>'required|string|min:10|confirmed','account_type'=>'required|in:individual,business','phone'=>'nullable|string|max:40','address'=>'nullable|string|max:255','city'=>'nullable|string|max:100','province'=>'nullable|string|max:100','postal_code'=>'nullable|string|max:30','country'=>'nullable|string|max:100','website'=>'nullable|url:http,https|max:255'] + \App\Services\BusinessDetails::rules($r->input('account_type')==='business'));
  $customer=\Illuminate\Support\Facades\DB::transaction(function()use($d){
   $user=new \App\Models\User;
   $user->forceFill(\Illuminate\Support\Arr::only($d,['name','email','password','phone','account_type'])+['is_active'=>true,'email_verified_at'=>$d['account_type']==='individual' && \App\Models\SmtpSetting::autoVerifyIndividual()?now():null])->save();
   $customer=$user->customerRecord();
   $customer->update(\Illuminate\Support\Arr::only($d,array_merge(['address','city','province','postal_code','country','website'],\App\Services\BusinessDetails::FIELDS)));
   return $customer;
  });
  if(!$customer->user->email_verified_at && (\App\Models\SmtpSetting::find(1)?->ready()??false)){
   $user=$customer->user;$url=\Illuminate\Support\Facades\URL::temporarySignedRoute('customer.invite',now()->addDays(2),['user'=>$user->id,'hash'=>sha1($user->email)]);
   try{app(\App\Services\OutgoingEmail::class)->template('admin_invitation',$user->email,['verification_url'=>$url,'expires_minutes'=>2880],$user);}
   catch(\Throwable $e){return redirect()->route('admin.customers.edit',$customer)->withErrors(['email'=>'Customer created, but the invitation could not be sent. Resend verification or verify manually after checking the customer identity.']);}
  }
  return redirect()->route('admin.customers.show',$customer)->with('status','Customer created. Login uses the email and password you entered. Business accounts still require approval and email verification.');
 }
 public function index(Request $request){
  $search=trim((string)$request->query('search',''));
  $customers=Customer::with('user')->withCount('orders')->withMax('orders','created_at')
   ->when($search!=='',fn($q)=>$q->where(fn($q)=>$q->where('name','like',"%{$search}%")->orWhere('email','like',"%{$search}%")->orWhere('phone','like',"%{$search}%")->when(ctype_digit(ltrim($search,'#')),fn($q)=>$q->orWhere('id',(int)ltrim($search,'#')))->orWhere('business_name','like',"%{$search}%")->orWhere('business_bin','like',"%{$search}%")))
   ->latest()->paginate(20)->withQueryString();
  $businessRequests=Customer::with('user')->whereHas('user',fn($q)=>$q->where('account_type','business')->whereNull('business_approved_at'))->latest()->paginate(15,['*'],'requests_page');
  return view('admin.customers.index',compact('customers','search','businessRequests'));
 }
 public function approveBusiness(Customer $customer){
  \Illuminate\Support\Facades\DB::transaction(function()use($customer){
   $user=\App\Models\User::whereKey($customer->user_id)->lockForUpdate()->firstOrFail();
   abort_unless($user->account_type==='business',404);
   if(!$user->is_active)throw \Illuminate\Validation\ValidationException::withMessages(['business'=>'Activate the customer before approving the business account.']);
   \Illuminate\Support\Facades\Validator::make($customer->fresh()->toArray(),\App\Services\BusinessDetails::rules(true))->validate();
   if(!$user->business_approved_at)$user->forceFill(['business_approved_at'=>now(),'business_approved_by'=>auth()->id()])->save();
  });
  return back()->with('status','Business account approved. Business pricing and dashboard access are available after email verification.');
 }
 public function show(Customer $customer){
  $customer->load('user')->loadCount('orders');
  $orders=$customer->orders()->latest('id')->paginate(20);
  $latestOrder=$customer->orders()->latest('id')->first();
  return view('admin.customers.show',compact('customer','orders','latestOrder'));
 }
 public function edit(Customer $customer){return view('admin.customers.edit',compact('customer'));}
 public function update(Request $r,Customer $customer){
  $d=$r->validate(['name'=>'required|string|max:100','phone'=>'nullable|string|max:40','address'=>'nullable|string|max:255','city'=>'nullable|string|max:100','province'=>'nullable|string|max:100','postal_code'=>'nullable|string|max:30','country'=>'nullable|string|max:100','website'=>'nullable|url:http,https|max:255'] + \App\Services\BusinessDetails::rules($customer->account_type==='business'));
  \Illuminate\Support\Facades\DB::transaction(function()use($customer,$d){
   $customer->user->forceFill(\Illuminate\Support\Arr::only($d,['name','phone']))->save();
   $customer->update(\Illuminate\Support\Arr::only($d,array_merge(['address','city','province','postal_code','country','website'],\App\Services\BusinessDetails::FIELDS)));
  });
  return back()->with('status','Customer details updated.');
 }
 public function password(Request $r,Customer $customer){
  $d=$r->validate(['password'=>'required|string|min:10|confirmed']);
  \Illuminate\Support\Facades\DB::transaction(function()use($customer,$d){
   $customer->user->forceFill(['password'=>$d['password'],'remember_token'=>\Illuminate\Support\Str::random(60)])->save();
   \Illuminate\Support\Facades\Password::deleteToken($customer->user);
  });
  return back()->with('status','Customer password updated. Email verification is still required if not already verified.');
 }
 public function verifyEmail(Request $r,Customer $customer){
  $d=$r->validate(['reason'=>'required|string|min:5|max:500']);
  \Illuminate\Support\Facades\DB::transaction(function()use($r,$customer,$d){
   $user=\App\Models\User::whereKey($customer->user_id)->lockForUpdate()->firstOrFail();
   abort_unless($user->isCustomer() && $user->is_active,422,'Only active customers can be manually verified.');
   if($user->email_verified_at)return;
   $user->forceFill(['email_verified_at'=>now()])->save();
   \Illuminate\Support\Facades\DB::table('customer_email_verifications')->insert(['user_id'=>$user->id,'verified_by'=>$r->user()->id,'reason'=>$d['reason'],'created_at'=>now()]);
  });
  return back()->with('status','Customer email manually verified. Business approval, if required, remains separate.');
 }
 public function active(Request $r,Customer $customer){
  $d=$r->validate(['is_active'=>'required|boolean']);
  $customer->user->forceFill(['is_active'=>(bool)$d['is_active'],'remember_token'=>\Illuminate\Support\Str::random(60)])->save();
  return back()->with('status',$d['is_active']?'Customer account activated.':'Customer account deactivated. Login and account access are blocked.');
 }
 public function resend(Customer $customer){
  $user=$customer->user;
  if(!$user->is_active)return back()->withErrors(['customer'=>'Activate this customer before sending verification.']);
  if($user->email_verified_at)return back()->with('status','This email is already verified.');
  $url=\Illuminate\Support\Facades\URL::temporarySignedRoute('customer.invite',now()->addDays(2),['user'=>$user->id,'hash'=>sha1($user->email)]);
  app(\App\Services\OutgoingEmail::class)->template('admin_verification',$user->email,['verification_url'=>$url,'expires_minutes'=>2880],$user);
  return back()->with('status','Verification email sent.');
 }
}
