<?php

namespace App\Services;

use App\Models\CustomerInvoice;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;

final class ReceivableAging
{
    public const BUCKETS = [
        'current' => 'Current',
        'days_1_30' => '1–30 Days',
        'days_31_60' => '31–60 Days',
        'days_61_90' => '61–90 Days',
        'days_91_plus' => '91+ Days',
    ];

    public function report(?int $customerId = null, ?int $invoiceId = null): array
    {
        $asOf = BusinessTime::today();
        $calendarDay = CarbonImmutable::parse($asOf->toDateString(), 'UTC');
        $totals = array_fill_keys([...array_keys(self::BUCKETS), 'total'], 0);
        $rows = [];
        $invoices = CustomerInvoice::query()
            ->with(['order.customer', 'order.payment', 'order.settlements'])
            ->whereHas('order', fn ($query) => $query
                ->where('currency', 'CAD')
                ->where('status', '!=', 'cancelled')
                ->when($customerId !== null, fn ($orders) => $orders->where('customer_id', $customerId)))
            ->when($invoiceId !== null, fn ($query) => $query->whereKey($invoiceId));

        foreach ($invoices->lazyById(200) as $invoice) {
            $balance = $this->invoiceBalance($invoice);
            if ($balance === 0) {
                continue;
            }

            $order = $invoice->order;
            $customer = $order->customer;
            $key = $customer ? 'customer-'.$customer->id : 'invoice-'.$invoice->id;
            $rows[$key] ??= [
                'customer_id' => $customer?->id,
                'name' => $customer?->business_name ?: ($customer?->name ?: trim($order->first_name.' '.$order->last_name)),
                'business' => $customer?->account_type === 'business',
                'amounts' => array_fill_keys(array_keys($totals), 0),
            ];
            $basis = $invoice->paymentDueDate() ?: BusinessTime::format($invoice->issued_at, 'Y-m-d');
            $days = CarbonImmutable::parse($basis, 'UTC')->diffInDays($calendarDay);
            $bucket = match (true) {
                $days <= 0 => 'current',
                $days <= 30 => 'days_1_30',
                $days <= 60 => 'days_31_60',
                $days <= 90 => 'days_61_90',
                default => 'days_91_plus',
            };

            $rows[$key]['amounts'][$bucket] += $balance;
            $rows[$key]['amounts']['total'] += $balance;
            $totals[$bucket] += $balance;
            $totals['total'] += $balance;
        }

        $rows = array_values($rows);
        usort($rows, fn ($a, $b) => ($a['customer_id'] ?? PHP_INT_MAX) <=> ($b['customer_id'] ?? PHP_INT_MAX));

        return ['asOf' => $asOf, 'rows' => $rows, 'totals' => $totals];
    }

    public function invoiceBalance(CustomerInvoice $invoice): int
    {
        $order = $invoice->order;
        if (!$order || $order->trashed() || $order->status === 'cancelled' || $order->currency !== 'CAD' || $order->payment?->mode === 'sandbox') {
            return 0;
        }

        return max(0, $order->balance_cents);
    }
}
