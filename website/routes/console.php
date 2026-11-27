<?php

use App\Models\GeneralSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('documents:financial-layout {layout? : original or revised}', function () {
    $layout = $this->argument('layout');
    $options = ['original' => 'classic', 'revised' => 'compact'];
    if ($layout !== null && ! isset($options[$layout])) {
        $this->error('Choose original or revised. Omit the argument to see the current layout.');
        return 2;
    }
    if (! Schema::hasColumn('general_settings', 'financial_document_layout')) {
        $this->error('Run the financial document layout migration first.');
        return 1;
    }

    $current = DB::transaction(function () use ($layout, $options) {
        $settings = GeneralSetting::query()->lockForUpdate()->findOrFail(1);
        if ($layout !== null && $settings->financial_document_layout !== $options[$layout]) {
            $settings->update(['financial_document_layout' => $options[$layout], 'revision' => $settings->revision + 1]);
        }

        return $settings->financial_document_layout;
    });

    $this->info('Invoice and payment receipt layout: '.GeneralSetting::FINANCIAL_DOCUMENT_LAYOUTS[$current]);
    return 0;
})->purpose('Show or switch the invoice and order-payment receipt layout');

Artisan::command('suppliers:backfill {--dry-run : Preview mappings without saving}', function () {
    $result = app(\App\Services\SupplierDirectory::class)->backfill((bool) $this->option('dry-run'));
    $this->info(($this->option('dry-run') ? 'Dry run: ' : '')."{$result['linked']} products mapped; {$result['created']} suppliers created; {$result['blank']} blank supplier names skipped.");
})->purpose('Map legacy product supplier names to supplier records without changing stock or prices');

Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping(10);
Schedule::command('invoices:reconcile-payments')->everyMinute()->withoutOverlapping(10)->runInBackground();
Schedule::command('emails:deliver')->everyMinute()->withoutOverlapping(10)->runInBackground();
Schedule::command('notifications:send')->everyMinute()->withoutOverlapping(5)->runInBackground();

\Illuminate\Support\Facades\Artisan::command('invoices:backfill', function () {
 $count=0;
 \App\Models\Order::whereIn('status',\App\Services\CustomerInvoicing::ELIGIBLE)->whereDoesntHave('invoice')->orderBy('id')->chunkById(100,function($orders)use(&$count){foreach($orders as $order){if(app(\App\Services\CustomerInvoicing::class)->issue($order,true))$count++;}});
 $this->info("Issued {$count} historical invoices. No emails sent.");
})->purpose('Issue missing invoices for existing packed or delivered orders');
