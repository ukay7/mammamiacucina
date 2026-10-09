<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\SmtpSetting;
use App\Services\SmtpConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Mail};
use Illuminate\Validation\ValidationException;
class SmtpSettingController extends Controller {
 public function edit(){
  $settings=SmtpSetting::find(1) ?? new SmtpSetting(['enabled'=>false,'host'=>'smtp.gmail.com','port'=>587,'encryption'=>'tls','username'=>'info@mammamiacucina.ca','from_address'=>'info@mammamiacucina.ca','from_name'=>'Mamma Mia Cucina','revision'=>0]);
  return response()->view('admin.settings.email',compact('settings'))->header('Cache-Control','no-store, private');
 }
 public function update(Request $request){
  $request->merge(['delivery_method'=>$request->input('delivery_method','smtp')]);
  $data=$request->validate(['delivery_method'=>'required|in:smtp,resend','api_key'=>'nullable|string|max:1024','enabled'=>'required|boolean','revision'=>'required|integer|min:0','host'=>['exclude_unless:delivery_method,smtp','required','string','max:255','regex:/^[a-zA-Z0-9.-]+$/'],
   'port'=>'exclude_unless:delivery_method,smtp|required|integer|min:1|max:65535','encryption'=>'exclude_unless:delivery_method,smtp|required|in:tls,ssl','username'=>'exclude_unless:delivery_method,smtp|required|string|max:255',
   'password'=>'nullable|string|max:1024','from_address'=>'required|email|max:255','from_name'=>'required|string|max:255']);
  if($data['delivery_method']==='smtp' && strtolower($data['host'])==='smtp.gmail.com' && !(($data['port']==587 && $data['encryption']==='tls') || ($data['port']==465 && $data['encryption']==='ssl')))throw ValidationException::withMessages(['port'=>'Google SMTP requires port 587 with STARTTLS, or port 465 with SSL/TLS.']);
  DB::transaction(function()use($data){
   $settings=SmtpSetting::lockForUpdate()->find(1) ?? new SmtpSetting();
   if((int)($settings->revision??0)!==(int)$data['revision'])throw ValidationException::withMessages(['settings'=>'Settings changed. Reload this page before saving.']);
   $password=$data['password']??null;
   if(($data['host']??'')==='smtp.gmail.com' && $password)$password=str_replace(' ','',$password);
   if($data['enabled'] && $data['delivery_method']==='smtp' && !$password && !$settings->password)throw ValidationException::withMessages(['password'=>'Enter an SMTP password or Google app password before enabling email.']);
   $apiKey=trim($data['api_key']??'');
   if($data['enabled'] && $data['delivery_method']==='resend' && !$apiKey && !$settings->api_key)throw ValidationException::withMessages(['api_key'=>'Enter a Resend API key before enabling API delivery.']);
   $values=\Illuminate\Support\Arr::except($data,['password','api_key','revision']);
   if($apiKey!=='')$values['api_key']=$apiKey;
   if($password!==null && $password!=='')$values['password']=$password;
   $settings->forceFill($values+['id'=>1,'tested_at'=>null,'tested_revision'=>null,'revision'=>(int)($settings->revision??0)+1])->save();
  });
  return redirect()->route('admin.email.edit')->with('status',$data['enabled']?'Email delivery enabled. Send a test email to verify the saved settings.':'Email delivery disabled. Emails will be written to local logs.');
 }
 public function test(Request $request,SmtpConfiguration $configuration){
  $data=$request->validate(['recipient'=>'required|email|max:255']);
  $tested=SmtpSetting::find(1);
  if(!$tested?->enabled)return back()->withErrors(['smtp'=>'Save and enable email delivery before sending a test email.']);
  try {
   if($tested->delivery_method!=='resend'){$configuration->apply();Mail::purge('configured_smtp');}
   $entry=app(\App\Services\OutgoingEmail::class)->template('smtp_test',$data['recipient'],[],null,'configured_smtp');
   if($entry->status!=='accepted')throw new \RuntimeException('SMTP did not accept the test message.');
   $marked=SmtpSetting::whereKey(1)->where('revision',$tested->revision)->where('enabled',true)->update(['tested_at'=>now(),'tested_revision'=>$tested->revision]);
   if(!$marked)return back()->withErrors(['smtp'=>'Settings changed during the test. Test the current settings again.']);
  }catch(\Throwable $e){
   // SMTP exception text can contain credentials or server conversation details.
   return back()->withErrors(['smtp'=>$tested->delivery_method==='resend'?'API test failed. Check your Resend API key, verified sender domain, account limits and outbound HTTPS access to api.resend.com:443.':'Test email failed. Check the host, port, encryption, username and app password, and confirm the server can reach SMTP.']);
  }
  return back()->with('status','Email provider accepted the test email. Email verification is now required for newly created customers. Check the recipient inbox and spam folder.');
 }
}
