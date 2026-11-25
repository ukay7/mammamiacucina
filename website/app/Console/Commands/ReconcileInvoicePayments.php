<?php

namespace App\Console\Commands;

use App\Models\InvoicePayment;
use App\Services\InvoicePayments;
use Illuminate\Console\Command;

class ReconcileInvoicePayments extends Command
{
    protected $signature = 'invoices:reconcile-payments';
    protected $description = 'Reconcile pending Helcim invoice payments';

    public function handle(InvoicePayments $payments): int
    {
        $pending = InvoicePayment::whereIn('status', ['initializing', 'pending'])
            ->where(fn ($query) => $query->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subMinute()))
            ->orderBy('last_checked_at')->orderBy('id')->limit(30)->get();
        $failed = 0;
        foreach ($pending as $payment) {
            try {
                $payments->sync($payment);
            } catch (\Throwable) {
                $this->warn('Invoice payment '.$payment->reference.' could not be checked.');
                $failed++;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
