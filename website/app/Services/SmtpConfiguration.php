<?php
namespace App\Services;
use App\Models\SmtpSetting;
use Illuminate\Support\Facades\Schema;
class SmtpConfiguration {
 public function apply():void {
  // Resolve lazily so installation and migrations do not require this table yet.
  if(!Schema::hasTable('smtp_settings'))return;
  $settings=SmtpSetting::find(1);
  if(!$settings)return; // Preserve the environment mailer until an administrator saves settings.
  if(!$settings->enabled){config(['mail.default'=>'log']);return;}
  if($settings->delivery_method==='resend'){config(['mail.default'=>'log']);return;} // API delivery is dispatched by OutgoingEmail.
  config(['mail.default'=>'configured_smtp','mail.from'=>['address'=>$settings->from_address,'name'=>$settings->from_name],
   'mail.mailers.configured_smtp'=>['transport'=>'smtp','scheme'=>$settings->encryption==='ssl'?'smtps':'smtp',
    'host'=>$settings->host,'port'=>$settings->port,'username'=>$settings->username,'password'=>$settings->password,
    'timeout'=>15,'require_tls'=>true,'auto_tls'=>true,'local_domain'=>parse_url(config('app.url'),PHP_URL_HOST)]]);
 }
}
