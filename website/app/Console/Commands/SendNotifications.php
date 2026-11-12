<?php
namespace App\Console\Commands;

use App\Models\{NotificationCampaign, NotificationDelivery, NotificationEvent, NotificationSetting, SmtpSetting};
use App\Services\Notifications\{Campaigns, Outbox};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, Schema};

class SendNotifications extends Command
{
    protected $signature = 'notifications:send {--limit= : Maximum messages, 1–100 (default from notification settings)} {--dry-run : Show due queue counts without sending or updating anything}';
    protected $description = 'Deliver due notifications with locked claims, bounded batches and safe provider retries';
    public function handle(Outbox $outbox): int
    {
        if (!Schema::hasTable('notification_deliveries')) { $this->error('Run migrations before starting notification delivery.'); return self::FAILURE; }
        $ready = SmtpSetting::find(1)?->ready()??false;
        $query = NotificationDelivery::whereIn('status',['queued','retry','sending'])->where(fn($q)=>$q->whereNull('available_at')->orWhere('available_at','<=',now()))
            ->where(fn($q)=>$q->where('status','!=','sending')->orWhereNull('started_at')->orWhere('started_at','<=',now()->subMinutes(5)))
            ->where(fn($q)=>$q->where('is_test',true)->orWhereHas('campaign',fn($c)=>$c->where('status','queued'))->orWhere('status','sending'));
        if ($this->option('dry-run')) { $this->info('Due deliveries: '.$query->count().'. Sender '.($ready?'ready':'not ready').'. No email sent.'); return self::SUCCESS; }
        $lock = Cache::lock('notifications:worker',120);
        if (!$lock->get()) { $this->info('Another notification worker is running.'); return self::SUCCESS; }
        try {
            $settings = NotificationSetting::firstOrCreate(['id'=>1]);
            $settings->update(['worker_ran_at'=>now()]);
            if (!$ready) { $this->warn('Sender settings are not enabled and tested. Queue retained.'); return self::SUCCESS; }
            $limit = max(1,min(100,(int)($this->option('limit')??$settings->per_run)));
            $started = microtime(true); $accepted = 0;
            foreach ($query->orderBy('id')->limit($limit)->pluck('id') as $id) {
                try { if ($outbox->deliver($id)) $accepted++; }
                catch (\Throwable) { $this->error('Notification '.$id.' could not be processed. Inspect the application and queue before retrying.'); }
                if (microtime(true)-$started>=40) break;
                usleep(550000);
            }
            foreach (NotificationCampaign::where('status','queued')->pluck('id') as $id) app(Campaigns::class)->finish($id);
            // Unmatched webhooks can arrive before the send response; retain them for seven days.
            NotificationEvent::whereNull('delivery_id')->where('created_at','<',now()->subDays(7))->delete();
            $this->info('Notifications accepted: '.$accepted.'. View Notifications for delivery and engagement reports.');
            return self::SUCCESS;
        } finally { $lock->release(); }
    }
}
