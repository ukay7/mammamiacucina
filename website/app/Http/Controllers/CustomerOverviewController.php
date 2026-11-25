<?php

namespace App\Http\Controllers;

use App\Services\CustomerDocuments;
use App\Services\CustomerInvoicing;
use App\Services\ReceivableAging;
use Illuminate\Http\Request;

class CustomerOverviewController extends Controller
{
    public function dashboard(Request $request, CustomerInvoicing $billing, ReceivableAging $aging)
    {
        $customer = $request->user()->customerRecord();
        $credit = $billing->balances($customer);
        $outstanding = $aging->report($customer->id)['totals']['total'];
        $orders = $customer->orders()->latest('ordered_at')->latest('id')->limit(5)->get();

        return view('customer.dashboard', compact('customer', 'credit', 'outstanding', 'orders'));
    }

    public function credit(Request $request, CustomerInvoicing $billing)
    {
        $customer = $request->user()->customerRecord();
        $credit = $billing->balances($customer);
        $application = $customer->creditApplications()->latest('id')->first(['id', 'status']);

        return view('customer.credit', compact('customer', 'credit', 'application'));
    }

    public function receipts(Request $request, CustomerDocuments $documents)
    {
        $request->merge(['document_type' => 'receipts']);
        $user = $request->user();

        return view('customer.receipts', ['documents' => $documents->listing($user->customerRecord(), $user, $request)]);
    }
}
