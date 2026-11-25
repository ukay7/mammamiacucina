<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\InvoicePayment;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoicePayments
{
    public function __construct(
        private HelcimGateway $helcim,
        private PaymentGateway $gateway,
        private GatewayConfiguration $configuration,
        private ReceivableAging $aging,
        private CustomerReceipts $receipts,
    ) {}

    public function invoices(Customer $customer): Builder
    {
        return CustomerInvoice::whereHas('order', fn ($orders) => $orders->where('customer_id', $customer->id))
            ->with(['order.payment', 'order.settlements']);
    }

    public function canPay(Customer $customer): bool
    {
        return $this->configuration->mode() === 'live' && in_array('card', app(CustomerTerms::class)->allowed($customer, 'website'), true)
            && $this->gateway->ready('helcim');
    }

    public function payableBalance(CustomerInvoice $invoice): int
    {
        $payment = $invoice->order?->payment;
        if ($payment && (! $payment->paid_at && ! $payment->released_at || $payment->attention)) return 0;

        return $this->aging->invoiceBalance($invoice);
    }

    public function active(Customer $customer): ?InvoicePayment
    {
        return InvoicePayment::where('customer_id', $customer->id)->whereIn('status', InvoicePayment::ACTIVE)->latest('id')->first();
    }

    public function start(Customer $customer, array $invoiceIds, int $expectedTotal): InvoicePayment
    {
        if (! $this->canPay($customer)) $this->fail('Online card payment is currently unavailable for your account.');

        [$payment, $created] = DB::transaction(function () use ($customer, $invoiceIds, $expectedTotal) {
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
            if ($pending = $this->active($customer)) return [$pending, false];
            if (! $this->canPay($customer)) $this->fail('Online card payment is currently unavailable for your account.');

            $invoices = $this->invoices($customer)->whereKey($invoiceIds)->get();
            abort_unless($invoices->count() === count($invoiceIds), 404);
            $orders = $customer->orders()->whereKey($invoices->pluck('order_id'))->orderBy('id')->lockForUpdate()
                ->with(['payment', 'settlements'])->get()->keyBy('id');
            abort_unless($orders->count() === $invoices->count(), 404);

            $allocations = [];
            foreach ($invoices as $invoice) {
                $order = $orders[$invoice->order_id];
                $invoice->setRelation('order', $order);
                $balance = $this->payableBalance($invoice);
                if ($balance <= 0) $this->fail('One of the invoices is no longer payable. Review the outstanding balance.');
                $allocations[] = ['customer_invoice_id' => $invoice->id, 'order_id' => $order->id,
                    'number' => $order->number, 'invoice' => $invoice->label, 'amount_cents' => $balance];
            }
            $total = array_sum(array_column($allocations, 'amount_cents'));
            if ($total !== $expectedTotal) $this->fail('The selected balances changed. Review the updated amount before paying.');
            if ($total > 999999999) $this->fail('Select fewer invoices for this payment.');

            $reference = (string) Str::uuid();
            $payment = InvoicePayment::create(['customer_id' => $customer->id, 'reference' => $reference,
                'provider_invoice' => 'MMCI'.str_replace('-', '', $reference), 'mode' => 'live',
                'amount_cents' => $total, 'status' => 'initializing', 'expires_at' => now()->addHour()]);
            $payment->allocations()->createMany($allocations);
            $this->record($payment, 'selected', 'Customer selected '.$invoices->count().' invoice(s) for full payment.');

            return [$payment, true];
        }, 3);
        if (! $created) return $payment;

        try {
            $result = $this->helcim->createInvoiceCheckout($payment);
        } catch (\RuntimeException | \Illuminate\Http\Client\ConnectionException) {
            $payment->update(['status' => 'init_failed', 'attention' => 'Could not open the secure payment window. You can start a new attempt.']);
            $this->record($payment, 'init_failed', 'Helcim checkout could not be prepared. No token was shown.');

            return $payment;
        }
        $payment->update(['status' => 'pending', 'checkout_token' => $result['checkoutToken'], 'expires_at' => now()->addHour()]);
        $this->record($payment, 'ready', 'Helcim checkout prepared.');

        return $payment;
    }

    public function sync(InvoicePayment $payment): InvoicePayment
    {
        if (in_array($payment->status, ['refunded', 'init_failed'], true)) return $payment;
        $payment->update(['last_checked_at' => now()]);
        $approved = collect($this->helcim->transactionsForInvoice($payment->mode, $payment->provider_invoice))
            ->filter(fn ($row) => ($row['status'] ?? '') === 'APPROVED');
        $matches = $approved->where('type', 'purchase');
        $refunds = $approved->where('type', 'refund');
        if ($refunds->isNotEmpty() && $matches->count() === 1) {
            return $this->reviewRefunds($payment, $matches->first(), $refunds->all());
        }
        if ($matches->count() === 1) return $this->verified($payment, $matches->first());

        return DB::transaction(function () use ($payment, $matches) {
            $payment = InvoicePayment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === 'paid') return $payment;
            if ($matches->count() > 1) return $this->attention($payment, 'Multiple approved charges were reported. Review these transactions in Helcim.');
            if (in_array($payment->status, ['pending', 'initializing'], true) && $payment->expires_at->copy()->addMinutes(10)->isPast()) {
                $payment->update(['status' => 'expired', 'checkout_token' => null]);
                $this->record($payment, 'expired', 'Checkout expired with no approved Helcim payment found.');
            }

            return $payment;
        }, 3);
    }

    private function verified(InvoicePayment $payment, array $transaction): InvoicePayment
    {
        if (($transaction['invoiceNumber'] ?? '') !== $payment->provider_invoice
            || ($transaction['status'] ?? '') !== 'APPROVED' || ($transaction['type'] ?? '') !== 'purchase') return $payment;

        return DB::transaction(function () use ($payment, $transaction) {
            $customer = Customer::lockForUpdate()->findOrFail($payment->customer_id);
            $payment = InvoicePayment::lockForUpdate()->findOrFail($payment->id);
            $id = (string) ($transaction['transactionId'] ?? '');
            if ($payment->status === 'refunded') return $payment;
            if ($payment->status === 'paid') {
                if ($payment->transaction_id !== $id && ! $payment->attention) {
                    $payment->update(['attention' => 'A second approved charge was reported. Review Helcim transaction '.$id.'.']);
                    $this->record($payment, 'attention', $payment->attention);
                }
                return $payment;
            }
            if (! preg_match('/^\d{1,20}$/D', $id)) return $this->attention($payment, 'Helcim returned an invalid transaction reference.');
            if (InvoicePayment::where('mode', $payment->mode)->where('transaction_id', $id)->whereKeyNot($payment->id)->exists()) {
                return $this->attention($payment, 'This Helcim transaction is already linked to another payment.');
            }
            $payment->transaction_id = $id;
            try {
                $amount = $this->gateway->cents((string) ($transaction['amount'] ?? ''));
            } catch (\RuntimeException) {
                return $this->attention($payment, 'Helcim returned an invalid approved charge amount.');
            }
            $payment->processor_amount_cents = $amount;
            $payment->processor_currency = preg_match('/^[A-Z]{3}$/D', $transaction['currency'] ?? '') ? $transaction['currency'] : null;
            if ($payment->mode !== 'live' || ($transaction['currency'] ?? '') !== 'CAD' || $amount !== $payment->amount_cents) {
                return $this->attention($payment, 'Helcim approved a charge whose currency or amount differs from the invoice selection.');
            }
            $payment->load('allocations');
            $orders = $customer->orders()->whereKey($payment->allocations->pluck('order_id'))->orderBy('id')->lockForUpdate()->get();
            if ($orders->count() !== $payment->allocations->count() || $orders->contains('status', 'cancelled')) {
                return $this->attention($payment, 'The charge was approved, but a selected order is closed by cancellation or unavailable. Review the charge in Helcim.');
            }
            $payment->setRelation('customer', $customer);
            $event = $this->receipts->fromVerifiedInvoicePayment($payment, $id);
            $payment->fill(['status' => 'paid', 'customer_receipt_id' => $event->customer_receipt_id,
                'paid_at' => now(), 'checkout_token' => null, 'attention' => null])->save();
            $this->record($payment, 'paid', 'Helcim transaction '.$id.' verified and allocated to the selected invoices. Receipt '.$event->receipt->number.'.');

            return $payment;
        }, 3);
    }

    private function reviewRefunds(InvoicePayment $payment, array $purchase, array $refunds): InvoicePayment
    {
        return DB::transaction(function () use ($payment, $purchase, $refunds) {
            $payment = InvoicePayment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === 'refunded') return $payment;
            $transaction = (string) ($purchase['transactionId'] ?? '');
            $currency = $purchase['currency'] ?? '';
            $amount = $this->gateway->cents((string) ($purchase['amount'] ?? ''));
            $total = 0;
            foreach ($refunds as $row) {
                if (($row['currency'] ?? '') === $currency) $total += $this->gateway->cents((string) ($row['amount'] ?? ''));
            }
            if (! $payment->receipt && $total === $amount && $amount > 0 && preg_match('/^\d{1,20}$/D', $transaction)
                && preg_match('/^[A-Z]{3}$/D', $currency) && ! InvoicePayment::where('mode', $payment->mode)
                    ->where('transaction_id', $transaction)->whereKeyNot($payment->id)->exists()) {
                $payment->update(['status' => 'refunded', 'transaction_id' => $transaction, 'processor_amount_cents' => $amount,
                    'processor_currency' => $currency, 'attention' => null, 'checkout_token' => null]);
                $this->record($payment, 'refunded', 'Helcim confirmed a full refund before any invoice allocation.');
            } elseif ($payment->status === 'paid') {
                $message = 'Helcim reports a refund on this payment. Review the receipt allocations before recording the adjustment.';
                if ($payment->attention !== $message) {
                    $payment->update(['attention' => $message]);
                    $this->record($payment, 'attention', $message);
                }
            } else {
                return $this->attention($payment, 'Helcim reports a partial or unmatched refund. Review before allocating the payment.');
            }

            return $payment;
        }, 3);
    }

    private function record(InvoicePayment $payment, string $action, string $detail): void
    {
        $payment->events()->create(compact('action', 'detail'));
    }

    private function attention(InvoicePayment $payment, string $message): InvoicePayment
    {
        if ($payment->status !== 'needs_review' || $payment->attention !== $message) $this->record($payment, 'attention', $message);
        $payment->fill(['status' => 'needs_review', 'attention' => $message, 'checkout_token' => null])->save();

        return $payment;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['invoices' => $message]);
    }
}
