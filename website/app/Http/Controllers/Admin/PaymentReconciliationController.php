<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PaymentReconciliation;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentReconciliationController extends Controller
{
    public function index(Request $request, PaymentReconciliation $reconciliation)
    {
        $data = $request->validate(['week' => 'nullable|date_format:Y-m-d']);
        $week = isset($data['week']) ? $this->monday($data['week']) : BusinessTime::today()->startOfWeek()->toDateString();
        $end = CarbonImmutable::parse($week)->addDays(6)->toDateString();
        $period = DB::table('payment_reconciliations')->where('week_start', $week)->first();
        $reviews = $period ? $reconciliation->reviews($period->id) : collect();
        $source = $reconciliation->source($week);
        $outstanding = $source->filter(fn ($row, $key) => $reviews->get($key)?->first()->fingerprint !== $row['fingerprint'])->count();
        $history = DB::table('payment_reconciliations')->whereNotNull('completed_at')->orderByDesc('week_start')->limit(8)->get();

        return view('admin.accounting.reconciliation', compact('week', 'end', 'period', 'source', 'reviews', 'outstanding', 'history'));
    }

    public function record(Request $request, PaymentReconciliation $reconciliation)
    {
        $data = $request->validate([
            'week' => 'required|date_format:Y-m-d', 'movement' => 'required|string|max:60',
            'fingerprint' => 'required|string|size:64', 'status' => ['required', Rule::in(array_keys(PaymentReconciliation::STATUSES))],
            'matched_cad' => ['nullable', 'regex:/^-?\d{1,10}(\.\d{1,2})?$/D'],
            'bank_reference' => 'required_if:status,matched,partial|nullable|string|max:150',
            'notes' => 'required_unless:status,matched|nullable|string|max:2000',
        ]);
        $week = $this->monday($data['week']);
        $reconciliation->record($week, $request->user(), $data);

        return redirect()->route('admin.payment-reconciliation.index', ['week' => $week])->with('status', 'Transaction review saved.');
    }

    public function complete(Request $request, PaymentReconciliation $reconciliation)
    {
        $data = $request->validate(['week' => 'required|date_format:Y-m-d', 'notes' => 'nullable|string|max:4000']);
        $week = $this->monday($data['week']);
        $reconciliation->complete($week, $request->user(), $data['notes'] ?? null);

        return redirect()->route('admin.payment-reconciliation.index', ['week' => $week])->with('status', 'Weekly review completed.');
    }

    private function monday(string $day): string
    {
        return CarbonImmutable::parse($day, BusinessTime::zone())->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
    }
}
