<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Role;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
class CustomerAccountController extends Controller {
 public function login(){return auth()->check() ? redirect()->route(auth()->user()->isCustomer()?'customer.orders':'admin.dashboard') : view('customer.auth',['register'=>false]);}
 public function register(){return auth()->check() ? redirect()->route(auth()->user()->isCustomer()?'customer.orders':'admin.dashboard') : view('customer.auth',['register'=>true]);}
 public function store(Request $r){
  abort_if(auth()->check(),403);
  $r->merge(['email'=>strtolower(trim((string)$r->email))]);
  $d=$r->validate(['name'=>'required|string|max:100','email'=>'required|email|max:255','phone'=>'required|string|max:40','account_type'=>'required|in:individual,business','password'=>['required','confirmed',Password::min(10)]] + \App\Services\BusinessDetails::rules($r->input('account_type')==='business'));
  if(User::whereRaw('lower(email) = ?',[$d['email']])->exists())throw ValidationException::withMessages(['email'=>'An account already uses this email. Please log in to access your details.']);
  $role=Role::where('name','Customer')->firstOrFail();
  $user=\Illuminate\Support\Facades\DB::transaction(function()use($d,$role){
   $user=new User();$user->forceFill([...\Illuminate\Support\Arr::except($d,\App\Services\BusinessDetails::FIELDS),'role_id'=>$role->id,'is_active'=>true])->save();
   $user->customerRecord()->update(\Illuminate\Support\Arr::only($d,\App\Services\BusinessDetails::FIELDS));
   return $user;
  });
  Auth::login($user);$r->session()->regenerate();$r->session()->forget(['url.intended','checkout_token','checkout_quote','last_order_id','last_order_token']);$this->sendVerification($user);
  return redirect()->route($this->shoppingDestination())->with('status','Your account is created. Check your email to verify it.');
 }
 public function authenticate(Request $r){
  $d=$r->validate(['email'=>'required|email|max:255','password'=>'required|string|max:255']);
  $user=User::whereRaw('lower(email) = ?',[strtolower(trim($d['email']))])->first();
  if(!$user||!$user->isCustomer()||!$user->is_active||!\Illuminate\Support\Facades\Hash::check($d['password'],$user->password))throw ValidationException::withMessages(['email'=>'Email or password is incorrect.']);
  Auth::login($user);$r->session()->regenerate();$r->session()->forget(['url.intended','checkout_token','checkout_quote','last_order_id','last_order_token']);
  return redirect()->route($this->shoppingDestination());
 }
 private function sendVerification(User $user):void{
  $url=URL::temporarySignedRoute('customer.verify',now()->addMinutes(60),['id'=>$user->id,'hash'=>sha1($user->email)]);
  app(\App\Services\OutgoingEmail::class)->raw("Welcome to Mamma Mia Cucina.\n\nVerify your email to continue checkout:\n".$url."\n\nThis link expires in 60 minutes.",fn($m)=>$m->to($user->email)->subject('Verify your Mamma Mia Cucina account'),'verification',$user);
 }
 public function notice(){
  if(auth()->user()->email_verified_at)return redirect()->route(auth()->user()->businessApprovalPending()?'customer.business.pending':$this->shoppingDestination());
  return view('customer.verify');
 }
 private function shoppingDestination():string {return app(\App\Services\StorefrontCart::class)->snapshot()['count']>0?'theme.cart':'theme.index';}
 public function resend(Request $r){if(!$r->user()->email_verified_at)$this->sendVerification($r->user());return back()->with('status','Verification email sent.');}
 public function verify(Request $r,string $id,string $hash){
  abort_unless((string)$r->user()->id===$id&&hash_equals(sha1($r->user()->email),$hash),403);
  if(!$r->user()->email_verified_at)$r->user()->forceFill(['email_verified_at'=>now()])->save();
  return redirect()->route($r->user()->businessApprovalPending()?'customer.business.pending':$this->shoppingDestination())->with('status','Email verified.');
 }
 public function logout(Request $r){$cart=$r->session()->get('storefront_cart',[]);Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();$r->session()->put('storefront_cart',$cart);return redirect()->route('theme.index');}
 public function orders(Request $r){$orders=$r->user()->customerRecord()->orders()->latest()->paginate(20);return view('customer.orders',compact('orders'));}
 public function order(Request $r,Order $order){abort_unless((int)$order->customer_id===$r->user()->customerRecord()->id,404);$order->load('items');return response()->view('customer.order',compact('order'))->header('Cache-Control','no-store, private');}
 public function printOrder(Request $r,Order $order){
  abort_unless((int)$order->customer_id===$r->user()->customerRecord()->id,404);
  $order->load('items');
  return response()->view('orders.print',compact('order'))->header('Cache-Control','no-store, private');
 }
 public function profile(Request $r){return view('customer.profile',['user'=>$r->user()]);}
 public function updateProfile(Request $r){
  $d=$r->validate(['name'=>'required|string|max:100','phone'=>'required|string|max:40','address'=>'nullable|string|max:255','city'=>'nullable|string|max:100','province'=>'nullable|string|max:100','postal_code'=>'nullable|string|max:30','country'=>'nullable|string|max:100'] + \App\Services\BusinessDetails::rules($r->user()->account_type==='business'));
  // Only these profile fields may change. Email, account type, verification and permissions are immutable here.
    \Illuminate\Support\Facades\DB::transaction(function() use($r,$d){
   $r->user()->forceFill(\Illuminate\Support\Arr::only($d,['name','phone']))->save();
   $r->user()->customerRecord()->update(\Illuminate\Support\Arr::only($d,array_merge(['address','city','province','postal_code','country'],\App\Services\BusinessDetails::FIELDS)));
  });
  return redirect()->route('customer.profile')->with('status','Your profile has been updated.');
 }
}
