<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Password,Mail};
use Illuminate\Support\Str;
class CustomerPasswordController extends Controller {
 public function forgot(){return view('customer.password',['reset'=>false]);}
 public function form(Request $r,string $token){return view('customer.password',['reset'=>true,'token'=>$token,'email'=>$r->query('email')]);}
 public function send(Request $r){
  $d=$r->validate(['email'=>'required|email|max:255']);
  $user=User::whereRaw('lower(email) = ?',[strtolower(trim($d['email']))])->first();
  if($user?->canSignIn()){
   Password::sendResetLink(['email'=>$user->email],function($user,$token){
    $url=route('customer.password.reset',['token'=>$token,'email'=>$user->email]);
    app(\App\Services\OutgoingEmail::class)->template('password_reset',$user->email,['reset_url'=>$url,'expires_minutes'=>60],$user);
   });
  }
  return back()->with('status','If an active account exists for this email, a password reset link has been sent.');
 }
 public function update(Request $r){
  $d=$r->validate(['token'=>'required|string','email'=>'required|email','password'=>'required|string|min:10|confirmed']);
  $d['email']=strtolower(trim($d['email']));
  $user=User::where('email',$d['email'])->first();
  if(!$user?->canSignIn())return back()->withErrors(['email'=>'This reset link is invalid or expired.']);
  $status=Password::reset($d,function($user,$password){$user->forceFill(['password'=>$password,'remember_token'=>Str::random(60)])->save();});
  if($status!==Password::PASSWORD_RESET)return back()->withErrors(['email'=>'This reset link is invalid or expired.'])->withInput($r->only('email'));
  return redirect()->route('customer.login')->with('status','Password updated. You can now log in.');
 }
 public function invite(Request $r,User $user){
  abort_unless($user->isCustomer() && $user->is_active && hash_equals(sha1($user->email),(string)$r->query('hash')),403);
  if(!$user->email_verified_at)$user->forceFill(['email_verified_at'=>now()])->save();
  return redirect()->route('customer.password.forgot')->with('status','Email verified. Enter your email to receive a link to set your password.');
 }
}
