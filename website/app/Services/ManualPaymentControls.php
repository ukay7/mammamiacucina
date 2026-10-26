<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManualPaymentControls
{
    public const METHODS = ['cash' => 'Cash', 'etransfer' => 'E-transfer', 'cheque' => 'Cheque', 'card' => 'Card (external terminal)', 'paypal' => 'PayPal (externally verified)', 'credit' => 'Customer Credit'];

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['payment_control' => $message]);
    }

    private function validate(array $data, User $actor, array $rules): array
    {
        abort_unless($actor->isSuper(), 403);
        $data = Validator::make($data, $rules + ['revision' => 'required|integer|min:0',
            'reason' => 'required|string|max:2000', 'confirmed' => 'required|accepted'])->validate();
        $data['reason'] = trim($data['reason']);
        if ($data['reason'] === '') {
            $this->fail('Enter a reason for this payment change.');
        }

        return $data;
    }

    private function current(Order $order, array $data): Order
    {
        $current = Order::lockForUpdate()->findOrFail($order->id);
        $current->assertEditable();
        if ((int) $current->revision !== (int) $data['revision']) {
            $this->fail('This order changed. Reload and review its payments before saving.');
        }

        return $current;
    }

    private function creditCustomer(Order $order): Customer
    {
        if ($order->status === 'cancelled') {
            $this->fail('A cancelled order cannot be moved to Customer Credit.');
        }
        if ($order->final_total_cents === null) {
            $this->fail('Finalize delivery and tax before checking Customer Credit.');
        }
        if (! $order->customer_id) {
            $this->fail('Customer Credit requires a customer account.');
        }

        // Lock before reading payment balances, so credit checks see the latest
        // committed usage after another credit change releases this customer.
        return Customer::lockForUpdate()->findOrFail($order->customer_id);
    }

    public function changeMethod(Order $order, array $data, User $actor): void
    {
        $data = $this->validate($data, $actor, ['method' => ['required', Rule::in(array_keys(self::METHODS))]]);
        DB::transaction(function () use ($order, $data, $actor) {
            $o = $this->current($order, $data);
            $creditCustomer = $data['method'] === 'credit' ? $this->creditCustomer($o) : null;
            if ($o->status === 'cancelled' || ($o->final_total_cents !== null && $o->balance_cents <= 0) || ! in_array($o->payment_status, ['unpaid', 'pending'], true)) {
                $this->fail('Only an unpaid, non-cancelled order with an outstanding balance can change payment method. Use a correction for a recorded manual payment.');
            }
            $before = $o->payment_method;
            if ($before === $data['method']) {
                $this->fail('Select a different payment method.');
            }
            if ($p = $o->payment()->lockForUpdate()->first()) {
                if ($p->paid_at || $p->refunded_cents || $p->attention || $p->manual_resolution || (int) $o->manual_collected_cents > 0 || ! in_array($p->status, ['cancelled', 'expired', 'failed'], true)
                    || DB::table('payment_refunds')->where('payment_id', $p->id)->exists()) {
                    $this->fail('Confirmed online payments are locked. An unpaid checkout must first be confirmed cancelled, expired or failed with no money received or pending refund.');
                }
                $p->update(['superseded_at' => now(), 'checkout_url' => null]);
                $o->unsetRelation('payment');
            }
            $o->manual_collected_cents ??= $o->collected_cents;
            if ($o->manual_collected_cents > 0) {
                $o->original_payment_method ??= $before;
            }
            $oldDue = $o->cheque_due_date;
            if ($data['method'] === 'credit') {
                $o->credit_warning = app(CustomerTerms::class)->check($creditCustomer, $o->source === 'pos' ? 'pos' : 'website', 'credit', max(0, $o->balance_cents));
            } else {
                $o->credit_warning = null;
            }
            $o->payment_method = $data['method'];
            $o->payment_status = 'unpaid';
            $o->cheque_days = $data['method'] === 'cheque' ? ($o->customer?->cheque_days ?: 7) : null;
            $o->cheque_due_date = $data['method'] === 'cheque' ? today()->addDays($o->cheque_days)->toDateString() : null;
            $o->revision++;
            $o->saveQuietly();
            $o->events()->create(['user_id' => $actor->id, 'created_at' => now(), 'description' => 'Super Admin changed unpaid payment method: '.$before.' → '.$o->payment_method.'. Payment remains Unpaid. Cheque due: '.($oldDue ?: 'none').' → '.($o->cheque_due_date ?: 'none').'. Reason: '.$data['reason']]);
        }, 3);
    }

    /** Remaining, unrefunded money that can be corrected without overwriting its source. */
    public function sources(Order $o): array
    {
        $o->loadMissing('payment', 'settlements');
        if ($o->payment) {
            return [];
        }
        $methods = $this->methodBalances($o);
        $sources = [];
        if ($o->collected_cents > 0) {
            $sources[] = ['id' => 'original', 'method' => $o->original_payment_method ?: $o->payment_method, 'amount' => $o->collected_cents, 'reference' => null];
        }
        foreach ($o->settlements as $entry) {
            if ($entry->amount_cents > 0 && in_array($entry->kind, ['payment', 'correction_credit'], true)) {
                $sources[] = ['id' => (string) $entry->id, 'method' => $entry->method, 'amount' => $entry->amount_cents, 'reference' => $entry->reference];
            }
        }
        foreach ($sources as &$source) {
            $used = $o->settlements->filter(fn ($entry) => $entry->amount_cents < 0 && ($source['id'] === 'original'
                ? $entry->related_original : (string) $entry->related_settlement_id === $source['id']))->sum('amount_cents');
            $source['available'] = max(0, min($source['amount'] + $used, $methods[$source['method']] ?? 0, $o->net_received_cents));
            $source['label'] = ($source['id'] === 'original' ? 'Original payment' : 'Entry #'.$source['id']).' · '.(self::METHODS[$source['method']] ?? $source['method']).' · available CAD '.number_format($source['available'] / 100, 2);
        }
        unset($source);

        return array_values(array_filter($sources, fn ($source) => $source['available'] > 0));
    }

    public function methodBalances(Order $o): array
    {
        $method = $o->original_payment_method ?: $o->payment_method;
        $refund = $o->manual_refunded_cents ?? ($o->payment_status === 'refunded' ? $o->collected_cents : 0);
        $balances = [$method => $o->collected_cents - $refund];
        foreach ($o->settlements as $entry) {
            $balances[$entry->method] = ($balances[$entry->method] ?? 0) + $entry->amount_cents;
        }

        return $balances;
    }

    public function adjust(Order $order, array $data, User $actor): void
    {
        $data = $this->validate($data, $actor, ['action' => 'required|in:correct_method,reverse,bounced_cheque',
            'source' => ['required', 'string', 'regex:/^(original|[1-9][0-9]*)$/D'],
            'amount' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/D'],
            'method' => ['nullable', Rule::in(array_keys(self::METHODS))],
            'reference' => 'nullable|string|max:255']);
        DB::transaction(function () use ($order, $data, $actor) {
            $o = $this->current($order, $data);
            $toCredit = $data['action'] === 'correct_method' && ($data['method'] ?? null) === 'credit';
            $creditCustomer = $toCredit ? $this->creditCustomer($o) : null;
            if ($o->payment || $o->paymentAttempts()->whereNotNull('paid_at')->exists()) {
                $this->fail('Gateway payments cannot be corrected or reversed here. Use the gateway reconciliation/refund controls.');
            }
            $source = collect($this->sources($o))->firstWhere('id', $data['source']);
            $amount = OrderAmendment::cents($data['amount']);
            if (! $source || $amount <= 0 || $amount > $source['available']) {
                $this->fail('The amount exceeds the unrefunded, unreversed amount available for this payment. Reload and review payment history.');
            }
            if ($data['action'] === 'bounced_cheque' && $source['method'] !== 'cheque') {
                $this->fail('Only a cheque payment can be marked as bounced.');
            }
            if ($data['action'] === 'correct_method' && (empty($data['method']) || $data['method'] === $source['method'])) {
                $this->fail('Select the actual, different payment method.');
            }
            if ($data['action'] === 'correct_method' && ! in_array($data['method'], ['cash', 'credit'], true) && trim($data['reference'] ?? '') === '') {
                $this->fail('Enter the verified transaction reference for the corrected method.');
            }
            if ($toCredit) {
                $balance = $o->balance_cents;
                $newBalance = max(0, $balance + $amount);
                if ($newBalance === 0) {
                    $this->fail('A Customer Credit correction must leave an outstanding amount on the order.');
                }
                $alreadyOnCredit = $o->payment_method === 'credit' ? max(0, $balance) : 0;
                $o->credit_warning = app(CustomerTerms::class)->check($creditCustomer,
                    $o->source === 'pos' ? 'pos' : 'website', 'credit', max(0, $newBalance - $alreadyOnCredit));
            }
            $o->manual_collected_cents ??= $o->collected_cents;
            $o->manual_refunded_cents ??= $o->payment_status === 'refunded' ? $o->collected_cents : 0;
            $o->original_payment_method ??= $o->payment_method;
            $beforeNet = $o->net_received_cents;
            $beforeStatus = $o->payment_status;
            $kind = $data['action'] === 'correct_method' ? 'correction_debit' : ($data['action'] === 'bounced_cheque' ? 'bounced_cheque' : 'reversal');
            $attributes = ['related_original' => $source['id'] === 'original',
                'related_settlement_id' => $source['id'] === 'original' ? null : (int) $source['id'],
                'note' => ($toCredit ? 'Moved to Customer Credit; no funds received. ' : '').$data['reason'], 'user_id' => $actor->id];
            $debit = $o->settlements()->create($attributes + ['entry_kind' => $kind, 'amount_cents' => -$amount, 'method' => $source['method'], 'reference' => $source['reference']]);
            if ($data['action'] === 'correct_method' && ! $toCredit) {
                $o->settlements()->create(['entry_kind' => 'correction_credit', 'related_settlement_id' => $debit->id,
                    'amount_cents' => $amount, 'method' => $data['method'], 'reference' => $data['reference'] ?? null,
                    'note' => $data['reason'], 'user_id' => $actor->id]);
            }
            $o->unsetRelation('settlements');
            if ($toCredit) {
                // Credit is an arrangement to pay later, not a positive receipt.
                $o->payment_method = 'credit';
                $o->cheque_days = null;
                $o->cheque_due_date = null;
            } elseif ($data['action'] === 'correct_method' && $o->balance_cents <= 0) {
                $effective = array_filter($this->methodBalances($o), fn ($value) => $value > 0);
                if (count($effective) === 1) {
                    $o->payment_method = array_key_first($effective);
                }
            }
            $o->payment_status = $o->net_received_cents >= ($o->final_total_cents ?? PHP_INT_MAX) && $o->status !== 'cancelled' ? 'paid' : 'unpaid';
            $o->revision++;
            // A bookkeeping correction is not a new collection or a customer refund.
            $o->saveQuietly();
            $o->events()->create(['user_id' => $actor->id, 'created_at' => now(), 'description' => 'Super Admin payment '.$data['action'].' on '.($source['id'] === 'original' ? 'original payment' : 'entry #'.$source['id']).': CAD '.number_format($amount / 100, 2).' · '.$source['method'].($data['action'] === 'correct_method' ? ' → '.$data['method'] : ' reversed').'. No money moved by this action; inventory and fulfillment unchanged. Net received CAD '.number_format($beforeNet / 100, 2).' → '.number_format($o->net_received_cents / 100, 2).'; payment status '.$beforeStatus.' → '.$o->payment_status.'. Reason: '.$data['reason']]);
        }, 3);
    }
}
