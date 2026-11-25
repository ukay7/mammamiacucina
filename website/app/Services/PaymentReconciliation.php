<?php

namespace App\Services;

use App\Models\User;
use App\Support\BusinessTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentReconciliation
{
    public const STATUSES = [
        'matched' => 'Matched', 'partial' => 'Partially matched', 'unmatched' => 'Unmatched', 'follow_up' => 'Requires follow-up',
    ];

    public function __construct(private BusinessReports $reports) {}

    public function source(string $week): Collection
    {
        $end = BusinessTime::start($week)->setTimezone(BusinessTime::zone())->addDays(6)->toDateString();
        $movements = $this->reports->cash(['from' => $week, 'to' => $end])
            ->whereNotIn('m.entry_kind', ['correction_debit', 'correction_credit'])
            ->select('m.*', 'o.number', 'o.business_name', 'o.first_name', 'o.last_name')
            ->orderBy('m.occurred_at')->orderBy('m.source_type')->orderBy('m.source_id')->orderBy('m.order_id')->get();

        return $movements->groupBy(fn ($row) => $row->source_type.':'.$row->source_id)
            ->map(function (Collection $lines) {
                $first = $lines->first();
                $snapshot = [
                    'date' => BusinessReports::movementDate($first->occurred_at),
                    'orders' => $lines->pluck('number')->unique()->implode(', '),
                    'customer' => $first->business_name ?: trim($first->first_name.' '.$first->last_name),
                    'amount' => (int) $lines->sum('amount_cents'),
                    'method' => $first->method, 'provider' => $first->provider,
                    'reference' => $first->reference, 'type' => $first->entry_type,
                    'allocations' => $lines->map(fn ($line) => [(int) $line->order_id, (int) $line->amount_cents])->all(),
                ];

                return ['snapshot' => $snapshot, 'fingerprint' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR))];
            });
    }

    public function reviews(int $period): Collection
    {
        return DB::table('payment_reconciliation_reviews')->where('reconciliation_id', $period)->latest('id')
            ->get()->map(function ($review) {
                $review->snapshot = json_decode($review->snapshot, true, flags: JSON_THROW_ON_ERROR);
                return $review;
            })->groupBy('movement');
    }

    public function record(string $week, User $actor, array $data): void
    {
        DB::transaction(function () use ($week, $actor, $data) {
            $period = $this->lock($week);
            $current = $this->source($week)->get($data['movement']);
            if (! $current || ! hash_equals($current['fingerprint'], $data['fingerprint'])) {
                $this->fail('The source transaction changed. Reload the week and review it again.');
            }
            $amount = $current['snapshot']['amount'];
            $matched = isset($data['matched_cad']) ? AccountingLedger::cents($data['matched_cad']) : null;
            $status = $data['status'];
            if ($status === 'matched' && $matched !== $amount) {
                $this->fail('A match must equal the recorded amount, including the sign for refunds and reversals.');
            }
            if ($status === 'partial' && (! $matched || ($matched < 0) !== ($amount < 0) || abs($matched) >= abs($amount))) {
                $this->fail('A partial match needs a smaller nonzero amount in the same direction as the recorded movement.');
            }
            if (in_array($status, ['unmatched', 'follow_up']) && $matched) {
                $this->fail('Use zero or leave the amount blank for unmatched and follow-up entries.');
            }
            DB::table('payment_reconciliation_reviews')->insert([
                'reconciliation_id' => $period->id, 'movement' => $data['movement'], 'fingerprint' => $current['fingerprint'],
                'snapshot' => json_encode($current['snapshot'], JSON_THROW_ON_ERROR), 'status' => $status, 'matched_cents' => $matched,
                'bank_reference' => $data['bank_reference'] ?? null, 'notes' => $data['notes'] ?? null,
                'reviewed_by' => $actor->id, 'reviewed_by_name' => $actor->name, 'created_at' => now(),
            ]);
        }, 3);
    }

    public function complete(string $week, User $actor, ?string $notes): void
    {
        DB::transaction(function () use ($week, $actor, $notes) {
            $period = $this->lock($week);
            $records = $this->reviews($period->id);
            foreach ($this->source($week) as $key => $payment) {
                if ($records->get($key)?->first()->fingerprint !== $payment['fingerprint']) {
                    $this->fail('Review every recorded payment and any changes before completing this week.');
                }
            }
            DB::table('payment_reconciliations')->where('id', $period->id)->update([
                'completed_at' => now(), 'completed_by' => $actor->id, 'completed_by_name' => $actor->name,
                'notes' => $notes, 'updated_at' => now(),
            ]);
        }, 3);
    }

    private function lock(string $week): object
    {
        DB::table('payment_reconciliations')->insertOrIgnore(['week_start' => $week, 'created_at' => now(), 'updated_at' => now()]);
        $period = DB::table('payment_reconciliations')->where('week_start', $week)->lockForUpdate()->first();
        if ($period->completed_at) $this->fail('This weekly review is complete.');
        return $period;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['review' => $message]);
    }
}
