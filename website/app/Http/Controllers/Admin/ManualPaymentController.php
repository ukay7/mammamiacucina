<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ManualPaymentControls;
use Illuminate\Http\Request;

class ManualPaymentController extends Controller
{
    public function method(Request $request, Order $order, ManualPaymentControls $controls)
    {
        $controls->changeMethod($order, $request->all(), $request->user());

        return back()->with('status', 'Payment method changed. The outstanding amount remains unpaid. No money or stock was moved.');
    }

    public function adjust(Request $request, Order $order, ManualPaymentControls $controls)
    {
        $controls->adjust($order, $request->all(), $request->user());

        return back()->with('status', 'Payment correction / reversal recorded with its reason and audit history. No money or stock was moved.');
    }
}
