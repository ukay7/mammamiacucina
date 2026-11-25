<?php

namespace App\Services;

use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountingReports
{
    public const REPORTS = [
        'profit-loss' => 'Profit and Loss', 'balance-sheet' => 'Balance Sheet', 'cash-flow' => 'Cash Flow',
        'receivables' => 'Accounts Receivable Aging', 'payables' => 'Accounts Payable Aging', 'sales' => 'Sales by Customer and Product',
        'payments' => 'Payments and Receipts by Method', 'expenses' => 'Expenses by Category and Supplier',
        'inventory' => 'Inventory Valuation', 'cogs' => 'Cost of Goods Sold', 'purchases' => 'Purchases by Supplier',
        'tax' => 'GST/HST and Input Tax Credits', 'documents' => 'Invoices, Receipts, Credits, Refunds and Adjustments',
    ];

    public const FILTERS = [
        'profit-loss' => ['from', 'to'], 'balance-sheet' => ['to'], 'cash-flow' => ['from', 'to'],
        'receivables' => ['customer'], 'payables' => ['to', 'supplier'], 'sales' => ['from', 'to', 'customer', 'product', 'category', 'method', 'payment_status', 'invoice_status'],
        'payments' => ['from', 'to', 'customer', 'method', 'transaction', 'payment_status'], 'expenses' => ['from', 'to', 'supplier'],
        'inventory' => ['supplier', 'product', 'category'], 'cogs' => ['from', 'to', 'supplier'],
        'purchases' => ['from', 'to', 'supplier', 'product', 'category'], 'tax' => ['from', 'to', 'supplier'],
        'documents' => ['from', 'to', 'customer', 'document_type', 'invoice_status', 'payment_status', 'method'],
    ];

    public function __construct(private AccountingLedger $ledger, private ReceivableAging $aging, private BusinessReports $business) {}

    public function report(string $kind, array $f): array
    {
        return match ($kind) {
            'profit-loss', 'balance-sheet', 'cash-flow' => $this->statement($kind, $f),
            'receivables' => $this->receivables($f),
            'payables' => $this->payables($f),
            'sales' => $this->sales($f),
            'payments' => $this->payments($f),
            'expenses', 'cogs', 'tax' => $this->ledgerDetail($kind, $f),
            'inventory' => $this->inventory($f),
            'purchases' => $this->purchases($f),
            'documents' => app(AccountingDocuments::class)->report($f),
        };
    }

    private function statement(string $kind, array $f): array
    {
        $period = $this->ledger->entries($f);
        $asOf = $this->ledger->entries($f, false);
        $money = self::money(...);
        $flow = ['operating' => 'Operating activities', 'investing' => 'Investing activities',
            'financing' => 'Financing activities', 'transfer' => 'Bank transfers (net)', 'opening' => 'Opening entries in period'];
        if ($kind === 'cash-flow') {
            $opening = (int) (clone $asOf)->where('e.classification', 'cash')->where('j.posted_on', '<', $f['from'])->sum('e.amount_cents');
            $totals = $period->where('e.classification', 'cash')->groupBy('e.cash_flow')
                ->selectRaw('e.cash_flow, SUM(e.amount_cents) AS total')->pluck('total', 'cash_flow');
            $rows = [['Opening cash', $money($opening)]];
            foreach ($flow as $key => $label) $rows[] = [$label, $money($totals[$key] ?? 0)];
            $rows[] = ['Closing cash', $money($opening + $totals->sum())];
            return [['Cash flow', 'CAD'], $rows, $totals->isEmpty() && ! (clone $asOf)->where('e.classification', 'cash')->exists()
                ? 'No cash or bank entries have been imported for this report.'
                : 'Based on cash and bank entries in the imported CAD ledger.'];
        }

        $activity = ['revenue', 'cogs', 'expense'];
        $entries = ($kind === 'profit-loss' ? $period->whereIn('e.classification', $activity) : $asOf)
            ->select('e.classification', 'e.account')->selectRaw('SUM(e.amount_cents) AS total')
            ->groupBy('e.classification', 'e.account')->orderBy('e.classification')->orderBy('e.account')->get();
        $rows = [];
        $groups = array_fill_keys(['Assets', 'Liabilities', 'Equity', 'Revenue', 'Cost of goods sold', 'Expenses'], 0);
        foreach ($entries as $entry) {
            $group = match ($entry->classification) {
                'cash', 'asset', 'gst_itc' => 'Assets',
                'liability', 'payable', 'gst_payable', 'gst_collected' => 'Liabilities',
                'equity' => 'Equity', 'revenue' => 'Revenue', 'cogs' => 'Cost of goods sold', 'expense' => 'Expenses',
            };
            $total = (int) $entry->total * (in_array($group, ['Liabilities', 'Equity', 'Revenue']) ? -1 : 1);
            $groups[$group] += $total;
            if ($kind === 'profit-loss' || ! in_array($entry->classification, $activity)) {
                $rows[] = [$group, $entry->account, $money($total)];
            }
        }
        $earnings = $groups['Revenue'] - $groups['Cost of goods sold'] - $groups['Expenses'];
        if ($kind === 'profit-loss') {
            $rows[] = ['Total', 'Gross profit', $money($groups['Revenue'] - $groups['Cost of goods sold'])];
            $rows[] = ['Total', 'Net profit / loss', $money($earnings)];
        } else {
            $rows[] = ['Equity', 'Unclosed earnings through '.$f['to'], $money($earnings)];
            $rows[] = ['Total', 'Assets', $money($groups['Assets'])];
            $rows[] = ['Total', 'Liabilities and equity', $money($groups['Liabilities'] + $groups['Equity'] + $earnings)];
        }

        return [['Group', 'Account', 'CAD'], $rows, $entries->isEmpty()
            ? 'No ledger entries are available for this report. Import the accountant’s CAD ledger, including opening balances where applicable.'
            : 'Based on the imported CAD ledger. Completeness depends on the journals and opening balances supplied.'];
    }

    private function receivables(array $f): array
    {
        $aging = $this->aging->report(isset($f['customer']) ? (int) $f['customer'] : null);
        $rows = [];
        foreach ($aging['rows'] as $r) $rows[] = [$r['customer_id'], $r['name'], ...array_map(self::money(...), array_values($r['amounts']))];
        $rows[] = ['', 'Total', ...array_map(self::money(...), array_values($aging['totals']))];
        return [['Customer #', 'Customer', ...array_values(ReceivableAging::BUCKETS), 'Total due (CAD)'], $rows,
            'Current balances as of '.$aging['asOf']->toDateString().'. Aging uses due date when present, otherwise the invoice issue date.'];
    }

    private function payables(array $f): array
    {
        $open = $this->ledger->entries($f, false)->where('e.classification', 'payable')
            ->select('e.supplier_id', 's.name', 'e.document')->selectRaw('MIN(j.posted_on) AS first_on, MIN(CASE WHEN e.amount_cents < 0 THEN e.due_on END) AS due_on, -SUM(e.amount_cents) AS balance')
            ->groupBy('e.supplier_id', 's.name', 'e.document')->havingRaw('SUM(e.amount_cents) < 0')->orderBy('s.name')->orderBy('e.document')->get();
        $asOf = CarbonImmutable::parse($f['to'], 'UTC');
        $total = 0;
        $buckets = array_fill(0, 5, 0);
        $rows = [];
        foreach ($open as $r) {
            $due = $r->due_on ?: $r->first_on;
            $days = (int) CarbonImmutable::parse($due, 'UTC')->diffInDays($asOf);
            $bucket = match (true) { $days <= 0 => 0, $days <= 30 => 1, $days <= 60 => 2, $days <= 90 => 3, default => 4 };
            $amounts = array_fill(0, 5, 0);
            $amounts[$bucket] = $r->balance;
            $buckets[$bucket] += $r->balance;
            $rows[] = [$r->name, $r->document, $due, ...array_map(self::money(...), $amounts), self::money($r->balance)];
            $total += $r->balance;
        }
        $rows[] = ['', 'Total', '', ...array_map(self::money(...), $buckets), self::money($total)];
        return [['Supplier', 'Supplier invoice', 'Due / posted', ...array_values(ReceivableAging::BUCKETS), 'Total due (CAD)'], $rows,
            'Based on payable journal entries. Apply payments to the same supplier ID and invoice reference as the bill.'];
    }

    private function sales(array $f): array
    {
        $query = DB::table('order_items as l')->join('orders as o', 'o.id', '=', 'l.order_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')->leftJoin('customer_invoices as i', 'i.order_id', '=', 'o.id')
            ->whereNull('o.deleted_at')->where('o.currency', 'CAD')->whereIn('o.status', ['delivered', 'completed'])
            ->whereNotExists(fn ($q) => $q->from('payments as p')->whereColumn('p.order_id', 'o.id')->whereNull('p.superseded_at')->where('p.mode', 'sandbox'))
            ->where('o.ordered_at', '>=', BusinessTime::start($f['from']))->where('o.ordered_at', '<', BusinessTime::end($f['to']))
            ->when($f['customer'] ?? null, fn ($q, $v) => $q->where('o.customer_id', $v))
            ->when($f['method'] ?? null, fn ($q, $v) => $q->where('o.payment_method', $v))
            ->when($f['payment_status'] ?? null, fn ($q, $v) => $q->where('o.payment_status', $v));
        if (isset($f['invoice_status'])) {
            match ($f['invoice_status']) {
                'issued' => $query->whereNotNull('i.id'),
                'not_issued' => $query->whereNull('i.id'),
                default => $query->whereIn('o.id', $this->business->orders()->whereNotNull('balance_cents')
                    ->where('balance_cents', $f['invoice_status'] === 'outstanding' ? '>' : '<=', 0)->select('id'))->whereNotNull('i.id'),
            };
        }
        $name = "COALESCE(NULLIF(c.business_name, ''), c.name, o.email)";
        $rows = $this->productFilters($query, $f, 'l.product_id')->selectRaw("o.customer_id, $name AS customer, l.product_code, l.name, SUM(l.quantity) AS units, SUM(l.line_cents) AS total")
            ->groupBy('o.customer_id', DB::raw($name), 'l.product_code', 'l.name')->orderBy('customer')->orderBy('l.name')->get()
            ->map(fn ($r) => [$r->customer_id, $r->customer, $r->product_code, $r->name, $r->units, self::money($r->total)])->all();
        return [['Customer #', 'Customer', 'Code', 'Product', 'Quantity', 'Line sales (CAD)'], $rows,
            'Delivered and completed orders by order date. Line values reflect line discounts. Order-level discounts, delivery, tax and credits are outside these line amounts.'];
    }

    private function payments(array $f): array
    {
        $ledgerFilters = $f;
        $ledgerFilters['customer'] = isset($f['customer']) ? 'id:'.$f['customer'] : null;
        $rows = $this->business->cash($ledgerFilters)
            ->when($f['transaction'] ?? null, fn ($q, $v) => $q->where('m.entry_kind', $v))
            ->when($f['payment_status'] ?? null, fn ($q, $v) => $q->where('o.payment_status', $v))
            ->select('m.method', 'm.entry_type', 'm.provider')->selectRaw('COUNT(*) AS entries, SUM(m.amount_cents) AS total')
            ->groupBy('m.method', 'm.entry_type', 'm.provider')->orderBy('m.method')->orderBy('m.entry_type')->get()
            ->map(fn ($r) => [$r->method, $r->provider, $r->entry_type, $r->entries, self::money($r->total)])->all();
        return [['Method', 'Processor', 'Transaction', 'Allocation entries', 'Net CAD'], $rows,
            'Includes dated payments, refunds, reversals and adjustments. A receipt split across invoices may have several allocation entries.'];
    }

    private function ledgerDetail(string $kind, array $f): array
    {
        $classes = match ($kind) { 'tax' => ['gst_collected', 'gst_itc'], 'cogs' => ['cogs'], default => ['expense'] };
        $entries = $this->ledger->entries($f)->whereIn('e.classification', $classes);
        if ($kind === 'tax') {
            $rows = $entries->select('e.classification', 'e.account', 's.name', 'e.document', 'j.posted_on')
                ->selectRaw('SUM(e.amount_cents) AS total')->groupBy('e.classification', 'e.account', 's.name', 'e.document', 'j.posted_on')
                ->orderBy('j.posted_on')->get()->map(fn ($r) => [
                    AccountingLedger::LABELS[$r->classification], $r->account, $r->name, $r->document, $r->posted_on,
                    self::money($r->classification === 'gst_collected' ? -$r->total : $r->total), '',
                ])->all();
            if (! isset($f['supplier'])) {
                foreach (['invoice' => 'Tax charged on invoice', 'credit-note' => 'Tax credited on sale'] as $type => $label) {
                    [, $documents] = app(AccountingDocuments::class)->report($f + ['document_type' => $type]);
                    foreach ($documents as [$date, , $reference, , , $tax, , $url]) {
                        if ($tax !== '' && (float) $tax !== 0.0) {
                            $rows[] = [$label, '', '', $reference, $date, $type === 'credit-note' ? '-'.$tax : $tax, $url];
                        }
                    }
                }
            }
            return [['Tax entry', 'Account', 'Supplier', 'Document', 'Recorded', 'CAD', 'PDF'], $rows,
                'Invoice and credit note rows show sales tax charged or credited. Collected tax and approved input tax credits come from the imported ledger. Review supporting records with your accountant before filing.'];
        }
        $rows = $entries->select('e.account', 's.name')->selectRaw('SUM(e.amount_cents) AS total')
            ->groupBy('e.account', 's.name')->orderBy('e.account')->orderBy('s.name')->get()
            ->map(fn ($r) => [$r->account, $r->name, self::money($r->total)])->all();
        return [['Category / Ledger account', 'Supplier', 'CAD'], $rows, 'Based on the imported CAD ledger.'];
    }

    private function inventory(array $f): array
    {
        $valuation = app(InventoryValuation::class);
        $query = $valuation->query(app(InventoryProducts::class), ['record_id' => $f['product'] ?? null, 'category' => $f['category'] ?? null])
            ->when($f['supplier'] ?? null, fn ($q, $v) => $q->where('products.supplier_id', $v));
        $rows = [];
        foreach ($valuation->rows($query, []) as $product) {
            $rows[] = array_values($valuation->values($product, false));
        }
        return [array_values(InventoryValuation::BASE_COLUMNS), $rows,
            'Current inventory at configured supplier costs, in each product’s purchase currency. Historical cost of goods sold comes from the ledger.'];
    }

    private function purchases(array $f): array
    {
        $query = DB::table('supplier_order_items as l')->join('supplier_orders as o', 'o.id', '=', 'l.supplier_order_id')
            ->join('suppliers as s', 's.id', '=', 'o.supplier_id')->whereIn('o.status', ['received', 'completed'])
            ->when($f['supplier'] ?? null, fn ($q, $v) => $q->where('o.supplier_id', $v));
        $date = 'o.completed_at';
        $quantity = 'l.quantity';
        if (Schema::hasTable('supplier_order_receipts') && Schema::hasTable('supplier_order_receipt_items')) {
            $query->leftJoin('supplier_order_receipts as r', 'r.supplier_order_id', '=', 'o.id')
                ->leftJoin('supplier_order_receipt_items as ri', 'ri.supplier_order_item_id', '=', 'l.id');
            $date = 'COALESCE(r.received_at, o.completed_at)';
            $quantity = 'CASE WHEN r.id IS NULL THEN l.quantity ELSE COALESCE(ri.quantity, 0) END';
        }
        $query->whereRaw("$date >= ?", [BusinessTime::start($f['from'])])->whereRaw("$date < ?", [BusinessTime::end($f['to'])]);
        $rows = $this->productFilters($query, $f, 'l.product_id')->select('s.id', 's.name', 'l.currency')
            ->selectRaw("COUNT(DISTINCT o.id) AS orders, SUM($quantity) AS units, SUM(ROUND(($quantity) * l.unit_cost, 2)) AS cost")
            ->groupBy('s.id', 's.name', 'l.currency')->orderBy('s.name')->orderBy('l.currency')->get()
            ->map(fn ($r) => [$r->id, $r->name, $r->currency, $r->orders, $r->units, number_format($r->cost, 2, '.', '')])->all();
        return [['Supplier #', 'Supplier', 'Currency', 'Received orders', 'Units', 'Purchase value'], $rows,
            'Uses received quantities and recorded purchase-order costs. Supplier bills and balances are tracked in the ledger.'];
    }

    private function productFilters($query, array $f, string $product)
    {
        return $query->when($f['product'] ?? null, fn ($q, $v) => $q->where($product, $v))
            ->when($f['category'] ?? null, fn ($q, $v) => $q->whereExists(fn ($membership) => $membership->from('category_product')
                ->whereColumn('product_id', $product)->where('category_id', $v)));
    }

    public static function money(int|float|string|null $cents): string
    {
        return $cents === null ? '' : number_format($cents / 100, 2, '.', '');
    }
}
