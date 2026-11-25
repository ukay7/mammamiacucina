<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Category, Supplier};
use App\Services\{AccountingDocuments, AccountingLedger, AccountingReports};
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountingController extends Controller
{
    public function index()
    {
        return view('admin.accounting.index');
    }

    public function report(Request $request, string $kind, AccountingReports $reports)
    {
        abort_unless(isset(AccountingReports::REPORTS[$kind]), 404);
        $active = AccountingReports::FILTERS[$kind];
        $rules = [
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d',
            'customer' => 'nullable|integer|exists:customers,id', 'supplier' => 'nullable|integer|exists:suppliers,id',
            'product' => 'nullable|integer|exists:products,id', 'category' => 'nullable|integer|exists:categories,id',
            'method' => 'nullable|in:cash,card,paypal,etransfer,cheque,credit',
            'transaction' => 'nullable|in:payment,refund,reversal,bounced_cheque,correction_debit,correction_credit',
            'payment_status' => ['nullable', Rule::in(['unpaid', 'pending', 'paid', 'partially_refunded', 'refunded', 'failed', 'cancelled', 'review'])],
            'invoice_status' => $kind === 'sales' ? 'nullable|in:issued,not_issued,outstanding,settled' : 'nullable|in:outstanding,settled',
            'document_type' => ['nullable', Rule::in(array_keys(AccountingDocuments::TYPES))],
        ];
        $filters = array_filter($request->validate(array_intersect_key($rules, array_flip($active)) + ['format' => 'nullable|in:csv,print']),
            fn ($value) => $value !== null);
        $filters['from'] ??= BusinessTime::today()->startOfMonth()->toDateString();
        $filters['to'] ??= BusinessTime::today()->toDateString();
        if (in_array('from', $active) && ($filters['to'] < $filters['from']
            || CarbonImmutable::parse($filters['from'])->diffInDays(CarbonImmutable::parse($filters['to'])) > 366)) {
            throw ValidationException::withMessages(['to' => 'Choose an ordered date range of up to 367 calendar days.']);
        }
        $title = AccountingReports::REPORTS[$kind];
        [$headers, $rows, $description] = $reports->report($kind, $filters);
        if (($filters['format'] ?? null) === 'csv') {
            return $this->csv($kind.'.csv', $headers, $rows);
        }
        if (($filters['format'] ?? null) === 'print') {
            return view('admin.accounting.print', compact('title', 'headers', 'rows', 'description', 'filters', 'active'));
        }
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(array_slice($rows, ($page - 1) * 30, 30), count($rows), 30, $page, ['path' => $request->url()]);
        $paginator->withQueryString();
        $suppliers = in_array('supplier', $active) ? Supplier::orderBy('name')->pluck('name', 'id') : collect();
        $categories = in_array('category', $active) ? Category::orderBy('name')->pluck('name', 'id') : collect();

        return view('admin.accounting.report', compact('kind', 'title', 'headers', 'description', 'paginator', 'active', 'filters', 'suppliers', 'categories'));
    }

    public function template()
    {
        abort_unless(auth()->user()->isSuper(), 403);
        return $this->csv('accounting-journal-template.csv', AccountingLedger::HEADERS, []);
    }

    public function import(Request $request, AccountingLedger $ledger)
    {
        abort_unless($request->user()->isSuper(), 403);
        $request->validate(['ledger' => 'required|file|max:3072|extensions:csv']);
        $count = $ledger->import($request->file('ledger')->getRealPath(), $request->user());

        return back()->with('status', $count.' balanced journals posted.');
    }

    private function csv(string $name, array $headers, array $rows)
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers, escape: '');
            foreach ($rows as $row) {
                fputcsv($file, array_map(function ($value) {
                    $text = (string) $value;
                    if (! is_numeric($text) && preg_match('/^\s*[=+\-@]|^[\t\r\n]/', $text)) return "'".$text;
                    return $text;
                }, $row), escape: '');
            }
            fclose($file);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
