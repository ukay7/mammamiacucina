<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptEvent;
use App\Models\CustomerReceiptLine;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerReceipts
{
    private function fail(string $message, string $field = 'payment'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->isSuper(), 403);
    }

    private function money(int $cents): string
    {
        return 'CAD '.number_format($cents / 100, 2);
    }

    private function input(array $input): array
    {
        $data = Validator::make($input, [
            'lines' => 'required|array|min:1|max:10', 'lines.*' => 'required|array', 'lines.*.key' => 'required|uuid|distinct',
            'lines.*.method' => ['required', Rule::in(array_keys(CustomerReceiptLine::METHODS))],
            'lines.*.amount' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/D'],
            'lines.*.payment_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'lines.*.reference' => 'nullable|string|max:150', 'lines.*.bank' => 'nullable|string|max:150',
            'lines.*.cheque_date' => 'nullable|date_format:Y-m-d', 'lines.*.note' => 'nullable|string|max:2000',
            'lines.*.verified' => 'nullable|boolean', 'lines.*.proof_hash' => 'nullable|string|size:64',
            'lines.*.proof_name' => 'nullable|string|max:255',
        ])->validate();
        foreach ($data['lines'] as $i => &$line) {
            $line['amount_cents'] = OrderAmendment::cents($line['amount']);
            if ($line['amount_cents'] <= 0) {
                $this->fail('Enter a payment amount greater than zero.', "lines.$i.amount");
            }
            $line['reference'] = trim($line['reference'] ?? '');
            $line['note'] = trim($line['note'] ?? '');
            $line['bank'] = trim($line['bank'] ?? '');
            if ($line['method'] !== 'cash' && $line['reference'] === '') {
                $this->fail('Enter the cheque number or verified transaction reference.', "lines.$i.reference");
            }
            if ($line['method'] === 'cheque' && empty($line['cheque_date'])) {
                $this->fail('Enter the cheque date.', "lines.$i.cheque_date");
            }
            if ($line['method'] !== 'cheque' && ! ($line['verified'] ?? false)) {
                $this->fail('Confirm that the funds were actually received. A screenshot alone is not confirmation.', "lines.$i.verified");
            }
            if ($line['method'] === 'etransfer' && empty($line['proof_hash'])) {
                $this->fail('Attach the e-transfer supporting image or PDF.', "lines.$i.proof");
            }
            if ($line['method'] !== 'cheque') {
                $line['bank'] = '';
                $line['cheque_date'] = null;
            }
            $line['proof_hash'] ??= null;
            $line['proof_name'] ??= null;
            unset($line['amount'], $line['verified']);
        }
        unset($line);

        return $data['lines'];
    }

    private function orders(Customer $customer, bool $lock = false): Collection
    {
        $query = $customer->orders()->where('currency', 'CAD')->where('status', '!=', 'cancelled')
            ->whereNotNull('delivery_cents')->whereNotNull('tax_cents')->whereDoesntHave('payment')
            ->whereDoesntHave('paymentAttempts', fn ($q) => $q->whereNotNull('paid_at'))->with('settlements')->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->filter(fn ($o) => $o->balance_cents > 0)->sortBy([['ordered_at', 'asc'], ['id', 'asc']])->values();
    }

    public function account(Customer $customer, ?int $exceptPendingLine = null, bool $lock = false): array
    {
        $orders = $this->orders($customer, $lock);
        $pending = (int) CustomerReceiptLine::where('customer_id', $customer->id)->where('status', 'awaiting_clearance')
            ->when($exceptPendingLine, fn ($q) => $q->where('id', '!=', $exceptPendingLine))->sum('amount_cents');
        $eligible = (int) $orders->sum(fn ($o) => $o->balance_cents);
        $invoices = CustomerInvoice::whereIn('order_id', $orders->modelKeys())->get()->keyBy('order_id');

        return ['outstanding_cents' => app(CustomerInvoicing::class)->balances($customer)['pending'],
            'eligible_cents' => $eligible, 'pending_cheques_cents' => $pending, 'maximum_cents' => max(0, $eligible - $pending),
            'unpaid_orders' => $orders->count(), 'overdue_orders' => $orders->filter(fn ($o) => $o->cheque_due_date && $o->cheque_due_date < today()->toDateString())->count(),
            'orders' => $orders->map(fn ($o) => ['id' => $o->id, 'number' => $o->number, 'invoice' => $invoices->get($o->id)?->label,
                'date' => $o->ordered_at->toDateString(), 'due_date' => $o->cheque_due_date, 'balance_cents' => $o->balance_cents,
                'revision' => (int) $o->revision])->all()];
    }

    private function customerSnapshot(Customer $customer): array
    {
        return $customer->only(['id', 'name', 'business_name', 'email', 'phone', 'business_email', 'business_phone', 'address', 'city', 'province', 'postal_code', 'country']);
    }

    private function sign(array $data): string
    {
        return hash_hmac('sha256', json_encode($data, JSON_THROW_ON_ERROR), config('app.key'));
    }

    private function plan(Customer $customer, array $lines, bool $lock = false, ?int $clearingLine = null): array
    {
        // One instrument can produce several order allocations, but must not be
        // recorded as another active payment line on this customer's account.
        $references = [];
        foreach ($lines as $line) {
            if ($line['method'] === 'cash') {
                continue;
            }
            $reference = mb_strtolower(trim($line['reference']));
            $key = $line['method'].'|'.$reference;
            $duplicate = CustomerReceiptLine::where('customer_id', $customer->id)->where('method', $line['method'])
                ->whereIn('status', ['confirmed', 'cleared', 'awaiting_clearance'])
                ->when($clearingLine, fn ($q) => $q->where('id', '!=', $clearingLine))
                ->whereRaw('LOWER(TRIM(reference)) = ?', [$reference])->exists();
            if (isset($references[$key]) || $duplicate) {
                $this->fail('This payment reference is already recorded for this customer. Review the existing receipt before recording it again.');
            }
            $references[$key] = true;
        }
        $account = $this->account($customer, $clearingLine, $lock);
        $total = array_sum(array_column($lines, 'amount_cents'));
        if ($total > $account['maximum_cents']) {
            $this->fail('Payment total '.$this->money($total).' exceeds the maximum '.$this->money($account['maximum_cents']).'. Enter this maximum or less. Eligible outstanding: '.$this->money($account['eligible_cents']).'; other cheques awaiting clearance: '.$this->money($account['pending_cheques_cents']).'. Customer advances are not supported.');
        }
        $remaining = array_column($account['orders'], 'balance_cents', 'id');
        $rows = [];
        $confirmed = 0;
        $pending = 0;
        // Confirmed funds are applied first. Pending cheque rows are proposals only;
        // their real allocation is reviewed again when bank clearance is recorded.
        $ordered = array_merge(array_filter($lines, fn ($l) => $clearingLine || $l['method'] !== 'cheque'),
            $clearingLine ? [] : array_filter($lines, fn ($l) => $l['method'] === 'cheque'));
        foreach ($ordered as $line) {
            $awaiting = ! $clearingLine && $line['method'] === 'cheque';
            $amount = $line['amount_cents'];
            if ($awaiting) {
                $pending += $amount;
            } else {
                $confirmed += $amount;
            }
            foreach ($account['orders'] as $order) {
                $applied = min($amount, $remaining[$order['id']]);
                if ($applied <= 0) {
                    continue;
                }
                $rows[$order['id']] ??= $order + ['allocations' => [], 'confirmed_cents' => 0, 'pending_cents' => 0];
                $rows[$order['id']]['allocations'][] = ['key' => $line['key'], 'method' => $line['method'], 'reference' => $line['reference'], 'amount_cents' => $applied, 'pending' => $awaiting];
                $rows[$order['id']][$awaiting ? 'pending_cents' : 'confirmed_cents'] += $applied;
                $remaining[$order['id']] -= $applied;
                $amount -= $applied;
                if ($amount === 0) {
                    break;
                }
            }
        }
        foreach ($rows as &$row) {
            $row['after_confirmed_cents'] = $row['balance_cents'] - $row['confirmed_cents'];
            $row['after_clearance_cents'] = $row['after_confirmed_cents'] - $row['pending_cents'];
        }
        unset($row);

        return ['customer' => $this->customerSnapshot($customer), 'account' => $account, 'lines' => $lines, 'orders' => array_values($rows),
            'total_cents' => $total, 'confirmed_cents' => $confirmed, 'pending_cents' => $pending,
            'outstanding_after_confirmed_cents' => max(0, $account['outstanding_cents'] - $confirmed)];
    }

    public function preview(Customer $customer, array $input, User $actor): array
    {
        $this->authorize($actor);
        $lines = $this->input($input);

        return DB::transaction(function () use ($customer, $lines) {
            $plan = $this->plan($customer->fresh(), $lines);

            return $plan + ['preview_hash' => $this->sign($plan)];
        });
    }

    public function create(Customer $customer, array $input, User $actor, ?callable $storeProofs = null): CustomerReceipt
    {
        $this->authorize($actor);
        $lines = $this->input($input);
        $confirmation = Validator::make($input, ['request_key' => 'required|uuid', 'preview_hash' => 'required|string|size:64', 'confirmed' => 'accepted'])->validate();

        return DB::transaction(function () use ($customer, $lines, $confirmation, $actor, $storeProofs) {
            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            if ($existing = CustomerReceipt::where('request_key', $confirmation['request_key'])->first()) {
                if ($existing->customer_id !== $customer->id || $existing->created_by !== $actor->id || ! hash_equals($existing->request_hash, $confirmation['preview_hash'])) {
                    $this->fail('This request has already been used. Reload before recording another payment.');
                }

                return $existing;
            }
            $plan = $this->plan($customer, $lines, true);
            if (! hash_equals($this->sign($plan), $confirmation['preview_hash'])) {
                $this->fail('Customer details, balances, attachments or payments changed. Review the allocation again.');
            }
            $paths = $storeProofs ? $storeProofs() : [];
            $receipt = CustomerReceipt::create(['customer_id' => $customer->id, 'created_by' => $actor->id, 'customer_snapshot' => $plan['customer'],
                'total_cents' => $plan['total_cents'], 'request_key' => $confirmation['request_key'], 'request_hash' => $confirmation['preview_hash']]);
            $receipt->update(['number' => 'CR-'.str_pad((string) $receipt->id, 6, '0', STR_PAD_LEFT)]);
            foreach ($lines as $entry) {
                $planned = [];
                foreach ($plan['orders'] as $row) {
                    foreach ($row['allocations'] as $allocation) {
                        if ($allocation['key'] === $entry['key']) {
                            $planned[] = ['order_id' => $row['id'], 'number' => $row['number'], 'invoice' => $row['invoice'], 'amount_cents' => $allocation['amount_cents']];
                        }
                    }
                }
                $line = $receipt->lines()->create(['customer_id' => $customer->id, 'client_key' => $entry['key'], 'method' => $entry['method'],
                    'amount_cents' => $entry['amount_cents'], 'payment_date' => $entry['payment_date'], 'reference' => $entry['reference'] ?: null,
                    'bank' => $entry['bank'] ?: null, 'cheque_date' => $entry['cheque_date'] ?? null, 'note' => $entry['note'],
                    'proof_path' => $paths[$entry['key']] ?? null, 'proof_name' => $entry['proof_name'], 'proof_hash' => $entry['proof_hash'],
                    'status' => $entry['method'] === 'cheque' ? 'awaiting_clearance' : 'confirmed', 'planned_allocations' => $planned]);
                if ($line->status === 'confirmed') {
                    $this->post($line, $planned, $entry['payment_date'], $actor, $receipt->number.' - verified customer payment');
                }
            }
            $this->audit($receipt, null, $actor, 'received', 'Customer payment recorded. Only confirmed funds were allocated; cheques await bank clearance.', null,
                ['before_outstanding_cents' => $plan['account']['outstanding_cents'], 'allocations' => $plan['orders']]);

            return $receipt;
        }, 3);
    }

    private function post(CustomerReceiptLine $line, array $allocations, string $receivedOn, User $actor, string $note): void
    {
        foreach ($allocations as $allocation) {
            $order = Order::whereKey($allocation['order_id'])->lockForUpdate()->firstOrFail();
            $before = $order->balance_cents;
            $order->manual_collected_cents ??= $order->collected_cents;
            $order->original_payment_method ??= $order->payment_method;
            $order->settlements()->create(['customer_receipt_line_id' => $line->id, 'amount_cents' => $allocation['amount_cents'],
                'entry_kind' => 'payment', 'method' => $line->method, 'reference' => $line->reference, 'received_on' => $receivedOn,
                'note' => $note.($line->note ? '. '.$line->note : ''), 'user_id' => $actor->id]);
            $order->unsetRelation('settlements');
            $order->payment_status = $order->balance_cents <= 0 ? 'paid' : 'unpaid';
            if ($order->payment_status === 'paid') {
                $order->paid_at ??= now();
            }
            $order->revision++;
            // Recording an account collection does not issue invoices, send emails,
            // move inventory, or advance fulfillment through Order model hooks.
            $order->saveQuietly();
            $order->events()->create(['user_id' => $actor->id, 'created_at' => now(), 'description' => $note.'. Receipt line #'.$line->id.' · '.$line->method.' · '.$this->money($allocation['amount_cents']).'. Balance '.$this->money($before).' → '.$this->money($order->balance_cents).'. Inventory and fulfillment unchanged.']);
        }
    }

    private function actionInput(CustomerReceiptLine $line, array $input): array
    {
        $data = Validator::make($input, ['action' => 'required|in:clear,reverse,bounce,cancel', 'revision' => 'required|integer|min:0',
            'reason' => 'required|string|max:2000', 'reference' => 'nullable|string|max:150',
            'payment_date' => 'required|date_format:Y-m-d|before_or_equal:today'])->validate();
        $data['reason'] = trim($data['reason']);
        $data['reference'] = trim($data['reference'] ?? '');
        if ($data['reason'] === '') {
            $this->fail('Enter a reason for this action.');
        }
        $earliest = $line->cleared_on ?? $line->payment_date;
        if ($data['payment_date'] < $earliest->toDateString()) {
            $this->fail('The action date cannot be before the recorded receipt or clearance date.');
        }
        if ($data['action'] === 'clear' && ($line->status !== 'awaiting_clearance' || $line->method !== 'cheque')) {
            $this->fail('Only a cheque awaiting clearance can be cleared.');
        }
        if ($data['action'] === 'clear' && ($data['reference'] === '' || $data['payment_date'] < $line->payment_date->toDateString() || $data['payment_date'] < $line->cheque_date->toDateString())) {
            $this->fail('Enter the bank clearance reference and a clearance date on or after the receipt and cheque dates.');
        }
        if ($data['action'] === 'cancel' && $line->status !== 'awaiting_clearance') {
            $this->fail('Only an uncleared cheque can be cancelled. Use reversal for confirmed funds.');
        }
        if ($data['action'] === 'bounce' && ($line->method !== 'cheque' || ! in_array($line->status, ['awaiting_clearance', 'cleared'], true))) {
            $this->fail('Only an awaiting-clearance or cleared cheque can be marked bounced.');
        }
        if ($data['action'] === 'reverse' && ! in_array($line->status, ['confirmed', 'cleared'], true)) {
            $this->fail('Only confirmed funds can be reversed.');
        }
        if ((int) $line->receipt->revision !== (int) $data['revision']) {
            $this->fail('This receipt changed. Reload before proceeding.');
        }

        return $data;
    }

    private function actionPlan(CustomerReceiptLine $line, array $data, bool $lock = false): array
    {
        $receipt = $line->receipt;
        if ($data['action'] === 'clear') {
            $entry = ['key' => $line->client_key, 'method' => 'cheque', 'amount_cents' => $line->amount_cents, 'reference' => $line->reference,
                'payment_date' => $data['payment_date'], 'cheque_date' => $line->cheque_date->toDateString(), 'bank' => $line->bank, 'note' => $line->note, 'proof_hash' => $line->proof_hash, 'proof_name' => $line->proof_name];
            $plan = $this->plan($receipt->customer, [$entry], $lock, $line->id);
        } else {
            $entries = $line->allocations()->where('amount_cents', '>', 0)->orderBy('order_id')->get();
            $orders = Order::withTrashed()->whereIn('id', $entries->pluck('order_id'))->with('settlements')->orderBy('id');
            if ($lock) {
                $orders->lockForUpdate();
            }
            $orders = $orders->get()->keyBy('id');
            $rows = [];
            foreach ($entries as $entry) {
                $order = $orders->get($entry->order_id);
                if (! $order || $order->trashed()) {
                    $this->fail('An allocated order is deleted or unavailable. Resolve its financial history before changing this receipt.');
                }
                $used = -(int) $order->settlements->where('related_settlement_id', $entry->id)->where('amount_cents', '<', 0)->sum('amount_cents');
                $methods = app(ManualPaymentControls::class)->methodBalances($order);
                if ($used || $order->net_received_cents < $entry->amount_cents || ($methods[$entry->method] ?? 0) < $entry->amount_cents) {
                    $this->fail('An allocation was already refunded or corrected. Review the order payment history before reversing this receipt.');
                }
                $rows[] = ['id' => $order->id, 'number' => $order->number, 'revision' => (int) $order->revision,
                    'settlement_id' => $entry->id, 'amount_cents' => $entry->amount_cents, 'balance_cents' => $order->balance_cents,
                    'after_cents' => $order->balance_cents + $entry->amount_cents];
            }
            $plan = ['orders' => $rows, 'amount_cents' => $line->amount_cents];
        }

        return $plan + ['line_id' => $line->id, 'receipt_revision' => (int) $receipt->revision, 'line_status' => $line->status, 'action_data' => $data];
    }

    public function previewAction(CustomerReceiptLine $line, array $input, User $actor): array
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($line, $input) {
            $line = $line->fresh(['receipt.customer']);
            $data = $this->actionInput($line, $input);
            $plan = $this->actionPlan($line, $data);

            return $plan + ['preview_hash' => $this->sign($plan)];
        });
    }

    public function act(CustomerReceiptLine $line, array $input, User $actor): CustomerReceipt
    {
        $this->authorize($actor);
        $confirmation = Validator::make($input, ['preview_hash' => 'required|string|size:64', 'confirmed' => 'accepted'])->validate();

        return DB::transaction(function () use ($line, $input, $actor, $confirmation) {
            Customer::whereKey($line->customer_id)->lockForUpdate()->firstOrFail();
            $receipt = CustomerReceipt::whereKey($line->customer_receipt_id)->lockForUpdate()->firstOrFail();
            $line = CustomerReceiptLine::whereKey($line->id)->lockForUpdate()->firstOrFail();
            $line->setRelation('receipt', $receipt);
            $data = $this->actionInput($line, $input);
            $plan = $this->actionPlan($line, $data, true);
            if (! hash_equals($this->sign($plan), $confirmation['preview_hash'])) {
                $this->fail('Balances or payment details changed. Review this action again.');
            }
            $before = ['before_status' => $line->status, 'before_outstanding_cents' => app(CustomerInvoicing::class)->balances($receipt->customer)['pending'], 'allocations' => $plan['orders']];
            if ($data['action'] === 'clear') {
                $allocations = [];
                foreach ($plan['orders'] as $row) {
                    foreach ($row['allocations'] as $entry) {
                        $allocations[] = ['order_id' => $row['id'], 'amount_cents' => $entry['amount_cents']];
                    }
                }
                $this->post($line, $allocations, $data['payment_date'], $actor, $receipt->number.' - cheque cleared; bank reference '.$data['reference']);
                $line->status = 'cleared';
                $line->cleared_on = $data['payment_date'];
                $line->clearance_reference = $data['reference'];
            } else {
                foreach ($plan['orders'] as $row) {
                    $order = Order::whereKey($row['id'])->lockForUpdate()->firstOrFail();
                    $order->settlements()->create(['customer_receipt_line_id' => $line->id, 'related_settlement_id' => $row['settlement_id'],
                        'entry_kind' => $data['action'] === 'bounce' ? 'bounced_cheque' : 'reversal', 'amount_cents' => -$row['amount_cents'],
                        'method' => $line->method, 'reference' => $data['reference'] ?: $line->reference, 'received_on' => $data['payment_date'],
                        'note' => $receipt->number.' - '.$data['reason'], 'user_id' => $actor->id]);
                    $order->unsetRelation('settlements');
                    $order->payment_status = $order->status !== 'cancelled' && $order->balance_cents <= 0 ? 'paid' : 'unpaid';
                    if ($order->payment_status === 'unpaid') {
                        $order->paid_at = null;
                    }
                    $order->revision++;
                    $order->saveQuietly();
                    $order->events()->create(['user_id' => $actor->id, 'created_at' => now(), 'description' => $receipt->number.' receipt line #'.$line->id.' '.$data['action'].': '.$this->money($row['amount_cents']).'. Balance '.$this->money($row['balance_cents']).' → '.$this->money($order->balance_cents).'. Reason: '.$data['reason'].'. No gateway refund, stock or fulfillment change.']);
                }
                $line->status = ['reverse' => 'reversed', 'bounce' => 'bounced', 'cancel' => 'cancelled'][$data['action']];
            }
            $line->resolved_at = now();
            $line->save();
            $receipt->increment('revision');
            $this->audit($receipt, $line, $actor, $data['action'], $data['reason'], $data['reference'], $before);

            return $receipt;
        }, 3);
    }

    private function audit(CustomerReceipt $receipt, ?CustomerReceiptLine $line, User $actor, string $action, string $reason, ?string $reference = null, array $before = []): CustomerReceiptEvent
    {
        $receipt->load('lines.allocations');
        $orderIds = $receipt->lines->flatMap(fn ($l) => $l->allocations->pluck('order_id'))->unique();
        $orders = Order::withTrashed()->whereIn('id', $orderIds)->with('settlements')->get()->keyBy('id');
        $invoices = CustomerInvoice::whereIn('order_id', $orderIds)->get()->keyBy('order_id');
        $lines = $receipt->lines->map(function ($l) use ($orders, $invoices) {
            return ['id' => $l->id, 'method' => $l->method, 'amount_cents' => $l->amount_cents, 'payment_date' => $l->payment_date->toDateString(),
                'reference' => $l->reference, 'bank' => $l->bank, 'cheque_date' => $l->cheque_date?->toDateString(), 'note' => $l->note,
                'proof_name' => $l->proof_name, 'proof_hash' => $l->proof_hash, 'status' => $l->status, 'cleared_on' => $l->cleared_on?->toDateString(),
                'clearance_reference' => $l->clearance_reference, 'planned_allocations' => $l->planned_allocations,
                'allocations' => $l->allocations->map(fn ($s) => ['id' => $s->id, 'order_id' => $s->order_id, 'number' => $orders->get($s->order_id)?->number,
                    'invoice' => $invoices->get($s->order_id)?->label, 'amount_cents' => $s->amount_cents, 'kind' => $s->kind,
                    'date' => $s->received_on?->toDateString(), 'balance_cents' => $orders->get($s->order_id)?->balance_cents])->all()];
        })->all();
        $snapshot = ['customer' => $receipt->customer_snapshot, 'number' => $receipt->number, 'action' => $action, 'reason' => $reason, 'change' => $before,
            'actor' => $actor->name, 'recorded_at' => now()->toIso8601String(), 'total_cents' => $receipt->total_cents, 'lines' => $lines,
            'confirmed_cents' => (int) $receipt->lines->filter(fn ($l) => in_array($l->status, ['confirmed', 'cleared'], true))->sum('amount_cents'),
            'pending_cents' => (int) $receipt->lines->where('status', 'awaiting_clearance')->sum('amount_cents'),
            'outstanding_cents' => app(CustomerInvoicing::class)->balances($receipt->customer)['pending']];

        return $receipt->events()->create(['customer_receipt_line_id' => $line?->id, 'user_id' => $actor->id, 'actor_name' => $actor->name,
            'action' => $action, 'reason' => $reason, 'reference' => $reference, 'snapshot' => $snapshot]);
    }
}
