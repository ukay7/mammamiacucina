<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
class CustomerController extends Controller {
 public function index(Request $request){
  $search=trim((string)$request->query('search',''));
  $customers=Customer::with('user')->withCount('orders')->withMax('orders','created_at')
   ->when($search!=='',fn($q)=>$q->where(fn($q)=>$q->where('name','like',"%{$search}%")->orWhere('email','like',"%{$search}%")->orWhere('phone','like',"%{$search}%")->orWhere('id',$search)))
   ->latest()->paginate(20)->withQueryString();
  return view('admin.customers.index',compact('customers','search'));
 }
 public function show(Customer $customer){
  $customer->load('user')->loadCount('orders');
  $orders=$customer->orders()->latest('id')->paginate(20);
  $latestOrder=$customer->orders()->latest('id')->first();
  return view('admin.customers.show',compact('customer','orders','latestOrder'));
 }
 public function edit(Customer $customer){return view('admin.customers.edit',compact('customer'));}
 public function update(Request $r,Customer $customer){
  $d=$r->validate(['name'=>'required|string|max:100','phone'=>'nullable|string|max:40','address'=>'nullable|string|max:255','city'=>'nullable|string|max:100','province'=>'nullable|string|max:100','postal_code'=>'nullable|string|max:30','country'=>'nullable|string|max:100']);
  \Illuminate\Support\Facades\DB::transaction(function()use($customer,$d){
   $customer->user->forceFill(\Illuminate\Support\Arr::only($d,['name','phone']))->save();
   $customer->update(\Illuminate\Support\Arr::only($d,['address','city','province','postal_code','country']));
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
  \Illuminate\Support\Facades\Mail::raw("Verify your Mamma Mia Cucina customer account:\n".$url."\nAfter verification, use Forgot password to set your password and view your orders.",fn($m)=>$m->to($user->email)->subject('Verify your customer account'));
  return back()->with('status','Verification email sent.');
 }
}
