<?php

namespace App\Services;

use App\Models\CustomerInvoice;
use App\Support\BusinessTime;
use Illuminate\Support\Facades\DB;

class AccountingDocuments
{
    public const TYPES = [
        'invoice' => 'Invoice', 'initial-payment' => 'Original payment receipt', 'settlement' => 'Payment or adjustment',
        'account-receipt' => 'Account receipt', 'credit-note' => 'Credit note', 'refund' => 'Refund record', 'revision' => 'Order adjustment',
    ];

    public function __construct(private ReceivableAging $aging) {}

    public function report(array $f): array
    {
        $rows = [];
        foreach (self::TYPES as $kind => $label) {
            if (isset($f['document_type']) && $f['document_type'] !== $kind) continue;
            if (isset($f['invoice_status']) && $kind !== 'invoice') continue;
            foreach ($this->query($kind, $f) as $record) {
                $balance = $kind === 'invoice' ? $this->aging->invoiceBalance($record) : null;
                if ($kind === 'invoice' && isset($f['invoice_status']) && match ($f['invoice_status']) {
                    'outstanding' => $balance === 0, 'settled' => $balance > 0, default => false,
                }) continue;

                $snapshot = $kind === 'invoice' ? $record->snapshot : json_decode($record->snapshot ?? '{}', true);
                [$reference, $customer, $date, $amount, $tax] = $kind === 'invoice'
                    ? [$record->label, $record->order->customer_id, $record->issued_at, $record->total_cents, data_get($snapshot, 'order.tax_cents')]
                    : [$record->reference, $record->customer_id, $record->recorded_at,
                        $record->amount ?? ($snapshot['confirmed_cents'] ?? null), $record->tax ?? null];
                $isReportedOriginal = $kind !== 'initial-payment' || $amount > 0;
                if (! $isReportedOriginal) continue;
                $rows[] = [
                    BusinessTime::format($date), $label, $reference, $customer,
                    AccountingReports::money($amount), AccountingReports::money($tax), AccountingReports::money($balance),
                    $customer ? route('admin.accounting.document', [$customer, $kind, $record->id, 'download' => 1]) : '',
                ];
            }
        }
        usort($rows, fn ($a, $b) => strcmp($b[0] ? BusinessTime::local($b[0])->format('c') : '', $a[0] ? BusinessTime::local($a[0])->format('c') : ''));
        return [['Recorded (Toronto)', 'Document type', 'Reference', 'Customer #', 'Recorded CAD', 'Tax CAD', 'Current balance CAD', 'PDF'],
            $rows, 'Each row identifies a source document or saved version. Amounts across document types should not be added together.'];
    }

    private function query(string $kind, array $f): iterable
    {
        $from = BusinessTime::start($f['from']);
        $to = BusinessTime::end($f['to']);
        if ($kind === 'invoice') {
            return CustomerInvoice::where('issued_at', '>=', $from)->where('issued_at', '<', $to)
                ->whereHas('order', fn ($q) => $q->where('currency', 'CAD')
                    ->whereDoesntHave('payment', fn ($p) => $p->where('mode', 'sandbox'))
                    ->when($f['customer'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))
                    ->when($f['method'] ?? null, fn ($q, $v) => $q->where('payment_method', $v))
                    ->when($f['payment_status'] ?? null, fn ($q, $v) => $q->where('payment_status', $v)))
                ->with(['order.payment', 'order.settlements'])->lazyById(200);
        }
        if ($kind === 'account-receipt') {
            return DB::table('customer_receipt_events as d')->join('customer_receipts as r', 'r.id', '=', 'd.customer_receipt_id')
                ->selectRaw('d.id, r.customer_id, r.number AS reference, d.created_at AS recorded_at, d.snapshot')
                ->where('d.created_at', '>=', $from)->where('d.created_at', '<', $to)
                ->when($f['customer'] ?? null, fn ($q, $v) => $q->where('r.customer_id', $v))
                ->when($f['method'] ?? null, fn ($q, $v) => $q->whereExists(fn ($line) => $line->from('customer_receipt_lines')
                    ->whereColumn('customer_receipt_id', 'r.id')->where('method', $v)
                    ->where(fn ($q) => $q->whereNull('d.customer_receipt_line_id')->orWhereColumn('id', 'd.customer_receipt_line_id'))))
                ->when(isset($f['payment_status']), fn ($q) => $q->whereRaw('1 = 0'))
                ->lazyById(200, 'd.id', 'id');
        }
        [$table, $date, $select] = match ($kind) {
            'initial-payment' => ['orders', 'o.paid_at', "o.number AS reference, COALESCE(p.amount_cents, o.manual_collected_cents, o.subtotal_cents + o.delivery_cents + o.tax_cents) AS amount"],
            'settlement' => ['order_settlements', 'd.created_at', 'o.number AS reference, d.amount_cents AS amount'],
            'credit-note' => ['order_credits', 'd.created_at', 'd.number AS reference, d.amount_cents AS amount, d.tax_cents AS tax'],
            'refund' => ['customer_refund_events', 'd.created_at', 'r.number AS reference, d.snapshot'],
            'revision' => ['order_revisions', 'd.created_at', 'd.number AS reference, d.difference_cents AS amount, d.tax_difference_cents AS tax'],
        };
        $query = DB::table($table.' as d');
        if ($kind === 'refund') {
            $query->join('customer_refunds as r', 'r.id', '=', 'd.customer_refund_id')->join('orders as o', 'o.id', '=', 'r.order_id');
        } else {
            $query->join('orders as o', 'o.id', '=', $kind === 'initial-payment' ? 'd.id' : 'd.order_id');
        }
        $query->leftJoin('payments as p', fn ($q) => $q->on('p.order_id', '=', 'o.id')->whereNull('p.superseded_at'))
            ->where(fn ($q) => $q->whereNull('p.id')->orWhere('p.mode', 'live'))
            ->whereNull('o.deleted_at')->where('o.currency', 'CAD')->where($date, '>=', $from)->where($date, '<', $to)
            ->when($f['customer'] ?? null, fn ($q, $v) => $q->where('o.customer_id', $v))
            ->when($f['payment_status'] ?? null, fn ($q, $v) => $q->where('o.payment_status', $v))
            ->when($f['method'] ?? null, fn ($q, $v) => $q->where($kind === 'settlement' ? 'd.method' : 'o.payment_method', $v));

        return $query->selectRaw("d.id, o.customer_id, $date AS recorded_at, $select")->lazyById(200, 'd.id', 'id');
    }
}
