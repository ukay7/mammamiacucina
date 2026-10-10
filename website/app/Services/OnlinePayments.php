<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OnlinePayments
{
    public function __construct(private PaymentGateway $gateway) {}

    public function initialize(Order $order): Payment
    {
        if ($order->final_total_cents < 50 || $order->final_total_cents > 999999999) {
            throw ValidationException::withMessages(['payment_method' => 'Online payment totals must be between CAD 0.50 and CAD 9,999,999.99.']);
        }

        return Payment::firstOrCreate(['order_id' => $order->id], [
            'reference' => (string) Str::uuid(), 'provider' => $order->payment_method === 'card' ? ($this->gateway->ready('helcim') ? 'helcim' : 'stripe') : 'paypal',
            'mode' => app(GatewayConfiguration::class)->mode(), 'amount_cents' => $order->final_total_cents,
            'currency' => 'CAD', 'expires_at' => now()->addHour(),
        ]);
    }

    private function locked(Payment $payment, callable $operation)
    {
        return DB::transaction(function () use ($payment, $operation) {
            $order = Order::lockForUpdate()->findOrFail($payment->order_id);
            $current = Payment::lockForUpdate()->findOrFail($payment->id);
            $current->setRelation('order', $order);

            return $operation($current, $order);
        }, 3);
    }

    public function start(Payment $payment): Payment
    {
        return $this->locked($payment, function ($p, $order) {
            if ($p->status !== 'creating') {
                return $p;
            }
            if ($p->expires_at->isPast()) {
                $this->release($p, $order, 'Checkout expired');

                return $p;
            }
            if ($p->provider === 'helcim') {
                $data = app(HelcimGateway::class)->create($p);
                $p->update(['provider_order_id' => app(HelcimGateway::class)->invoiceNumber($p), 'checkout_token' => $data['checkoutToken'], 'expires_at' => now()->addHour(), 'status' => 'pending']);
                return $p;
            }
            $data = $this->gateway->create($p);
            $host = parse_url($data['url'], PHP_URL_HOST);
            $allowed = $p->provider === 'stripe' ? ['checkout.stripe.com'] :
                ($p->mode === 'live' ? ['www.paypal.com', 'paypal.com'] : ['www.sandbox.paypal.com', 'sandbox.paypal.com']);
            if (parse_url($data['url'], PHP_URL_SCHEME) !== 'https' || ! in_array($host, $allowed, true)) {
                throw new RuntimeException('Unexpected payment redirect.');
            }
            $p->update(['provider_order_id' => $data['id'], 'checkout_url' => $data['url'], 'status' => 'pending']);

            return $p;
        });
    }

    public function sync(Payment $payment, bool $capture = false, bool $cancel = false): Payment
    {
        return $this->locked($payment, function ($p, $order) use ($capture, $cancel) {
            if ($p->provider === 'helcim' && $p->provider_order_id) {
                return $this->syncHelcim($p, $order, $cancel);
            }
            if (! $p->provider_order_id) {
                if ($p->expires_at->isPast()) {
                    $this->release($p, $order, 'Checkout expired');
                }

                return $p;
            }
            $data = $this->gateway->retrieve($p);
            $p->update(['last_checked_at' => now()]);
            if ($p->provider === 'stripe') {
                $this->validateStripe($p, $data);
                if (($data['payment_status'] ?? '') === 'paid') {
                    $intent = $data['payment_intent'];
                    $transaction = is_array($intent) ? $intent['id'] : $intent;
                    $this->paid($p, $order, $transaction);
                    if (is_array($intent) && is_array($intent['latest_charge'] ?? null)) {
                        $this->refunded($p, $order, (int) ($intent['latest_charge']['amount_refunded'] ?? 0));
                    }
                } elseif (($data['status'] ?? '') === 'expired') {
                    $this->release($p, $order, 'Payment session expired');
                } elseif (($cancel || $p->expires_at->isPast()) && ($data['status'] ?? '') === 'open') {
                    $this->gateway->expire($p);
                    // Re-fetch after expiration; never release solely on a browser cancel redirect.
                    $data = $this->gateway->retrieve($p);
                    $this->validateStripe($p, $data);
                    if (($data['payment_status'] ?? '') === 'paid') {
                        $intent = $data['payment_intent'];
                        $this->paid($p, $order, is_array($intent) ? $intent['id'] : $intent);
                    } elseif (($data['status'] ?? '') === 'expired') {
                        $this->release($p, $order, 'Payment cancelled or expired');
                    }
                }
            } else {
                $this->validatePaypal($p, $data);
                if (($data['status'] ?? '') === 'APPROVED' && $capture && ! $cancel && ! $p->expires_at->isPast() && ! $p->released_at && ! $p->paid_at) {
                    $this->gateway->capture($p);
                    $data = $this->gateway->retrieve($p);
                    $this->validatePaypal($p, $data);
                }
                $captures = $data['purchase_units'][0]['payments']['captures'] ?? [];
                if (count($captures) > 1) {
                    throw new RuntimeException('Unexpected multiple captures; review required.');
                }
                $captureData = $captures[0] ?? null;
                if ($captureData) {
                    $details = $this->gateway->captureDetails($p, $captureData['id']);
                    if (($details['amount']['currency_code'] ?? '') !== 'CAD' || $this->gateway->cents($details['amount']['value'] ?? '') !== $p->amount_cents) {
                        throw new RuntimeException('Payment amount mismatch.');
                    }
                    if (in_array($details['status'] ?? '', ['COMPLETED', 'REFUNDED', 'PARTIALLY_REFUNDED'], true)) {
                        $this->paid($p, $order, $details['id']);
                        if ($details['status'] === 'REFUNDED') {
                            $this->refunded($p, $order, $p->amount_cents);
                        }
                    } elseif (in_array($details['status'] ?? '', ['DECLINED', 'FAILED'], true)) {
                        $this->release($p, $order, 'Payment declined');
                    }
                    // A PENDING capture may still settle: hold stock until confirmed, never assume failure.
                } elseif (($cancel || $p->expires_at->isPast()) && in_array($data['status'] ?? '', ['CREATED', 'SAVED', 'PAYER_ACTION_REQUIRED', 'APPROVED', 'VOIDED'], true)) {
                    $this->release($p, $order, 'Payment cancelled or expired');
                }
            }

            if ($p->attention === 'Automatic reconciliation failed. Check provider configuration or reconcile from Orders.') {
                $p->update(['attention' => null]);
            }

            return $p->fresh();
        });
    }

    private function syncHelcim(Payment $p, Order $order, bool $cancel): Payment
    {
        $rows = app(HelcimGateway::class)->transactions($p);
        $p->update(['last_checked_at' => now()]);
        $purchases = [];
        $refunds = [];
        foreach ($rows as $row) {
            if (($row['status'] ?? '') === 'DECLINED') continue;
            if (($row['status'] ?? '') !== 'APPROVED' || ($row['currency'] ?? '') !== $p->currency ||
                ! preg_match('/^[0-9]{1,20}$/D', (string) ($row['transactionId'] ?? ''))) {
                throw new RuntimeException('Helcim transaction requires review.');
            }
            if (($row['type'] ?? '') === 'purchase') $purchases[] = $row;
            elseif (($row['type'] ?? '') === 'refund') $refunds[] = $row;
            else throw new RuntimeException('Unexpected Helcim transaction type; review required.');
        }
        if (count($purchases) > 1) {
            $p->update(['attention' => 'Multiple approved payments found for this invoice. Review in Helcim before fulfillment.']);
            $order->update(['status' => 'payment_review']);
            return $p->fresh();
        }
        if ($purchase = $purchases[0] ?? null) {
            if ($this->gateway->cents((string) $purchase['amount']) !== $p->amount_cents) {
                throw new RuntimeException('Helcim payment amount mismatch.');
            }
            $this->paid($p, $order, (string) $purchase['transactionId']);
            foreach ($refunds as $refund) {
                $amount = $this->gateway->cents((string) ($refund['amount'] ?? ''));
                $key = ['payment_id' => $p->id, 'provider_refund_id' => (string) $refund['transactionId']];
                DB::table('payment_refunds')->insertOrIgnore($key + ['amount_cents' => $amount, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('payment_refunds')->where($key)->update(['amount_cents' => $amount, 'status' => 'completed', 'updated_at' => now()]);
            }
            $total = (int) DB::table('payment_refunds')->where('payment_id', $p->id)->where('status', 'completed')->sum('amount_cents');
            $this->refunded($p, $order, $total);
        } elseif ($p->paid_at || $refunds) {
            throw new RuntimeException('Previously confirmed Helcim payment is unavailable.');
        } elseif ($p->expires_at->copy()->addMinutes(10)->isPast()) {
            // No cancellation API exists for the modal. Wait for token expiry and a settlement grace period,
            // then query the provider before releasing stock. Late callbacks still route to payment_review.
            $this->release($p, $order, 'Card checkout cancelled or expired');
        } elseif ($cancel) {
            $p->update(['cancel_requested_at' => $p->cancel_requested_at ?? now(),
                'attention' => 'Cancellation requested. Stock stays reserved until the card session expires and Helcim confirms no payment.']);
        }
        if ($p->attention === 'Automatic reconciliation failed. Check provider configuration or reconcile from Orders.') {
            $p->update(['attention' => null]);
        }
        return $p->fresh();
    }

    private function validateStripe(Payment $p, array $data): void
    {
        if (($data['id'] ?? '') !== $p->provider_order_id || ($data['client_reference_id'] ?? '') !== $p->reference ||
            (int) ($data['amount_total'] ?? -1) !== $p->amount_cents || strtolower($data['currency'] ?? '') !== 'cad' ||
            ($data['livemode'] ?? null) !== ($p->mode === 'live')) {
            throw new RuntimeException('Stripe payment verification mismatch.');
        }
    }

    private function validatePaypal(Payment $p, array $data): void
    {
        $units = $data['purchase_units'] ?? [];
        if (($data['id'] ?? '') !== $p->provider_order_id || count($units) !== 1 ||
            ($units[0]['custom_id'] ?? '') !== $p->reference || ($units[0]['amount']['currency_code'] ?? '') !== 'CAD' ||
            $this->gateway->cents($units[0]['amount']['value'] ?? '') !== $p->amount_cents) {
            throw new RuntimeException('PayPal payment verification mismatch.');
        }
    }

    private function paid(Payment $p, Order $order, string $transaction): void
    {
        if ($p->transaction_id && $p->transaction_id !== $transaction) {
            throw new RuntimeException('Unexpected payment transaction.');
        }
        if ($p->paid_at) {
            return;
        }
        $late = (bool) $p->released_at || $order->status === 'cancelled';
        $p->update(['transaction_id' => $transaction, 'status' => 'paid', 'paid_at' => now(), 'attention' => $late ? 'Payment arrived after stock was released. Refund or review before fulfilling.' : null]);
        $order->update(['payment_status' => 'paid', 'paid_at' => now(), 'status' => $late ? 'payment_review' : 'warehouse_pending', 'warehouse_round' => $late ? $order->warehouse_round : 1, 'warehouse_sent_at' => $late ? $order->warehouse_sent_at : now(), 'revision' => $order->revision + 1]);
        $order->events()->create(['description' => $late ? 'Late gateway payment confirmed; stock is not reserved. Staff review required.' : 'Payment confirmed by '.ucfirst($p->provider).'; automatically sent to warehouse.', 'created_at' => now()]);
    }

    private function restock(Order $order, string $reason): void
    {
        foreach ($order->items()->orderBy('product_id')->get() as $item) {
            if (! $item->stock_deducted || ! $item->product_id) {
                continue;
            }
            $inventory = Inventory::where('product_id', $item->product_id)->lockForUpdate()->first();
            if (! $inventory || $inventory->quantity_on_hand === null) {
                throw new RuntimeException('Cannot release stock: inventory configuration changed.');
            }
            $before = $inventory->quantity_on_hand;
            $after = ((int) round((float) $before * 1000) + $item->quantity * 1000) / 1000;
            if ($after > 999999999) {
                throw new RuntimeException('Stock release exceeds inventory limit.');
            }
            $inventory->update(['quantity_on_hand' => $after]);
            InventoryMovement::create(['product_id' => $item->product_id, 'quantity_change' => $item->quantity,
                'quantity_before' => $before, 'quantity_after' => $after, 'reason' => $reason.' '.$order->number, 'created_by' => null, 'created_at' => now()]);
            $item->update(['stock_deducted' => false]);
        }
    }

    private function release(Payment $p, Order $order, string $reason): void
    {
        if ($p->paid_at || $p->released_at) {
            return;
        }
        $this->restock($order, $reason);
        $p->update(['status' => 'expired', 'released_at' => now(), 'checkout_url' => null]);
        $order->update(['status' => 'cancelled', 'payment_status' => 'unpaid', 'cancelled_at' => now(), 'revision' => $order->revision + 1]);
        $order->events()->create(['description' => $reason.'; reserved stock released.', 'created_at' => now()]);
    }

    private function refunded(Payment $p, Order $order, int $amount): void
    {
        if ($amount < 0 || $amount > $p->amount_cents) {
            throw new RuntimeException('Refund amount mismatch.');
        }
        if ($amount <= $p->refunded_cents) {
            return;
        }
        $full = $amount === $p->amount_cents;
        $p->update(['refunded_cents' => $amount, 'attention' => null, 'status' => $full ? 'refunded' : 'partially_refunded']);
        // Refunds do not imply physical returns. Only unfulfilled orders are automatically restocked.
        if ($full && ! in_array($order->status, ['delivered', 'out_for_delivery', 'completed'], true)) {
            $this->restock($order, 'Refunded order');
            $p->update(['released_at' => $p->released_at ?? now()]);
            $order->status = 'cancelled';
            $order->cancelled_at = now();
        }
        $order->payment_status = $full ? 'refunded' : 'partially_refunded';
        $order->revision++;
        $order->save();
        $order->events()->create(['description' => 'Gateway confirmed refund total CAD '.number_format($amount / 100, 2).'.', 'created_at' => now()]);
    }

    public function refund(Payment $payment, int $userId, ?int $expectedCents = null, ?int $refundCents = null): void
    {
        $this->sync($payment);
        $this->locked($payment, function ($p, $order) use ($userId, $expectedCents, $refundCents) {
            if ($p->status === 'refunded') {
                return;
            }
            if (! $p->paid_at || ! $p->transaction_id) {
                throw new RuntimeException('No confirmed gateway payment to refund.');
            }
            if ($expectedCents !== null && $expectedCents !== $p->amount_cents - $p->refunded_cents) {
                throw new RuntimeException('Refund amount changed. Reload the order.');
            }
            if ($refundCents !== null && ($p->provider !== 'helcim' || $refundCents < 1 || $refundCents > $p->amount_cents - $p->refunded_cents)) {
                throw new RuntimeException('Invalid refund amount.');
            }
            $requested = $refundCents ?? ($p->amount_cents - $p->refunded_cents);
            $result = $p->provider === 'helcim' ? app(HelcimGateway::class)->refund($p, $requested) : $this->gateway->refund($p);
            DB::table('payment_refunds')->updateOrInsert(['payment_id' => $p->id, 'provider_refund_id' => $result['id']],
                ['amount_cents' => $result['amount'], 'status' => $result['status'], 'created_at' => now(), 'updated_at' => now()]);
            if (in_array($result['status'], ['succeeded', 'completed'], true)) {
                if ($result['amount'] !== $requested) {
                    throw new RuntimeException('Unexpected refund amount; reconcile with provider.');
                }
                $this->refunded($p, $order, $p->refunded_cents + $result['amount']);
            } else {
                $p->update(['attention' => 'Refund '.$result['status'].'. Reconcile with provider before retrying or fulfilling.']);
            }
            $order->events()->create(['user_id' => $userId, 'description' => 'Gateway refund requested: '.$result['status'], 'created_at' => now()]);
        });
        $this->sync($payment);
    }

    public function paypalRefund(Payment $payment, array $resource): void
    {
        $this->locked($payment, function ($p, $order) use ($resource) {
            if (($resource['status'] ?? '') !== 'COMPLETED') {
                return;
            }
            if (($resource['amount']['currency_code'] ?? '') !== 'CAD') {
                throw new RuntimeException('Refund currency mismatch.');
            }
            $amount = $this->gateway->cents($resource['amount']['value']);
            DB::table('payment_refunds')->updateOrInsert(['payment_id' => $p->id, 'provider_refund_id' => $resource['id']],
                ['amount_cents' => $amount, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
            $total = (int) DB::table('payment_refunds')->where('payment_id', $p->id)->whereIn('status', ['completed', 'succeeded'])->sum('amount_cents');
            $this->refunded($p, $order, max($p->refunded_cents, $total));
        });
    }
}
