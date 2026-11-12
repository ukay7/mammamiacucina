<?php
namespace App\Services\Notifications;

use App\Models\{GeneralSetting, NotificationCampaign, NotificationDelivery, SmtpSetting};

class Message
{
    public static function url(string $path): string { return rtrim(config('app.url'),'/').'/'.ltrim($path,'/'); }

    public function snapshot(NotificationCampaign $campaign, SmtpSetting $settings): array
    {
        $company = GeneralSetting::find(1);
        return ['subject'=>$campaign->subject, 'body'=>$campaign->body, 'track_opens'=>$campaign->track_opens, 'track_clicks'=>$campaign->track_clicks,
            'from_address'=>$settings->from_address, 'from_name'=>$settings->from_name, 'provider'=>$settings->delivery_method, 'settings_revision'=>$settings->revision,
            'company'=>$company?->document_company_name ?: GeneralSetting::DOCUMENT_COMPANY_NAME, 'address'=>$company?->mailing_address ?: '',
            'phone'=>$company?->phone ?: '', 'email'=>$company?->email ?: $settings->from_address, 'base_url'=>rtrim(config('app.url'),'/')];
    }

    public function urls(string $body): array
    {
        preg_match_all('~https?://[^\s<>"\x27]+~u', $body, $matches);
        return array_values(array_unique(array_filter(array_map(fn($url)=>rtrim($url, '.,;!?)'),$matches[0]),fn($url)=>strlen($url)<=2000 && filter_var($url,FILTER_VALIDATE_URL))));
    }

    public function render(array $snapshot, ?NotificationDelivery $delivery = null): array
    {
        $test = !$delivery || $delivery->is_test;
        $base = $snapshot['base_url'];
        $unsubscribe = !$test ? $base.'/notifications/unsubscribe/'.$delivery->token : null;
        $links = !$test && $snapshot['track_clicks'] ? $delivery->campaign->links->keyBy('url')->all() : [];
        // Escape all author content; only validated HTTP(S) URLs become links.
        $parts = preg_split('~(https?://[^\s<>"\x27]+)~u',$snapshot['body'],-1,PREG_SPLIT_DELIM_CAPTURE);
        $body = implode('',array_map(function($part) use($links,$base,$delivery) {
            $url = rtrim($part,'.,;!?)');
            if (!str_starts_with($url,'http') || strlen($url)>2000 || !filter_var($url,FILTER_VALIDATE_URL)) return e($part);
            $href = isset($links[$url]) ? $base.'/notifications/click/'.$delivery->token.'/'.$links[$url]->token : $url;
            return '<a href="'.e($href).'" style="color:#285780">'.e($url).'</a>'.e(substr($part,strlen($url)));
        },$parts));
        $html = view('emails.notification', ['message'=>$snapshot, 'bodyHtml'=>nl2br($body), 'unsubscribe'=>$unsubscribe,
            'pixel'=>!$test && $snapshot['track_opens'] ? $base.'/notifications/open/'.$delivery->token.'.gif' : null, 'test'=>$test])->render();
        $text = $snapshot['body']."\n\n".$snapshot['company']."\n".$snapshot['address']."\n".$snapshot['email'];
        if ($unsubscribe) $text .= "\n\nUnsubscribe from bulk notifications: ".$unsubscribe;
        if ($test) $text .= "\n\nTEST EMAIL — only the test recipient receives this message. Tracking and unsubscribe actions are disabled.";
        return ['subject'=>($delivery?->is_test?'[TEST] ':'').$snapshot['subject'], 'html'=>$html, 'text'=>$text,
            'from_address'=>$snapshot['from_address'], 'from_name'=>$snapshot['from_name'],
            'headers'=>$unsubscribe ? ['List-Unsubscribe'=>'<'.$unsubscribe.'>', 'List-Unsubscribe-Post'=>'List-Unsubscribe=One-Click'] : []];
    }
}
