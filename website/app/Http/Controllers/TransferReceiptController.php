<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderManagement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TransferReceiptController extends Controller
{
    private function authorizeOrder(Request $request, Order $order, bool $write = false): void
    {
        $user = $request->user();
        abort_unless($user && $user->is_active, 403);
        if ($user->isCustomer()) {
            abort_unless((int) $order->customer_id === $user->customerRecord()->id, 404);
        } else {
            abort_unless($user->hasAdminPermission($write ? 'orders.manage' : 'orders.view') || ($order->source === 'pos' && (int)$order->created_by === $user->id && $user->hasAdminPermission('pos.manage')), 403);
        }
        abort_unless($order->payment_method === 'etransfer', 404);
    }

    public function store(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order, true);
        $data = $request->validate(['revision'=>'required|integer|min:0', 'receipt'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);
        $path = $request->file('receipt')->store('transfer-receipts', 'local');
        if (!$path) throw ValidationException::withMessages(['receipt'=>'Unable to store your receipt. Please try again.']);
        $old = null;
        try {
            DB::transaction(function () use ($order, $data, $path, $request, &$old) {
                $locked = Order::lockForUpdate()->findOrFail($order->id);
                if ((int) $locked->revision !== (int) $data['revision'] || $locked->payment_status !== 'unpaid' || $locked->status !== 'transfer_pending') {
                    throw ValidationException::withMessages(['receipt'=>'This order changed or its payment was already verified. Reload before uploading.']);
                }
                $old = $locked->transfer_receipt_path;
                $locked->update(['transfer_receipt_path'=>$path, 'transfer_receipt_uploaded_at'=>now(), 'revision'=>$locked->revision + 1]);
                $locked->events()->create(['user_id'=>$request->user()->id, 'description'=>'E-transfer receipt '.($old ? 'replaced' : 'submitted').'; awaiting admin payment verification.', 'created_at'=>now()]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
        if ($old) Storage::disk('local')->delete($old);
        if ($request->expectsJson()) return response()->json(['message'=>'Receipt submitted. Awaiting admin verification.']);
        return back()->with('receipt_status', 'Receipt submitted. Your payment is awaiting admin verification.');
    }

    public function show(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);
        $disk = Storage::disk('local');
        abort_unless($order->transfer_receipt_path && $disk->exists($order->transfer_receipt_path), 404);
        return response()->file($disk->path($order->transfer_receipt_path), [
            'Cache-Control'=>'no-store, private', 'X-Content-Type-Options'=>'nosniff',
            'Content-Security-Policy'=>"sandbox; default-src 'none'",
        ])->setPrivate();
    }

    public function verify(Request $request, Order $order, OrderManagement $service)
    {
        $this->authorizeOrder($request, $order, true);
        abort_unless($request->user()->hasAdminPermission('orders.manage'), 403);
        $data = $request->validate(['revision'=>'required|integer|min:0', 'received'=>'accepted']);
        if ($order->status !== 'transfer_pending' || $order->payment_status !== 'unpaid') {
            throw ValidationException::withMessages(['receipt'=>'This order has already changed. Reload to review it.']);
        }
        $service->update($order, [
            'revision'=>$data['revision'], 'status'=>'warehouse_pending', 'payment_status'=>'paid',
            'delivery'=>number_format($order->delivery_cents/100,2,'.',''),
            'tax'=>number_format($order->tax_cents/100,2,'.',''),
            'reason'=>'Admin verified receipt and confirmed e-transfer funds received; sent to warehouse.',
        ], $request->user()->id);
        return back()->with('receipt_status', 'Payment verified. Order sent to warehouse.');
    }
}
