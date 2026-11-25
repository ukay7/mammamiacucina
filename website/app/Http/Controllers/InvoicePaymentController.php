<?php

namespace App\Http\Controllers;

use App\Models\InvoicePayment;
use App\Services\HelcimGateway;
use App\Services\InvoicePayments;
use App\Services\ReceivableAging;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoicePaymentController extends Controller
{
    public function invoices(Request $request, InvoicePayments $payments, ReceivableAging $aging)
    {
        $customer = $request->user()->customerRecord();
        $invoices = $payments->invoices($customer)->latest('issued_at')->latest('id')->paginate(20);
        $today = BusinessTime::today()->toDateString();
        $date = CarbonImmutable::parse($today, 'UTC');
        $rows = $invoices->getCollection()->mapWithKeys(function ($invoice) use ($payments, $aging, $date) {
            $issued = BusinessTime::format($invoice->issued_at, 'Y-m-d');
            $due = $invoice->paymentDueDate();
            return [$invoice->id => [
                'balance' => $aging->invoiceBalance($invoice), 'payable' => $payments->payableBalance($invoice), 'due' => $due,
                'outstanding_days' => max(0, (int) CarbonImmutable::parse($issued, 'UTC')->diffInDays($date)),
                'overdue_days' => $due ? max(0, (int) CarbonImmutable::parse($due, 'UTC')->diffInDays($date)) : null,
            ]];
        });
        $active = $payments->active($customer);
        $canPay = $payments->canPay($customer);
        $payable = [];
        $outstanding = 0;
        foreach ($payments->invoices($customer)->lazyById(150) as $invoice) {
            $outstanding += $aging->invoiceBalance($invoice);
            if ($canPay && ! $active && ($amount = $payments->payableBalance($invoice))) {
                $payable[] = ['id' => $invoice->id, 'label' => $invoice->label, 'order' => $invoice->order->number, 'amount' => $amount];
            }
        }
        $recentPayments = InvoicePayment::where('customer_id', $customer->id)->with('allocations')->latest('id')->limit(5)->get();

        return view('customer.invoices', compact('invoices', 'rows', 'active', 'canPay', 'payable', 'outstanding', 'recentPayments'));
    }

    public function start(Request $request, InvoicePayments $payments)
    {
        $data = $request->validate(['invoice_ids' => 'required|array|min:1|max:100', 'invoice_ids.*' => 'required|integer|min:1|distinct',
            'expected_total' => 'required|integer|min:1|max:999999999']);
        $payment = $payments->start($request->user()->customerRecord(), array_map('intval', $data['invoice_ids']), (int) $data['expected_total']);

        return redirect()->route('customer.invoice-payments.show', $payment->reference);
    }

    public function show(Request $request, InvoicePayment $invoicePayment, InvoicePayments $payments)
    {
        $this->owns($request, $invoicePayment);

        return $this->details($invoicePayment, false, $payments->canPay($request->user()->customerRecord()));
    }

    public function confirm(Request $request, InvoicePayment $invoicePayment, HelcimGateway $gateway, InvoicePayments $payments)
    {
        $this->owns($request, $invoicePayment);
        $data = $request->validate(['transaction_id' => ['required', 'string', 'regex:/^[0-9]{1,20}$/D']]);
        try {
            $transaction = $gateway->transaction($invoicePayment->mode, $data['transaction_id']);
            if (($transaction['invoiceNumber'] ?? '') !== $invoicePayment->provider_invoice) {
                return response()->json(['message' => 'This charge does not belong to the selected invoices.'], 422);
            }
            $payment = $payments->sync($invoicePayment);
        } catch (\RuntimeException | \Illuminate\Http\Client\ConnectionException) {
            return response()->json(['message' => 'Confirmation is pending. Use Check payment status before trying again.'], 503);
        }

        return response()->json(['paid' => $payment->status === 'paid', 'redirect' => route('customer.invoice-payments.show', $payment->reference)]);
    }

    public function check(Request $request, InvoicePayment $invoicePayment, InvoicePayments $payments)
    {
        $this->owns($request, $invoicePayment);

        return $this->refresh($invoicePayment, $payments);
    }

    public function adminIndex(Request $request)
    {
        $filter = $request->validate(['status' => ['nullable', Rule::in(array_keys(InvoicePayment::STATUSES))]])['status'] ?? '';
        $payments = InvoicePayment::with('customer:id,name,business_name')->when($filter, fn ($q) => $q->where('status', $filter))
            ->latest('id')->paginate(25)->withQueryString();

        return view('admin.invoice-payments', compact('filter', 'payments'));
    }

    public function adminShow(InvoicePayment $invoicePayment)
    {
        return $this->details($invoicePayment, true, false);
    }

    public function adminCheck(InvoicePayment $invoicePayment, InvoicePayments $payments)
    {
        return $this->refresh($invoicePayment, $payments);
    }

    private function details(InvoicePayment $payment, bool $staff, bool $canPay)
    {
        $payment->load(['customer', 'allocations', 'receipt.events', 'events']);

        return view('customer.invoice-payment', compact('payment', 'staff', 'canPay'));
    }

    private function refresh(InvoicePayment $payment, InvoicePayments $payments)
    {
        try {
            $payments->sync($payment);
        } catch (\RuntimeException | \Illuminate\Http\Client\ConnectionException) {
            return back()->withErrors(['payment' => 'Helcim did not confirm the payment status. Check again later.']);
        }

        return back()->with('status', 'Payment status checked.');
    }

    private function owns(Request $request, InvoicePayment $payment): void
    {
        abort_unless((int) $payment->customer_id === $request->user()->customerRecord()->id, 404);
    }
}
