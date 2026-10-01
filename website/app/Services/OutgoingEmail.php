<?php
namespace App\Services;
use App\Models\{User,EmailHistory};
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
class OutgoingEmail {
 public static function remaining(User $user):int {
  $last=$user->verification_last_sent_at;
  return $last ? max(0,\Carbon\Carbon::parse($last)->timestamp+600-now()->timestamp) : 0;
 }
 public function raw(string $body, callable $callback,string $type,?User $user=null,?string $mailer=null):void {
  $verification=in_array($type,['verification','pos_invitation','admin_verification'],true);
  $reservedAt=now()->startOfSecond();
  if($verification && $user){
   // One atomic database update shares the cooldown across sessions and admin/customer routes.
   $reserved=User::whereKey($user->id)->where(function($q){$q->whereNull('verification_last_sent_at')->orWhere('verification_last_sent_at','<=',now()->subMinutes(10));})->update(['verification_last_sent_at'=>$reservedAt]);
   if(!$reserved)throw ValidationException::withMessages(['email'=>'Please wait 10 minutes between verification emails. Refresh the page to see the remaining time.']);
  }
  $entry=null;
  try {
   $message=new \Illuminate\Mail\Message(new \Symfony\Component\Mime\Email());$callback($message);
   $entry=EmailHistory::create(['recipient'=>implode(', ',array_map(fn($a)=>$a->getAddress(),$message->getSymfonyMessage()->getTo())), 'subject'=>$message->getSymfonyMessage()->getSubject(),'type'=>$type,'status'=>'pending']);
   $manager=Mail::getFacadeRoot();
   $selected=$mailer ?? config('mail.default');
   $entry->update(['mailer'=>$selected]);
   $sent=$mailer ? Mail::mailer($mailer)->raw($body,$callback) : Mail::raw($body,$callback);
   $transport=config('mail.mailers.'.$selected.'.transport');
   $entry->update(['status'=>!$sent?'not_sent':(in_array($transport,['log','array'],true)?'logged_only':'accepted'),'sent_at'=>$sent?now():null,'message_id'=>$sent?->getMessageId()]);
  }catch(\Throwable $e){
   if($entry)$entry->update(['status'=>'failed']);
   if($verification && $user)User::whereKey($user->id)->where('verification_last_sent_at',$reservedAt)->update(['verification_last_sent_at'=>null]);
   // Never expose SMTP credentials or signed URLs from provider exceptions.
   throw ValidationException::withMessages(['email'=>'Email could not be sent. Please try again or contact the administrator.']);
  }
 }
}
