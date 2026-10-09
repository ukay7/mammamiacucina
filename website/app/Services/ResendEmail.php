<?php
namespace App\Services;
use App\Models\SmtpSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Mime\{Email,Address};

class ResendEmail {
 public function send(SmtpSetting $settings,Email $message,string $body):string {
  if(!$settings->api_key)throw new \RuntimeException('Email API key is missing.');
  $response=Http::withToken($settings->api_key)->acceptJson()->withHeaders(['Idempotency-Key'=>(string)Str::uuid()])
   ->connectTimeout(10)->timeout(25)->withOptions(['allow_redirects'=>false])
   ->post('https://api.resend.com/emails',[
    'from'=>(new Address($settings->from_address,$settings->from_name))->toString(),
    'to'=>array_map(fn($a)=>$a->getAddress(),$message->getTo()),
    'subject'=>$message->getSubject(),'text'=>$body,
   ]);
  // Never expose provider responses, credentials or email contents in exceptions.
  if(!$response->successful() || !is_string($response->json('id')) || $response->json('id')==='')throw new \RuntimeException('Email provider did not accept the message.');
  return $response->json('id');
 }
}
