<?php
namespace App\Services\Notifications;

use App\Models\{NotificationDelivery, SmtpSetting};
use App\Services\{ResendEmail, SmtpConfiguration};
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\{Address, Email};

class EmailChannel implements Channel
{
    public function send(NotificationDelivery $delivery, SmtpSetting $settings): string
    {
        $p = $delivery->payload;
        $files = $p['attachments'] ?? [];
        app(Attachments::class)->verify($files);
        $attachments = array_map(fn($file)=>['filename'=>$file['name'],'content'=>base64_encode(app(Attachments::class)->read($file))],$files);
        $message = (new Email)->to($delivery->address)->subject($p['subject'])->from(new Address($p['from_address'],$p['from_name']));
        if ($delivery->provider==='resend') return app(ResendEmail::class)->send($settings,$message,$p['text'],$delivery->idempotency_key,$attachments,$p['html'],$p['headers']);
        app(SmtpConfiguration::class)->apply();
        Mail::purge('configured_smtp');
        $sent = Mail::mailer('configured_smtp')->html($p['html'],function($mail) use ($delivery,$p,$files,$attachments) {
            $mail->to($delivery->address)->from($p['from_address'],$p['from_name'])->subject($p['subject']);
            $email = $mail->getSymfonyMessage();
            $email->text($p['text']);
            foreach ($attachments as $i=>$attachment) $mail->attachData(base64_decode($attachment['content']),$attachment['filename'],['mime'=>$files[$i]['mime']]);
            foreach ($p['headers'] as $name=>$value) $email->getHeaders()->addTextHeader($name,$value);
        });
        if (!$sent) throw new \RuntimeException('Provider did not confirm acceptance.');
        return $sent->getMessageId();
    }
}
