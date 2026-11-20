<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{NotificationCampaign, NotificationConsentEvent, NotificationSubscription};
use App\Services\Notifications\{Attachments, AttachmentUnavailable, Consents};
use App\Support\BusinessTime;
use Illuminate\Http\Request;

class NotificationConsentController extends Controller
{
    private function query(Request $r)
    {
        $filters=$r->validate(['q'=>'nullable|string|max:150','status'=>'nullable|in:unknown,subscribed,unsubscribed,blocked','campaign'=>'nullable|integer|min:1']);
        return NotificationSubscription::where('channel','email')
            ->when($filters['q']??null,fn($q,$text)=>$q->where(fn($q)=>$q->where('address','like','%'.$text.'%')->orWhereHas('deliveries',fn($d)=>$d->where('recipient_name','like','%'.$text.'%'))))
            ->when(($filters['status']??'')==='blocked',fn($q)=>$q->whereHas('suppression',fn($q)=>$q->where('reason','!=','unsubscribed')))
            ->when(in_array($filters['status']??'', ['unknown','subscribed','unsubscribed']),fn($q)=>$q->where('status',$filters['status']))
            ->when($filters['campaign']??null,fn($q,$id)=>$q->whereHas('deliveries',fn($d)=>$d->where('campaign_id',$id)->where('is_test',false)))
            ->withCount(['deliveries as accepted_count'=>fn($q)=>$q->where('is_test',false)->whereNotNull('accepted_at')])
            ->withMax(['deliveries as last_sent_at'=>fn($q)=>$q->where('is_test',false)],'accepted_at');
    }
    public function index(Request $r)
    {
        $recipients=$this->query($r)->with(['suppression','latestUnsubscribe.delivery.campaign','latestEmail.customer'])->orderByDesc('id')->paginate(30)->withQueryString();
        $stats=NotificationSubscription::where('channel','email')->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total','status');
        $selectedCampaign=$r->filled('campaign') ? NotificationCampaign::withTrashed()->find($r->integer('campaign')) : null;
        return view('admin.notifications.recipients',compact('recipients','stats','selectedCampaign'));
    }
    public function create(Request $r, Consents $service)
    {
        $data=$r->validate(['address'=>'required|email:rfc|max:255']);
        $subscription=$service->subscription($data['address']);
        return redirect()->route('admin.notifications.recipient',$subscription);
    }
    public function show(NotificationSubscription $subscription)
    {
        abort_unless($subscription->channel==='email',404);
        $subscription->load('suppression');
        $history=$subscription->events()->with('delivery.campaign')->latest('id')->paginate(15,['*'],'history_page')->withQueryString();
        $deliveries=$subscription->deliveries()->with('campaign')->latest('id')->paginate(20,['*'],'mail_page')->withQueryString();
        return view('admin.notifications.recipient',compact('subscription','history','deliveries'));
    }
    public function record(Request $r, NotificationSubscription $subscription, Consents $service)
    {
        abort_unless($subscription->channel==='email',404);
        $data=$r->validate(['version'=>'required|integer|min:0','request_key'=>'required|uuid','source'=>'required|in:'.implode(',',array_keys(Consents::SOURCES)),
            'consented_at'=>'required|date_format:Y-m-d\TH:i,Y-m-d\TH:i:s','evidence'=>'required|string|min:20|max:10000','confirmed'=>'required|accepted',
            'evidence_files'=>'sometimes|array|max:5','evidence_files.*'=>'required|file']);
        $value=$data['consented_at'];
        $data['consented_at']=BusinessTime::input(substr($value,0,16),'consented_at')->addSeconds(strlen($value)===19?(int)substr($value,17,2):0);
        $result=$service->record($subscription,$data,$r->user(),$r->file('evidence_files',[]));
        if ($result->status==='unsubscribed') return redirect()->route('admin.notifications.recipient',$subscription)->with('status','That earlier consent request was already recorded. The newer unsubscribe remains in effect.');
        return redirect()->route('admin.notifications.recipient',$subscription)->with('status','Consent recorded. Future campaigns can use this address unless a provider block remains. Old queued emails will not restart.');
    }
    public function unsubscribe(Request $r, NotificationSubscription $subscription, Consents $service)
    {
        abort_unless($subscription->channel==='email',404);
        $data=$r->validate(['version'=>'required|integer|min:0','notes'=>'required|string|min:10|max:10000','confirmed'=>'required|accepted']);
        $service->unsubscribe($subscription->address,actor:$r->user(),notes:$data['notes'],version:(int)$data['version']);
        return redirect()->route('admin.notifications.recipient',$subscription)->with('status','Unsubscribed. Promotional emails are blocked until fresh consent is recorded. Receipts and password-reset emails continue.');
    }
    public function evidence(NotificationSubscription $subscription, NotificationConsentEvent $event, string $attachment)
    {
        abort_unless($subscription->channel==='email' && $event->subscription_id===$subscription->id,404);
        $file=collect($event->files??[])->firstWhere('id',$attachment);
        abort_unless($file,404);
        try { $bytes=app(Attachments::class)->read($file); }
        catch (AttachmentUnavailable $e) { abort(404,'This evidence file is unavailable.'); }
        return response()->streamDownload(fn()=>print($bytes),$file['name'],[
            'Content-Type'=>$file['mime'],'Cache-Control'=>'no-store, private','X-Content-Type-Options'=>'nosniff',
        ]);
    }
    public function export(Request $r)
    {
        $query=$this->query($r);
        return response()->streamDownload(function() use($query) {
            $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
            $write=fn($values)=>fputcsv($out,array_map(fn($v)=>preg_match('/^[=+@\-\t\r\n]/u',(string)$v)?"'".$v:(string)$v,$values),',','"','');
            $write(['Email','Subscription status','Accepted campaign emails','Last accepted (Toronto)','Last unsubscribe (Toronto)','Consent received (Toronto)']);
            foreach ($query->orderBy('id')->cursor() as $s) $write([$s->address,$s->status_label,$s->accepted_count,BusinessTime::format($s->last_sent_at),BusinessTime::format($s->unsubscribed_at),BusinessTime::format($s->consented_at)]);
            fclose($out);
        },'notification-recipients.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'no-store, private']);
    }
}
