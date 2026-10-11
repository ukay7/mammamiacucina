<?php

namespace App\Console\Commands;

use App\Models\EmailHistory;
use App\Services\EmailOutbox;
use Illuminate\Console\Command;

class DeliverCustomerEmails extends Command
{
    protected $signature = 'emails:deliver {--limit=20}';

    protected $description = 'Deliver queued customer lifecycle emails with safe retries';

    public function handle(EmailOutbox $outbox): int
    {
        $start = microtime(true);
        $count = 0;
        $ids = EmailHistory::whereNotNull('event_key')->whereIn('status', ['queued', 'retry', 'sending'])
            ->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->orderBy('id')->limit(max(1, min(100, (int) $this->option('limit'))))->pluck('id');
        foreach ($ids as $id) {
            try {
                if ($outbox->deliver($id)) {
                    $count++;
                }
            } catch (\Throwable $e) {
                EmailHistory::whereKey($id)->whereIn('status', ['queued', 'retry'])->update(['status' => 'needs_review', 'delivery_note' => 'Unable to prepare email/PDF. Review server configuration before retrying.']);
                $this->error('Email '.$id.' could not be prepared; review the queue and server configuration.');
            }
            if (microtime(true) - $start > 45) {
                break;
            }
        }
        $this->info('Emails accepted: '.$count.'. See Email History for pending/retry/review status.');

        return self::SUCCESS;
    }
}
