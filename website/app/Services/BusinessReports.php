<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BusinessReports
{
    public const TYPES = [
        'sales' => ['Orders & Sales', 'Order value, received amounts and remaining balances.', 'shopping-cart'],
        'customers' => ['Customer Summary', 'Customer-wise order value, collections and outstanding money.', 'users'],
        'payments' => ['Collections & Refunds', 'Money received and refunded by transaction recording date.', 'exchange-alt'],
        'methods' => ['Payment Methods', 'Cash, e-transfer, card and PayPal collections breakdown.', 'credit-card'],
        'balances' => ['Outstanding & Refund Due', 'Current unpaid balances and money owed back to customers.', 'balance-scale'],
        'fulfilment' => ['Delivery & Pickup Planner', 'Scheduled, overdue and unscheduled open orders.', 'truck'],
    ];

    public function orders(array $filters = [])
    {
        $query = app(AdminDashboard::class)->balances()->addSelect('o.customer_id', 'o.email', 'o.phone', 'o.source', 'o.fulfillment', 'o.fulfillment_date', 'o.payment_status', 'o.revision')
            ->selectRaw("COALESCE(p.provider, 'manual') AS provider");
        if (! empty($filters['source'])) {
            $query->where('o.source', $filters['source']);
        }
        if (! empty($filters['customer'])) {
            if (str_starts_with($filters['customer'], 'id:')) {
                $query->where('o.customer_id', substr($filters['customer'], 3));
            } else {
                $query->whereNull('o.customer_id')->whereRaw('LOWER(o.email) = ?', [mb_strtolower(substr($filters['customer'], 6))]);
            }
        }
        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
            $query->where(fn ($q) => $q->where('o.number', 'like', $term)->orWhere('o.email', 'like', $term)->orWhere('o.first_name', 'like', $term)->orWhere('o.last_name', 'like', $term));
        }

        return DB::query()->fromSub($query, 'b');
    }

    // Each row is a recorded movement, not an order total. No receipt upload counts as money.
    public function movements()
    {
        $gateway = DB::table('payments as p')->where('p.mode', 'live')->whereNotNull('p.paid_at')
            ->selectRaw("p.order_id, p.paid_at AS occurred_at, CAST(p.amount_cents AS SIGNED) AS amount_cents, CASE WHEN p.provider = 'paypal' THEN 'paypal' ELSE 'card' END AS method, p.provider, p.transaction_id AS reference, 'Gateway payment' AS entry_type");
        $manual = DB::table('orders as o')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('payments as p')->whereColumn('p.order_id', 'o.id'))
            ->selectRaw("o.id AS order_id, o.paid_at AS occurred_at, CAST(COALESCE(o.manual_collected_cents, CASE WHEN o.payment_status IN ('paid','refunded') THEN COALESCE(o.subtotal_cents + o.delivery_cents + o.tax_cents,0) ELSE 0 END) AS SIGNED) AS amount_cents, o.payment_method AS method, 'manual' AS provider, NULL AS reference, 'Original manual payment' AS entry_type");
        $adjustments = DB::table('order_settlements as s')->selectRaw("s.order_id, s.created_at AS occurred_at, s.amount_cents, s.method, 'manual' AS provider, s.reference, CASE WHEN s.amount_cents < 0 THEN 'Refund' ELSE 'Payment received' END AS entry_type");
        $refunds = DB::table('payment_refunds as r')->join('payments as p', 'p.id', '=', 'r.payment_id')->where('p.mode', 'live')->whereIn('r.status', ['completed', 'succeeded'])
            ->selectRaw("p.order_id, r.created_at AS occurred_at, -CAST(r.amount_cents AS SIGNED) AS amount_cents, CASE WHEN p.provider = 'paypal' THEN 'paypal' ELSE 'card' END AS method, p.provider, r.provider_refund_id AS reference, 'Gateway refund (entry recorded)' AS entry_type");
        // Historic aggregate refunds have no reliable transaction timestamp; explicitly leave them undated.
        $legacyRefunds = DB::table('orders as o')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('payments as p')->whereColumn('p.order_id', 'o.id'))
            ->selectRaw("o.id AS order_id, NULL AS occurred_at, -CAST(COALESCE(o.manual_refunded_cents, CASE WHEN o.payment_status = 'refunded' THEN COALESCE(o.manual_collected_cents,o.subtotal_cents + o.delivery_cents + o.tax_cents,0) ELSE 0 END) AS SIGNED) AS amount_cents, o.payment_method AS method, 'manual' AS provider, NULL AS reference, 'Historic refund — date unavailable' AS entry_type");

        return $gateway->unionAll($manual)->unionAll($adjustments)->unionAll($refunds)->unionAll($legacyRefunds);
    }

    public function cash(array $f, bool $dated = true)
    {
        $query = DB::query()->fromSub($this->movements(), 'm')->joinSub($this->orders($f), 'o', 'o.id', '=', 'm.order_id')->where('m.amount_cents', '!=', 0);
        if (! empty($f['method'])) {
            $query->where('m.method', $f['method']);
        }
        if (! empty($f['provider'])) {
            $query->where('m.provider', $f['provider']);
        }
        if ($dated) {
            $query->where('m.occurred_at', '>=', $f['from'].' 00:00:00')->where('m.occurred_at', '<', Carbon::parse($f['to'])->addDay()->toDateString());
        }

        return $query;
    }

    public function orderSummary($query): array
    {
        return (array) (clone $query)->selectRaw("COUNT(*) AS orders, COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total_cents ELSE 0 END),0) AS value, COALESCE(SUM(net_cents),0) AS received, COALESCE(SUM(CASE WHEN balance_cents > 0 THEN balance_cents ELSE 0 END),0) AS due, COALESCE(SUM(CASE WHEN balance_cents < 0 THEN -balance_cents ELSE 0 END),0) AS refund_due, SUM(CASE WHEN total_cents IS NULL THEN 1 ELSE 0 END) AS unpriced")->first();
    }

    public function report(string $kind, array $f): array
    {
        $cash = in_array($kind, ['payments', 'methods']);
        $trend = collect();
        $undated = 0;
        if ($cash) {
            $query = $this->cash($f);
            $summary = (array) (clone $query)->selectRaw('COUNT(*) AS transactions, COALESCE(SUM(CASE WHEN m.amount_cents > 0 THEN m.amount_cents ELSE 0 END),0) AS received, COALESCE(SUM(CASE WHEN m.amount_cents < 0 THEN -m.amount_cents ELSE 0 END),0) AS refunded, COALESCE(SUM(m.amount_cents),0) AS net')->first();
            $undated = $this->cash($f, false)->whereNull('m.occurred_at')->count();
            if ($kind === 'methods') {
                $rows = (clone $query)->select('m.method', 'm.provider')->selectRaw('COUNT(*) AS transactions, COUNT(DISTINCT m.order_id) AS orders, SUM(CASE WHEN m.amount_cents > 0 THEN m.amount_cents ELSE 0 END) AS received, SUM(CASE WHEN m.amount_cents < 0 THEN -m.amount_cents ELSE 0 END) AS refunded, SUM(m.amount_cents) AS net')->groupBy('m.method', 'm.provider')->orderByDesc('received');
            } else {
                $rows = (clone $query)->select('o.id', 'o.number', 'o.first_name', 'o.last_name', 'm.*')->orderByDesc('m.occurred_at')->orderByDesc('m.order_id')->orderBy('m.entry_type');
            }
            $trend = (clone $query)->selectRaw('DATE(m.occurred_at) AS day, SUM(CASE WHEN m.amount_cents > 0 THEN m.amount_cents ELSE 0 END) AS amount')->groupByRaw('DATE(m.occurred_at)')->orderBy('day')->get();
        } else {
            $query = $this->orders($f);
            if (! empty($f['method'])) {
                $query->where('payment_method', $f['method']);
            }
            if (in_array($kind, ['sales', 'customers'])) {
                $query->where('ordered_at', '>=', $f['from'].' 00:00:00')->where('ordered_at', '<', Carbon::parse($f['to'])->addDay()->toDateString());
            }
            if ($kind === 'balances') {
                $query->whereNotNull('balance_cents')->where('balance_cents', ($f['balance'] ?? 'due') === 'refund' ? '<' : '>', 0);
            }
            if ($kind === 'fulfilment') {
                $query->whereNotIn('status', ['completed', 'delivered', 'cancelled']);
                if (! empty($f['fulfillment'])) {
                    $query->where('fulfillment', $f['fulfillment']);
                }
                match ($f['schedule'] ?? 'scheduled') {
                    'unscheduled' => $query->whereNull('fulfillment_date'),
                    'overdue' => $query->where('fulfillment_date', '<', today()->toDateString()),
                    default => $query->whereBetween('fulfillment_date', [$f['from'], $f['to']]),
                };
            }
            $summary = $this->orderSummary($query);
            if ($kind === 'customers') {
                $rows = (clone $query)->selectRaw("customer_id, CASE WHEN customer_id IS NULL THEN LOWER(email) ELSE '' END AS guest_email, MAX(first_name) AS first_name, MAX(last_name) AS last_name, MAX(email) AS email, COUNT(*) AS orders, SUM(CASE WHEN status != 'cancelled' THEN total_cents ELSE 0 END) AS value, SUM(net_cents) AS received, SUM(CASE WHEN balance_cents > 0 THEN balance_cents ELSE 0 END) AS due, SUM(CASE WHEN balance_cents < 0 THEN -balance_cents ELSE 0 END) AS refund_due, SUM(CASE WHEN total_cents IS NULL THEN 1 ELSE 0 END) AS unpriced")
                    ->groupBy('customer_id')->groupByRaw("CASE WHEN customer_id IS NULL THEN LOWER(email) ELSE '' END")->orderByDesc('value')->orderBy('customer_id')->orderBy('guest_email');
            } else {
                $rows = (clone $query)->select('b.*');
                if ($kind === 'balances') {
                    $rows->orderByRaw('ABS(balance_cents) DESC');
                } elseif ($kind === 'fulfilment') {
                    $rows->orderBy('fulfillment_date');
                }
                $rows->orderByDesc('id');
            }
            if ($kind === 'sales') {
                $trend = (clone $query)->where('status', '!=', 'cancelled')->selectRaw('DATE(ordered_at) AS day, SUM(total_cents) AS amount')->groupByRaw('DATE(ordered_at)')->orderBy('day')->get();
            }
        }

        if ($cash || $kind === 'sales') {
            $byDay = $trend->keyBy('day');
            $trend = collect();
            for ($day = Carbon::parse($f['from']); $day->lte(Carbon::parse($f['to'])); $day->addDay()) {
                $date = $day->toDateString();
                $trend->push((object) ['day' => $date, 'amount' => $byDay->get($date)->amount ?? 0]);
            }
        }

        return compact('rows', 'summary', 'trend', 'undated');
    }
}
