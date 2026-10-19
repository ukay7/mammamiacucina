<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\BusinessReports;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BusinessReportController extends Controller
{
    private function authorizeReport(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->hasAdminPermission('orders.view'), 403);
    }

    private function filters(Request $request, string $kind): array
    {
        $this->authorizeReport($request);
        abort_unless(isset(BusinessReports::TYPES[$kind]), 404);
        $f = $request->validate([
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'method' => ['nullable', Rule::in(['cash', 'card', 'paypal', 'etransfer'])], 'source' => 'nullable|in:website,pos',
            'provider' => 'nullable|in:manual,helcim,stripe,paypal', 'q' => 'nullable|string|max:120', 'customer' => ['nullable', 'string', 'max:300', 'regex:/^(id:[1-9][0-9]*|email:.+)$/'],
            'balance' => 'nullable|in:due,refund', 'fulfillment' => 'nullable|in:pickup,delivery',
            'schedule' => 'nullable|in:scheduled,overdue,unscheduled',
        ]);
        $f['from'] = $f['from'] ?? ($kind === 'fulfilment' ? today() : today()->startOfMonth())->toDateString();
        $f['to'] = $f['to'] ?? ($kind === 'fulfilment' ? today()->addDays(7) : today())->toDateString();
        if ($f['to'] < $f['from'] || Carbon::parse($f['from'])->diffInDays($f['to']) > 366) {
            throw ValidationException::withMessages(['to' => 'Choose a date range of up to 366 days, with the end after the start.']);
        }

        return $f;
    }

    public function index(Request $request, BusinessReports $reports)
    {
        $this->authorizeReport($request);
        $today = ['from' => today()->toDateString(), 'to' => today()->toDateString()];
        $sales = $reports->report('sales', $today)['summary'];
        $cash = $reports->report('payments', $today)['summary'];
        $balances = $reports->orderSummary($reports->orders());

        return view('admin.reports.home', compact('sales', 'cash', 'balances'));
    }

    public function show(Request $request, BusinessReports $reports, string $kind)
    {
        $filters = $this->filters($request, $kind);
        $data = $reports->report($kind, $filters);
        $rows = $data['rows']->paginate(30)->withQueryString();

        return view('admin.reports.business', compact('kind', 'filters', 'rows') + $data);
    }

    public function detail(Request $request, BusinessReports $reports, Order $order)
    {
        $this->authorizeReport($request);
        abort_unless($reports->orders()->where('id', $order->id)->exists(), 404);
        $order->load(['items', 'payment', 'settlements']);
        $movements = $reports->cash([], false)->where('o.id', $order->id)->select('m.*')->orderByDesc('m.occurred_at')->limit(100)->get();

        return response()->view('admin.reports.order-detail', compact('order', 'movements'))->header('Cache-Control', 'private, no-store');
    }

    public function schedule(Request $request, BusinessReports $reports, Order $order)
    {
        $this->authorizeReport($request);
        abort_unless($request->user()->hasAdminPermission('orders.manage'), 403);
        abort_unless($reports->orders()->where('id', $order->id)->exists(), 404);
        $data = $request->validate(['fulfillment_date' => 'nullable|date_format:Y-m-d', 'revision' => 'required|integer|min:0']);
        DB::transaction(function () use ($request, $order, $data) {
            $current = Order::lockForUpdate()->findOrFail($order->id);
            if ($current->revision != $data['revision'] || in_array($current->status, ['cancelled', 'completed', 'delivered'])) {
                throw ValidationException::withMessages(['fulfillment_date' => 'Order changed or is closed. Reload before scheduling.']);
            }
            $before = $current->fulfillment_date ?: 'Unscheduled';
            $current->update(['fulfillment_date' => $data['fulfillment_date'] ?? null, 'revision' => $current->revision + 1]);
            $current->events()->create(['user_id' => $request->user()->id, 'description' => 'Planned delivery / pickup: '.$before.' → '.($data['fulfillment_date'] ?: 'Unscheduled'), 'created_at' => now()]);
        });

        return back()->with('status', 'Planned delivery / pickup date saved.');
    }

    public function export(Request $request, BusinessReports $reports, string $kind)
    {
        $f = $this->filters($request, $kind);
        $query = $reports->report($kind, $f)['rows'];

        return response()->streamDownload(function () use ($query, $kind) {
            $out = fopen('php://output', 'w');
            $headers = match ($kind) {
                'payments' => ['Order', 'Customer', 'Recorded at', 'Method', 'Provider', 'Entry', 'Amount CAD', 'Reference'],
                'methods' => ['Method', 'Provider', 'Transactions', 'Orders', 'Received CAD', 'Refunded CAD', 'Net CAD'],
                'customers' => ['Customer', 'Email', 'Orders', 'Order value CAD', 'Net received CAD', 'Due CAD', 'Refund due CAD', 'Unconfirmed totals'],
                default => ['Order', 'Customer', 'Email', 'Order date', 'Status', 'Method', 'Source', 'Fulfillment', 'Planned date', 'Order total CAD', 'Net received CAD', 'Amount due CAD', 'Refund due CAD'],
            };
            fputcsv($out, $headers, ',', '"', '');
            $money = fn ($v) => $v === null ? 'Unconfirmed' : number_format($v / 100, 2, '.', '');
            foreach ($query->cursor() as $r) {
                $values = match ($kind) {
                    'payments' => [$r->number, $r->first_name.' '.$r->last_name, $r->occurred_at, $r->method, $r->provider, $r->entry_type, $money($r->amount_cents), $r->reference],
                    'methods' => [$r->method, $r->provider, $r->transactions, $r->orders, $money($r->received), $money($r->refunded), $money($r->net)],
                    'customers' => [$r->first_name.' '.$r->last_name, $r->email, $r->orders, $money($r->value), $money($r->received), $money($r->due), $money($r->refund_due), $r->unpriced],
                    default => [$r->number, $r->first_name.' '.$r->last_name, $r->email, $r->ordered_at, Order::STATUSES[$r->status] ?? $r->status, $r->payment_method, $r->source, $r->fulfillment, $r->fulfillment_date, $money($r->total_cents), $money($r->net_cents), $money($r->balance_cents === null ? null : max(0, $r->balance_cents)), $money($r->balance_cents === null ? null : max(0, -$r->balance_cents))],
                };
                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+@\-\t\r]/', (string) $v) ? "'".$v : ($v ?? ''), $values), ',', '"', '');
            }
            fclose($out);
        }, 'report-'.$kind.'-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
