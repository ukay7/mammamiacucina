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
  $data=$request->validate(['enabled'=>'required|boolean','revision'=>'required|integer|min:0','host'=>['required','string','max:255','regex:/^[a-zA-Z0-9.-]+$/'],
   'port'=>'required|integer|min:1|max:65535','encryption'=>'required|in:tls,ssl','username'=>'required|string|max:255',
   'password'=>'nullable|string|max:1024','from_address'=>'required|email|max:255','from_name'=>'required|string|max:255']);
  DB::transaction(function()use($data){
   $settings=SmtpSetting::lockForUpdate()->find(1) ?? new SmtpSetting();
   if((int)($settings->revision??0)!==(int)$data['revision'])throw ValidationException::withMessages(['settings'=>'Settings changed. Reload this page before saving.']);
   $password=$data['password']??null;
   if($data['host']==='smtp.gmail.com' && $password)$password=str_replace(' ','',$password);
   if($data['enabled'] && !$password && !$settings->password)throw ValidationException::withMessages(['password'=>'Enter an SMTP password or Google app password before enabling email.']);
   $values=\Illuminate\Support\Arr::except($data,['password','revision']);
   if($password!==null && $password!=='')$values['password']=$password;
   $settings->forceFill($values+['id'=>1,'revision'=>(int)($settings->revision??0)+1])->save();
  });
  return redirect()->route('admin.email.edit')->with('status',$data['enabled']?'SMTP enabled. New account emails will use these settings. Send a test email to verify delivery.':'SMTP disabled. Emails will be written to local logs.');
 }
 public function test(Request $request,SmtpConfiguration $configuration){
  $data=$request->validate(['recipient'=>'required|email|max:255']);
  if(!SmtpSetting::find(1)?->enabled)return back()->withErrors(['smtp'=>'Save and enable SMTP before sending a test email.']);
  try {
   $configuration->apply();Mail::purge('configured_smtp');
   app(\App\Services\OutgoingEmail::class)->raw('Your Mamma Mia Cucina website can send email using the saved SMTP settings.',fn($message)=>$message->to($data['recipient'])->subject('Mamma Mia Cucina — SMTP test'),'smtp_test',null,'configured_smtp');
  }catch(\Throwable $e){
   // SMTP exception text can contain credentials or server conversation details.
   return back()->withErrors(['smtp'=>'Test email failed. Check the host, port, encryption, username and app password, and confirm the server can reach SMTP.']);
  }
  return back()->with('status','Your SMTP server accepted the test email. Check the recipient inbox and spam folder.');
 }
}
