<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Customer, NotificationCampaign, NotificationDelivery, NotificationSetting, NotificationSuppression, SmtpSetting};
use App\Services\Notifications\{Audience, Campaigns, Message};
use App\Support\BusinessTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    public function index(Request $r)
    {
        $filters = $r->validate(['q'=>'nullable|string|max:150','status'=>'nullable|in:draft,queued,paused,completed,cancelled','from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d|after_or_equal:from']);
        $campaigns = NotificationCampaign::select(['id','title','subject','channel','status','created_at','scheduled_at','tested_at'])
            ->when($filters['q']??null,fn($q,$s)=>$q->where(fn($q)=>$q->where('title','like','%'.$s.'%')->orWhere('subject','like','%'.$s.'%')->when(preg_match('/^(?:NT-)?0*(\d+)$/i',$s,$m),fn($q)=>$q->orWhere('id',$m[1]??0))))
            ->when($filters['status']??null,fn($q,$v)=>$q->where('status',$v))
            ->when($filters['from']??null,fn($q,$v)=>$q->where('created_at','>=',BusinessTime::start($v)))
            ->when($filters['to']??null,fn($q,$v)=>$q->where('created_at','<',BusinessTime::end($v)))
            ->withCount(['deliveries as recipients_count'=>fn($q)=>$q->where('is_test',false), 'deliveries as accepted_count'=>fn($q)=>$q->where('is_test',false)->whereNotNull('accepted_at'),
                'deliveries as opened_count'=>fn($q)=>$q->where('is_test',false)->whereNotNull('opened_at')])->latest('id')->paginate(20)->withQueryString();
        $worker = NotificationSetting::find(1); $sender = SmtpSetting::find(1);
        return view('admin.notifications.index',compact('campaigns','filters','worker','sender'));
    }
    public function create() { return $this->editor(new NotificationCampaign(['audience'=>[],'audience_mode'=>'selected','customer_type'=>'all','include_alternate'=>true,'track_opens'=>true,'track_clicks'=>true,'revision'=>0])); }
    public function edit(NotificationCampaign $campaign) { abort_unless($campaign->status==='draft',409,'Only drafts can be edited. Duplicate this campaign to prepare a new message.'); return $this->editor($campaign); }
    private function editor(NotificationCampaign $campaign)
    {
        $audience = old('audience',$campaign->audience??[]);
        $audience = is_array($audience) ? array_filter($audience,'is_string') : [];
        $customers = Customer::whereIn('id',collect($audience)->filter(fn($v)=>str_starts_with($v,'customer:'))->map(fn($v)=>(int)substr($v,9)))->get()->keyBy('id');
        $choices = [];
        foreach ($audience as $value) {
            if (str_starts_with($value,'customer:') && ($c=$customers->get((int)substr($value,9)))) $choices[$value]='#'.$c->id.' · '.($c->business_name?$c->business_name.' · ':'').$c->name.' · '.$c->email;
            elseif (str_starts_with($value,'email:')) $choices[$value]=substr($value,6);
        }
        $sender = SmtpSetting::find(1);
        return view('admin.notifications.edit',compact('campaign','choices','sender'));
    }
    private function data(Request $r): array
    {
        $data = $r->validate(['title'=>'required|string|max:150','subject'=>['required','string','max:200','not_regex:/[\r\n]/'], 'body'=>'required|string|max:30000',
            'audience_mode'=>'required|in:selected,all','customer_type'=>'required|in:all,business,individual','audience'=>'nullable|array|max:500','audience.*'=>'required|string|max:270',
            'include_alternate'=>'required|boolean','track_opens'=>'required|boolean','track_clicks'=>'required|boolean','revision'=>'required|integer|min:0']);
        $values=[];
        foreach ($data['audience']??[] as $value) {
            if (preg_match('/^customer:([1-9]\d*)$/D',$value,$m)) {
                if (!Customer::whereKey($m[1])->exists()) throw ValidationException::withMessages(['audience'=>'A selected customer no longer exists.']);
            } elseif (str_starts_with($value,'email:') && filter_var(trim(substr($value,6)),FILTER_VALIDATE_EMAIL) && strlen(trim(substr($value,6)))<=255) $value='email:'.strtolower(trim(substr($value,6)));
            else throw ValidationException::withMessages(['audience'=>'Select a customer or enter a valid email address.']);
            $values[]=$value;
        }
        $data['audience']=array_values(array_unique($values));
        return $data;
    }
    public function store(Request $r, Campaigns $service) { $c=$service->save(null,$this->data($r),$r->user()->id); return redirect()->route('admin.notifications.edit',$c)->with('status','Draft saved. Send a test email before reviewing the bulk send.'); }
    public function update(Request $r, NotificationCampaign $campaign, Campaigns $service) { $service->save($campaign,$this->data($r),$r->user()->id); return redirect()->route('admin.notifications.edit',$campaign)->with('status','Draft saved. Test this version before sending.'); }
    public function customers(Request $r)
    {
        $data=$r->validate(['q'=>'nullable|string|max:150','page'=>'nullable|integer|min:1|max:10000']); $q=trim($data['q']??'');
        $customers=Audience::customers()->when($q!=='',fn($query)=>$query->where(fn($s)=>$s->where('name','like','%'.$q.'%')->orWhere('business_name','like','%'.$q.'%')->orWhere('email','like','%'.$q.'%')->orWhere('id',ltrim($q,'#'))))->orderBy('id')->paginate(30,['id','name','business_name','email']);
        return response()->json(['results'=>$customers->map(fn($c)=>['id'=>'customer:'.$c->id,'text'=>'#'.$c->id.' · '.($c->business_name?$c->business_name.' · ':'').$c->name.' · '.$c->email,'number'=>$c->id,'business'=>$c->business_name,'name'=>$c->name,'email'=>$c->email]),'pagination'=>['more'=>$customers->hasMorePages()]]);
    }
    public function preview(Request $r, Message $message)
    {
        $data=$r->validate(['subject'=>['required','string','max:200','not_regex:/[\r\n]/'],'body'=>'required|string|max:30000']);
        $campaign=new NotificationCampaign($data+['track_opens'=>false,'track_clicks'=>false]);
        $settings=SmtpSetting::find(1)??new SmtpSetting(['from_name'=>'Mamma Mia Cucina','from_address'=>'']);
        return response($message->render($message->snapshot($campaign,$settings))['html'])->header('Content-Security-Policy',"default-src 'none'; style-src 'unsafe-inline'; img-src https: data:; base-uri 'none'; form-action 'none'");
    }
    public function test(Request $r, NotificationCampaign $campaign, Campaigns $service)
    {
        $data=$r->validate(['revision'=>'required|integer|min:0','recipient'=>'required|email:rfc|max:255','test_key'=>'required|uuid']);
        $delivery=$service->test($campaign,(int)$data['revision'],strtolower(trim($data['recipient'])),$data['test_key']);
        if ($delivery->status==='accepted') return back()->with('status','Test accepted by the provider. Check the test inbox and spam folder, then review the bulk send.');
        return back()->withErrors(['notification'=>'Test not yet accepted. '.$delivery->note.' See this campaign’s Test sends report.']);
    }
    public function review(Request $r, NotificationCampaign $campaign, Campaigns $service)
    {
        $data=$r->validate(['revision'=>'required|integer|min:0','scheduled_at'=>'nullable|date_format:Y-m-d\TH:i']);
        $scheduled=BusinessTime::input($data['scheduled_at']??null,'scheduled_at');
        if ($scheduled && ($scheduled->isPast() || $scheduled->gt(now()->addYear()))) throw ValidationException::withMessages(['scheduled_at'=>'Choose a future Toronto time within the next year, or leave blank to send now.']);
        return view('admin.notifications.review',$service->review($campaign,(int)$data['revision'],$r->user()->id,$scheduled));
    }
    public function queue(Request $r, NotificationCampaign $campaign, Campaigns $service)
    {
        $data=$r->validate(['review_token'=>'required|string|size:64','confirmed'=>'required|accepted']);
        $service->queue($campaign,$data['review_token'],$r->user()->id);
        return redirect()->route('admin.notifications.show',$campaign)->with('status','Notification queued. The worker will send each recipient separately.');
    }
    private function recipients(Request $r, NotificationCampaign $campaign)
    {
        $data=$r->validate(['q'=>'nullable|string|max:150','status'=>'nullable|in:queued,retry,sending,accepted,needs_review,cancelled,suppressed','outcome'=>'nullable|in:delivered,delayed,bounced,complained,failed,suppressed', 'engagement'=>'nullable|in:opened,unopened,clicked','tests'=>'nullable|boolean']);
        return $campaign->deliveries()->where('is_test',$r->boolean('tests'))->when($data['q']??null,fn($q,$s)=>$q->where(fn($q)=>$q->where('address','like','%'.$s.'%')->orWhere('recipient_name','like','%'.$s.'%')))
            ->when($data['status']??null,fn($q,$v)=>$q->where('status',$v))->when($data['outcome']??null,fn($q,$v)=>$q->where('outcome',$v))
            ->when(($data['engagement']??'')==='opened',fn($q)=>$q->whereNotNull('opened_at'))
            ->when(($data['engagement']??'')==='unopened',fn($q)=>$q->whereNotNull('accepted_at')->whereNull('opened_at'))
            ->when(($data['engagement']??'')==='clicked',fn($q)=>$q->whereNotNull('clicked_at'));
    }
    public function show(Request $r, NotificationCampaign $campaign)
    {
        $deliveries=$this->recipients($r,$campaign)->select(['id','campaign_id','customer_id','address','recipient_name','source','is_test','status','outcome','attempts','accepted_at','delivered_at','opened_at','open_count','clicked_at','click_count','note'])->orderBy('id')->paginate(30)->withQueryString();
        $base=$campaign->deliveries()->where('is_test',false);
        $stats=['Recipients'=>(clone $base)->count(),'Accepted'=>(clone $base)->whereNotNull('accepted_at')->count(),'Delivered'=>(clone $base)->whereNotNull('delivered_at')->count(),
            'Opened (estimated)'=>(clone $base)->whereNotNull('opened_at')->count(),'Clicked'=>(clone $base)->whereNotNull('clicked_at')->count(),
            'Needs attention'=>(clone $base)->where(fn($q)=>$q->where('status','needs_review')->orWhereIn('outcome',['bounced','complained','failed']))->count()];
        $sender=SmtpSetting::find(1); $worker=NotificationSetting::find(1);
        return view('admin.notifications.show',compact('campaign','deliveries','stats','sender','worker'));
    }
    public function delivery(NotificationCampaign $campaign, NotificationDelivery $delivery)
    {
        abort_unless($delivery->campaign_id===$campaign->id,404);
        $events=$delivery->events()->orderBy('occurred_at')->orderBy('id')->get();
        return view('admin.notifications.delivery',compact('campaign','delivery','events'));
    }
    public function action(Request $r, NotificationCampaign $campaign, Campaigns $service)
    {
        $data=$r->validate(['action'=>'required|in:pause,resume,cancel']); $service->transition($campaign,$data['action']);
        return back()->with('status','Campaign updated. An email already in flight may still finish.');
    }
    public function duplicate(Request $r, NotificationCampaign $campaign, Campaigns $service)
    {
        $data=$campaign->only(['title','subject','body','audience_mode','customer_type','audience','include_alternate','track_opens','track_clicks']);
        $data['title']=mb_substr($data['title'],0,140).' (copy)';
        $copy=$service->save(null,$data,$r->user()->id);
        return redirect()->route('admin.notifications.edit',$copy)->with('status','Copied as a new draft. Review recipients and send a new test.');
    }
    public function destroy(NotificationCampaign $campaign)
    {
        DB::transaction(function() use($campaign) { $c=NotificationCampaign::lockForUpdate()->findOrFail($campaign->id); if (!in_array($c->status,['draft','cancelled','completed'])) Campaigns::fail('Cancel this campaign before archiving it.'); $c->delete(); });
        return redirect()->route('admin.notifications.index')->with('status','Campaign archived. Delivery records are retained.');
    }
    private function csv(string $name, callable $write)
    {
        return response()->streamDownload(function() use($write) {
            $file=fopen('php://output','w'); fwrite($file,"\xEF\xBB\xBF");
            $row=function(array $cells) use($file) { fputcsv($file,array_map(fn($v)=>preg_match('/^[=+@\-\t\r\n]/u',(string)$v)?"'".$v:(string)$v,$cells),',','"',''); };
            $write($row); fclose($file);
        },$name,['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'no-store, private']);
    }
    public function export(Request $r, NotificationCampaign $campaign)
    {
        $query=$this->recipients($r,$campaign);
        return $this->csv($campaign->number.($r->boolean('tests')?'-tests':'-recipients').'.csv',function($row) use($query) {
            $row(['Email','Customer name','Source','Status','Provider outcome','Attempts','Accepted (Toronto)','Delivered (Toronto)','First open (estimated, Toronto)','Open requests','First click (Toronto)','Click requests','Note']);
            foreach($query->orderBy('id')->cursor() as $d) $row([$d->address,$d->recipient_name,$d->source,$d->status,$d->outcome,$d->attempts,BusinessTime::format($d->accepted_at),BusinessTime::format($d->delivered_at),BusinessTime::format($d->opened_at),$d->open_count,BusinessTime::format($d->clicked_at),$d->click_count,$d->note]);
        });
    }
    public function reviewExport(Request $r, NotificationCampaign $campaign)
    {
        $review=$campaign->review; abort_unless($review && $review['user']===$r->user()->id && $review['expires']>=now()->timestamp,403);
        return $this->csv($campaign->number.'-review.csv',function($row) use($review) { $row(['Email','Customer name','Source','Eligibility']); foreach($review['rows'] as $r) $row([$r['address'],$r['recipient_name'],$r['source'],$r['suppression']?'Excluded: '.$r['suppression']:'Eligible']); });
    }
    public function settings()
    {
        $settings=NotificationSetting::find(1)??new NotificationSetting(['per_run'=>20]); $sender=SmtpSetting::find(1);
        return view('admin.notifications.settings',compact('settings','sender'));
    }
    public function saveSettings(Request $r)
    {
        $data=$r->validate(['webhook_secret'=>['nullable','string','max:512','regex:/^whsec_[A-Za-z0-9+\/=]+$/D'],'per_run'=>'required|integer|min:1|max:100']);
        $settings=NotificationSetting::firstOrCreate(['id'=>1]); $values=['per_run'=>$data['per_run']];
        if (!empty($data['webhook_secret'])) $values['webhook_secret']=$data['webhook_secret'];
        $settings->update($values); return back()->with('status','Notification settings saved.');
    }
    public function suppressions(Request $r)
    {
        $data=$r->validate(['q'=>'nullable|string|max:150']);
        $suppressions=NotificationSuppression::when($data['q']??null,fn($q,$v)=>$q->where('address','like','%'.$v.'%'))->latest('id')->paginate(30)->withQueryString();
        return view('admin.notifications.suppressions',compact('suppressions'));
    }
}
