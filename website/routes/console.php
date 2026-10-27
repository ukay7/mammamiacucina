<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('suppliers:backfill {--dry-run : Preview mappings without saving}', function () {
    $result = app(\App\Services\SupplierDirectory::class)->backfill((bool) $this->option('dry-run'));
    $this->info(($this->option('dry-run') ? 'Dry run: ' : '')."{$result['linked']} products mapped; {$result['created']} suppliers created; {$result['blank']} blank supplier names skipped.");
})->purpose('Map legacy product supplier names to supplier records without changing stock or prices');

Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping(10);
Schedule::command('emails:deliver')->everyMinute()->withoutOverlapping(10)->runInBackground();

\Illuminate\Support\Facades\Artisan::command('invoices:backfill', function () {
 $count=0;
 \App\Models\Order::whereIn('status',\App\Services\CustomerInvoicing::ELIGIBLE)->whereDoesntHave('invoice')->orderBy('id')->chunkById(100,function($orders)use(&$count){foreach($orders as $order){if(app(\App\Services\CustomerInvoicing::class)->issue($order,true))$count++;}});
 $this->info("Issued {$count} historical invoices. No emails sent.");
})->purpose('Issue missing invoices for existing packed or delivered orders');
