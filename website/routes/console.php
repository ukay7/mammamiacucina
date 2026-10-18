<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping(10);
Schedule::command('emails:deliver')->everyMinute()->withoutOverlapping(10)->runInBackground();

\Illuminate\Support\Facades\Artisan::command('invoices:backfill', function () {
 $count=0;
 \App\Models\Order::whereIn('status',\App\Services\CustomerInvoicing::ELIGIBLE)->whereDoesntHave('invoice')->orderBy('id')->chunkById(100,function($orders)use(&$count){foreach($orders as $order){if(app(\App\Services\CustomerInvoicing::class)->issue($order,true))$count++;}});
 $this->info("Issued {$count} historical invoices. No emails sent.");
})->purpose('Issue missing invoices for existing packed or delivered orders');
