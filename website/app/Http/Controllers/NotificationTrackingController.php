<?php
namespace App\Http\Controllers;

use App\Models\{NotificationDelivery, NotificationLink, NotificationSetting, NotificationSuppression, NotificationEvent};
use App\Services\Notifications\Events;
use Illuminate\Http\Request;

class NotificationTrackingController extends Controller
{
    public function open(string $token, Events $events)
    {
        $delivery=NotificationDelivery::where('token',$token)->first();
        if ($delivery) $events->engage($delivery,'open');
        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'),200,['Content-Type'=>'image/gif','Cache-Control'=>'no-store, max-age=0','Pragma'=>'no-cache','X-Content-Type-Options'=>'nosniff']);
    }
    public function click(string $token, string $link, Events $events)
    {
        $delivery=NotificationDelivery::where('token',$token)->where('is_test',false)->firstOrFail();
        $url=NotificationLink::where('token',$link)->where('campaign_id',$delivery->campaign_id)->firstOrFail();
        abort_unless(in_array(parse_url($url->url,PHP_URL_SCHEME),['http','https'],true),404);
        $events->engage($delivery,'click');
        return redirect()->away($url->url)->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');
    }
    public function unsubscribe(Request $r, string $token)
    {
        $delivery=NotificationDelivery::where('token',$token)->where('is_test',false)->where('attempts','>',0)->firstOrFail();
        if ($r->isMethod('POST')) {
            NotificationSuppression::firstOrCreate(['channel'=>'email','address'=>$delivery->address],['reason'=>'unsubscribed']);
            NotificationEvent::firstOrCreate(['event_key'=>'unsubscribe:'.$delivery->id],['delivery_id'=>$delivery->id,'type'=>'unsubscribed','source'=>'recipient','description'=>'Recipient unsubscribed from bulk email notifications.','occurred_at'=>now(),'processed_at'=>now()]);
        }
        $unsubscribed=NotificationSuppression::where('channel','email')->where('address',$delivery->address)->exists();
        return response()->view('notifications.unsubscribe',compact('unsubscribed','token'))->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');
    }
    public function webhook(Request $r, Events $events)
    {
        abort_if(strlen($r->getContent())>65536,413);
        $secret=NotificationSetting::find(1)?->webhook_secret ?: config('notifications.webhook_secret');
        abort_unless($secret,503);
        abort_unless($events->verify($r->getContent(),$r->header('svix-id',''),$r->header('svix-timestamp',''),$r->header('svix-signature',''),$secret),400);
        $data=json_decode($r->getContent(),true);
        abort_unless(is_array($data),400);
        $events->ingest($data,$r->header('svix-id'));
        return response()->json(['received'=>true]);
    }
}
