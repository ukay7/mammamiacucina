<?php

namespace App\Services;

use App\Models\{Customer, CustomerInvoice, CustomerRefund, CustomerRefundLine, Order, OrderCredit, User};
use App\Support\BusinessTime;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\{Rule, ValidationException};

class CustomerRefunds
{
    private function fail(string $message, string $field = 'refund'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function authorize(User $actor): void { abort_unless($actor->is_active && $actor->isSuper(), 403); }
    private function sign(array $data): string { return hash_hmac('sha256', json_encode($data, JSON_THROW_ON_ERROR), config('app.key')); }
    private function money(int $amount): string { return 'CAD '.number_format($amount / 100, 2); }

    public function account(Order $order): array
    {
        $order->loadMissing('payment', 'settlements');
        $total = $order->final_total_cents ?? 0;
        $pending = (int) $order->pending_refund_cents;
        return ['id'=>$order->id, 'number'=>$order->number, 'status'=>$order->status_label,
            'total_cents'=>$total, 'net_received_cents'=>$order->net_received_cents,
            'refunded_cents'=>$order->refunded_cents, 'credited_cents'=>(int)$order->refund_credit_cents,
            'pending_cents'=>$pending, 'balance_cents'=>$order->balance_cents,
            'maximum_cents'=>max(0, min($total - (int)$order->refund_credit_cents - $pending, $order->net_received_cents - $pending)),
            'maximum_tax_cents'=>max(0, (int)$order->tax_cents - (int)$order->refund_credit_tax_cents - (int)$order->pending_refund_tax_cents),
            'tax_cents'=>(int)$order->tax_cents, 'revision'=>(int)$order->revision, 'gateway'=>(bool)$order->payment];
    }

    private function input(array $input): array
    {
        $data = Validator::make($input, ['order_id'=>'required|integer|min:1', 'request_key'=>'required|uuid',
            'reason'=>'required|string|max:2000', 'lines'=>'required|array|min:1|max:10', 'lines.*'=>'required|array',
            'lines.*.key'=>'required|uuid|distinct', 'lines.*.method'=>['required', Rule::in(array_keys(CustomerRefundLine::METHODS))],
            'lines.*.amount'=>['required','regex:/^\d{1,7}(\.\d{1,2})?$/D'],
            'lines.*.tax'=>['required','regex:/^\d{1,7}(\.\d{1,2})?$/D'],
            'lines.*.refund_date'=>'required|date_format:Y-m-d|before_or_equal:'.BusinessTime::today()->toDateString(),
            'lines.*.reference'=>'nullable|string|max:150', 'lines.*.bank'=>'nullable|string|max:150',
            'lines.*.cheque_date'=>'nullable|date_format:Y-m-d', 'lines.*.note'=>'nullable|string|max:2000',
            'lines.*.verified'=>'nullable|boolean', 'lines.*.proof_hash'=>'nullable|string|size:64', 'lines.*.proof_name'=>'nullable|string|max:255',
        ])->validate();
        $data['order_id'] = (int) $data['order_id'];
        $data['lines'] = array_values($data['lines']);
        $data['reason'] = trim($data['reason']);
        if ($data['reason'] === '') $this->fail('Enter the reason for the refund.', 'reason');
        foreach ($data['lines'] as $i => &$line) {
            $line['amount_cents'] = OrderAmendment::cents($line['amount']);
            $line['tax_cents'] = OrderAmendment::cents($line['tax']);
            if ($line['amount_cents'] <= 0) $this->fail('Enter an amount greater than zero.', "lines.$i.amount");
            if ($line['tax_cents'] > $line['amount_cents']) $this->fail('Included tax cannot exceed the refund amount.', "lines.$i.tax");
            foreach (['reference','bank','note'] as $field) $line[$field] = trim($line[$field] ?? '');
            if ($line['method'] !== 'cash' && $line['reference'] === '') $this->fail('Enter the refund transaction reference or cheque number.', "lines.$i.reference");
            if ($line['method'] === 'cheque' && empty($line['cheque_date'])) $this->fail('Enter the cheque date.', "lines.$i.cheque_date");
            if ($line['method'] !== 'cheque' && !($line['verified'] ?? false)) $this->fail('Confirm that this money was actually returned to the customer.', "lines.$i.verified");
            if (in_array($line['method'], ['etransfer','cheque','card','paypal']) && empty($line['proof_hash'])) $this->fail('Attach the supporting image or PDF for this refund.', "lines.$i.proof");
            if ($line['method'] !== 'cheque') { $line['bank'] = ''; $line['cheque_date'] = null; }
            $line['proof_hash'] ??= null; $line['proof_name'] ??= null;
            unset($line['amount'], $line['tax'], $line['verified']);
        }
        unset($line);
        return $data;
    }

    private function eligible(Order $order): void
    {
        $order->assertEditable();
        if (!in_array($order->status, ['delivered','completed']) || $order->currency !== 'CAD' || $order->final_total_cents === null) {
            $this->fail('Choose a delivered or completed CAD order with confirmed totals.', 'order_id');
        }
        $order->loadMissing('payment', 'settlements');
        if ($order->payment && (!$order->payment->paid_at || $order->payment->attention || $order->payment->mode !== 'live')) {
            $this->fail('Reconcile the live gateway payment before recording a refund.');
        }
        if ($order->payment && DB::table('payment_refunds')->where('payment_id', $order->payment->id)->whereNotIn('status', ['completed','succeeded','failed'])->lockForUpdate()->get(['id'])->isNotEmpty()) {
            $this->fail('A gateway refund is still pending. Reconcile it before recording another refund.');
        }
    }

    private function plan(Customer $customer, Order $order, array $data, User $actor, int $expires): array
    {
        $this->eligible($order);
        $account = $this->account($order);
        $total = array_sum(array_column($data['lines'], 'amount_cents'));
        $tax = array_sum(array_column($data['lines'], 'tax_cents'));
        if ($total > $account['maximum_cents']) $this->fail('Refund total '.$this->money($total).' exceeds the remaining refundable amount '.$this->money($account['maximum_cents']).'. Previous refunds and pending cheques are included in this limit.');
        if ($tax > $account['maximum_tax_cents']) $this->fail('Included tax exceeds the remaining tax available to credit.');
        $nonTaxRemaining = $account['total_cents'] - $account['tax_cents'] - ((int)$order->refund_credit_cents - (int)$order->refund_credit_tax_cents) - ((int)$order->pending_refund_cents - (int)$order->pending_refund_tax_cents);
        if ($total - $tax > $nonTaxRemaining) $this->fail('The amount before tax exceeds the remaining sale amount. Check the included tax.');
        $references = [];
        $pending = 0;
        foreach ($data['lines'] as $i => $line) {
            if ($line['refund_date'] < BusinessTime::local($order->ordered_at)->toDateString()) $this->fail('Refund date cannot precede the order date.', "lines.$i.refund_date");
            if ($order->payment && in_array($line['method'], ['card','paypal'])) $this->fail('This order has an integrated gateway payment. Card/PayPal gateway refunds must be processed and reconciled through that provider, not recorded as an external-terminal refund. Choose the actual external payout method.');
            if ($line['method'] === 'cheque') $pending += $line['amount_cents'];
            if ($line['method'] === 'cash') continue;
            $key = $line['method'].'|'.mb_strtolower($line['reference']);
            $duplicate = CustomerRefundLine::whereHas('refund', fn($q) => $q->where('customer_id', $customer->id))
                ->where('method', $line['method'])->where('status', '!=', 'cancelled')
                ->whereRaw('LOWER(TRIM(reference)) = ?', [mb_strtolower($line['reference'])])->exists();
            if (isset($references[$key]) || $duplicate) $this->fail('This refund reference is already recorded for this customer. Review the existing refund first.', "lines.$i.reference");
            $references[$key] = true;
        }
        return ['actor_id'=>$actor->id, 'customer_id'=>$customer->id, 'request_key'=>$data['request_key'], 'expires_at'=>$expires,
            'order'=>$account, 'reason'=>$data['reason'], 'lines'=>$data['lines'], 'total_cents'=>$total, 'tax_cents'=>$tax,
            'confirmed_cents'=>$total-$pending, 'pending_cents'=>$pending, 'balance_after_cents'=>$account['balance_cents']];
    }

    public function preview(Customer $customer, array $input, User $actor): array
    {
        $this->authorize($actor); $data = $this->input($input);
        $order = $customer->orders()->findOrFail($data['order_id']);
        $plan = $this->plan($customer, $order, $data, $actor, now()->addMinutes(20)->timestamp);
        return $plan + ['preview_hash'=>$this->sign($plan)];
    }

    public function create(Customer $customer, array $input, User $actor, ?callable $storeProofs = null): CustomerRefund
    {
        $this->authorize($actor); $data = $this->input($input);
        $confirmation = Validator::make($input, ['confirmed'=>'accepted','preview_hash'=>'required|string|size:64','expires_at'=>'required|integer'])->validate();
        $requestHash = $this->sign(['actor_id'=>$actor->id,'customer_id'=>$customer->id,'data'=>$data]);
        return DB::transaction(function () use ($customer, $actor, $data, $confirmation, $requestHash, $storeProofs) {
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
            if ($existing = CustomerRefund::where('request_key', $data['request_key'])->first()) {
                if ($existing->customer_id !== $customer->id || $existing->created_by !== $actor->id || !hash_equals($existing->request_hash, $requestHash) || !hash_equals($existing->preview_hash, $confirmation['preview_hash'])) $this->fail('This request was already used with different refund details. Reload before adding a refund.');
                return $existing;
            }
            if ($confirmation['expires_at'] < now()->timestamp || $confirmation['expires_at'] > now()->addMinutes(20)->timestamp) $this->fail('This preview expired. Preview the refund again.');
            $order = $customer->orders()->lockForUpdate()->findOrFail($data['order_id']);
            // Use current locking reads, including on MySQL REPEATABLE READ: a
            // concurrent gateway/receipt adjustment must not leave stale funds.
            $order->load(['payment'=>fn($q)=>$q->lockForUpdate(), 'settlements'=>fn($q)=>$q->lockForUpdate()]);
            $plan = $this->plan($customer, $order, $data, $actor, (int)$confirmation['expires_at']);
            if (!hash_equals($this->sign($plan), $confirmation['preview_hash'])) $this->fail('The order, available refund amount, or entered details changed. Preview again before confirming.');
            $paths = $storeProofs ? $storeProofs() : [];
            $invoice = CustomerInvoice::where('order_id', $order->id)->first();
            $refund = CustomerRefund::create(['customer_id'=>$customer->id, 'order_id'=>$order->id, 'request_key'=>$data['request_key'],
                'request_hash'=>$requestHash, 'preview_hash'=>$confirmation['preview_hash'], 'total_cents'=>$plan['total_cents'], 'tax_cents'=>$plan['tax_cents'],
                'reason'=>$data['reason'], 'created_by'=>$actor->id, 'snapshot'=>['customer'=>$customer->only(['id','name','business_name','email','phone','address','city','province','postal_code','country']),
                    'brand'=>app(DocumentTheme::class)->brand(), 'order'=>$plan['order'], 'invoice'=>$invoice?->label]]);
            $refund->update(['number'=>'RF-'.str_pad((string)$refund->id, 5, '0', STR_PAD_LEFT)]);
            foreach ($data['lines'] as $entry) {
                $line = $refund->lines()->create(['client_key'=>$entry['key'], 'method'=>$entry['method'], 'amount_cents'=>$entry['amount_cents'],
                    'tax_cents'=>$entry['tax_cents'], 'refund_date'=>$entry['refund_date'], 'reference'=>$entry['reference'] ?: null,
                    'cheque_date'=>$entry['cheque_date'], 'bank'=>$entry['bank'] ?: null, 'note'=>$entry['note'],
                    'proof_path'=>$paths[$entry['key']] ?? null, 'proof_hash'=>$entry['proof_hash'], 'proof_name'=>$entry['proof_name'],
                    'status'=>$entry['method'] === 'cheque' ? 'awaiting_clearance' : 'confirmed']);
                $line->setRelation('refund', $refund);
                if ($line->status === 'confirmed') $this->post($order, $line, $entry['refund_date'], $actor);
                else {
                    $order->pending_refund_cents += $line->amount_cents;
                    $order->pending_refund_tax_cents += $line->tax_cents;
                }
            }
            $order->revision++;
            $order->saveQuietly();
            $this->audit($refund, null, $order, $actor, 'created', 'Refund recorded; confirmed payouts have matching credit notes. Cheques await bank clearance.');
            return $refund;
        }, 3);
    }

    private function post(Order $order, CustomerRefundLine $line, string $date, User $actor): void
    {
        $before = $order->balance_cents;
        // Freeze legacy received/refunded amounts before changing derived payment status.
        if (!$order->payment) {
            $order->manual_collected_cents ??= $order->collected_cents;
            $order->manual_refunded_cents ??= $order->payment_status === 'refunded' ? $order->collected_cents : 0;
            $order->original_payment_method ??= $order->payment_method;
        }
        $credit = OrderCredit::create(['order_id'=>$order->id,'customer_id'=>$line->refund->customer_id,'customer_refund_line_id'=>$line->id,
            'amount_cents'=>$line->amount_cents,'tax_cents'=>$line->tax_cents,'issued_by'=>$actor->id,
            'snapshot'=>$line->refund->snapshot + ['refund_number'=>$line->refund->number,'reason'=>$line->refund->reason,'refund_date'=>$date,
                'method'=>$line->method,'reference'=>$line->clearance_reference ?: $line->reference]]);
        $credit->update(['number'=>'CN-'.str_pad((string)$credit->id,5,'0',STR_PAD_LEFT)]);
        $order->refund_credit_cents += $line->amount_cents;
        $order->refund_credit_tax_cents += $line->tax_cents;
        $order->settlements()->create(['customer_refund_line_id'=>$line->id,'amount_cents'=>-$line->amount_cents,'entry_kind'=>'refund',
            'method'=>$line->method,'reference'=>$line->clearance_reference ?: $line->reference,'received_on'=>$date,
            'receipt_path'=>$line->proof_path,'note'=>$line->refund->number.' / '.$credit->number.' — '.$line->refund->reason,'user_id'=>$actor->id]);
        $order->unsetRelation('settlements');
        if (!$order->payment && $order->balance_cents <= 0) $order->payment_status = $order->net_received_cents === 0 ? 'refunded' : 'paid';
        if ($order->balance_cents !== $before) throw new \LogicException('A refund and matching credit must preserve the order balance.');
    }

    private function actionPlan(CustomerRefundLine $line, Order $order, array $input, User $actor): array
    {
        $data = Validator::make($input, ['action'=>'required|in:clear,cancel','revision'=>'required|integer|min:1',
            'reason'=>'required|string|max:2000','date'=>'required|date_format:Y-m-d|before_or_equal:'.BusinessTime::today()->toDateString(),
            'reference'=>'nullable|string|max:150'])->validate();
        $data['reason'] = trim($data['reason']); $data['reference'] = trim($data['reference'] ?? ''); $data['revision'] = (int)$data['revision'];
        if ($data['reason'] === '') $this->fail('Enter a reason.');
        if ($line->method !== 'cheque' || $line->status !== 'awaiting_clearance') $this->fail('Only a refund cheque awaiting clearance can be cleared or cancelled.');
        if ($line->refund->revision !== $data['revision']) $this->fail('This refund changed. Reload before continuing.');
        if ($data['date'] < $line->refund_date->toDateString()) $this->fail('Action date cannot precede the refund date.');
        if ($data['action'] === 'clear') {
            $this->eligible($order);
            if ($data['reference'] === '' || $data['date'] < $line->cheque_date->toDateString()) $this->fail('Enter the bank clearance reference and a date on or after the cheque date.');
            if ($order->net_received_cents < (int)$order->pending_refund_cents || $order->final_total_cents < (int)$order->refund_credit_cents + (int)$order->pending_refund_cents) $this->fail('Available funds changed. Resolve other pending refunds or payment corrections before clearing this cheque.');
        }
        return ['actor_id'=>$actor->id,'refund_id'=>$line->refund->id,'line_id'=>$line->id,'data'=>$data,'order'=>$this->account($order)];
    }

    public function previewAction(CustomerRefundLine $line, array $input, User $actor): array
    {
        $this->authorize($actor);
        $plan = $this->actionPlan($line, $line->refund->order, $input, $actor);
        return $plan + ['preview_hash'=>$this->sign($plan)];
    }

    public function act(CustomerRefundLine $line, array $input, User $actor): void
    {
        $this->authorize($actor);
        $confirmation = Validator::make($input, ['confirmed'=>'accepted','preview_hash'=>'required|string|size:64'])->validate();
        DB::transaction(function () use ($line, $input, $actor, $confirmation) {
            $parent = $line->refund;
            Customer::lockForUpdate()->findOrFail($parent->customer_id);
            $order = Order::withTrashed()->lockForUpdate()->findOrFail($parent->order_id);
            $order->load(['payment'=>fn($q)=>$q->lockForUpdate(), 'settlements'=>fn($q)=>$q->lockForUpdate()]);
            $refund = CustomerRefund::lockForUpdate()->findOrFail($parent->id);
            $line = CustomerRefundLine::lockForUpdate()->findOrFail($line->id);
            $line->setRelation('refund', $refund);
            $plan = $this->actionPlan($line, $order, $input, $actor);
            if (!hash_equals($this->sign($plan), $confirmation['preview_hash'])) $this->fail('The refund or order changed. Review this action again.');
            $data = $plan['data'];
            $order->pending_refund_cents -= $line->amount_cents;
            $order->pending_refund_tax_cents -= $line->tax_cents;
            if ($order->pending_refund_cents < 0 || $order->pending_refund_tax_cents < 0) throw new \LogicException('Refund reservation mismatch.');
            $line->update(['status'=>$data['action'] === 'clear' ? 'cleared' : 'cancelled', 'resolved_on'=>$data['date'],
                'resolved_by'=>$actor->id,'clearance_reference'=>$data['reference'] ?: null]);
            if ($data['action'] === 'clear') $this->post($order, $line, $data['date'], $actor);
            $order->revision++; $order->saveQuietly();
            $refund->increment('revision');
            $this->audit($refund, $line, $order, $actor, $data['action'], $data['reason']);
        }, 3);
    }

    private function audit(CustomerRefund $refund, ?CustomerRefundLine $line, Order $order, User $actor, string $action, string $description): void
    {
        $lines = $refund->lines()->with('credit:id,number,customer_refund_line_id')->get();
        $snapshot = ['number'=>$refund->number,'reason'=>$refund->reason,'actor'=>$actor->name,'order'=>$this->account($order),
            'confirmed_cents'=>(int)$lines->whereIn('status',['confirmed','cleared'])->sum('amount_cents'),
            'pending_cents'=>(int)$lines->where('status','awaiting_clearance')->sum('amount_cents'),
            'cancelled_cents'=>(int)$lines->where('status','cancelled')->sum('amount_cents'),
            'lines'=>$lines->map(fn($l)=>$l->only(['id','method','amount_cents','tax_cents','status','reference','bank','note','proof_name','proof_hash','clearance_reference']) +
                ['refund_date'=>$l->refund_date->toDateString(),'cheque_date'=>$l->cheque_date?->toDateString(),'resolved_on'=>$l->resolved_on?->toDateString(),'credit_number'=>$l->credit?->number])->all()];
        $refund->events()->create(['customer_refund_line_id'=>$line?->id,'user_id'=>$actor->id,'action'=>$action,'description'=>$description,'snapshot'=>$snapshot,'created_at'=>now()]);
        $order->events()->create(['user_id'=>$actor->id,'description'=>$refund->number.' '.$action.': '.$description.' Confirmed refunds '.$this->money($snapshot['confirmed_cents']).'; pending cheques '.$this->money($snapshot['pending_cents']).'. Original items and delivery unchanged.','created_at'=>now()]);
    }
}
