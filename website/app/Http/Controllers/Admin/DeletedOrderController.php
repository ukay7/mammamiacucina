<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderDeletion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeletedOrderController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isSuper(), 403);
        $data = $request->validate(['q' => 'nullable|string|max:200', 'per_page' => 'nullable|in:10,20,50,100']);
        $search = trim($data['q'] ?? '');
        $orders = Order::onlyTrashed()->with('deletedBy')->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
            ->where('number', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')
            ->orWhere('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%')))
            ->orderByDesc('deleted_at')->orderByDesc('id')->paginate((int) ($data['per_page'] ?? 20))->withQueryString();

        return view('admin.orders.deleted', compact('orders', 'search'));
    }

    public function show(Request $request, Order $deletedOrder)
    {
        abort_unless($request->user()->isSuper(), 403);
        abort_unless($deletedOrder->trashed(), 404);
        $order = $deletedOrder->load(['items', 'deletedBy', 'payment', 'paymentAttempts', 'invoice', 'settlements.author', 'events.author']);
        $refunds = $order->payment ? DB::table('payment_refunds')->where('payment_id', $order->payment->id)->orderBy('id')->get() : collect();

        return view('admin.orders.deleted-show', compact('order', 'refunds'));
    }

    public function destroy(Request $request, Order $order, OrderDeletion $deletion)
    {
        $deletion->remove($order, $request->all(), $request->user());

        return redirect()->route('admin.orders.deleted.index')->with('status', $order->number.' deleted. Its retained records are read-only.');
    }
}
