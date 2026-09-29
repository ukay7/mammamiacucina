<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\OnlinePayments;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Reconcile online payments and release confirmed expired reservations';

    public function handle(OnlinePayments $payments): int
    {
        $ids = Payment::where(function ($q) {
            $q->whereIn('status', ['creating', 'pending'])->orWhere(function ($q) {
                $q->whereIn('status', ['paid', 'partially_refunded'])->where('updated_at', '>=', now()->subDays(90))
                    ->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subHours(6)));
            });
        })->orderBy('last_checked_at')->limit(100)->pluck('id');
        $failed = 0;
        foreach ($ids as $id) {
            try {
                $payment = Payment::findOrFail($id);
                if (! $payment->provider_order_id && ! $payment->expires_at->isPast()) {
                    $payment = $payments->start($payment);
                }
                $payments->sync($payment, true);
            } catch (\Throwable $e) {
                $failed++;
                Payment::whereKey($id)->update(['attention' => 'Automatic reconciliation failed. Check provider configuration or reconcile from Orders.', 'last_checked_at' => now()]);
                $this->warn('Payment '.$id.' requires reconciliation.');
            }
        }
        $this->info('Checked '.$ids->count().' payment records; '.$failed.' deferred.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
