<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderManagement;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $r)
    {
        $search = mb_substr((string) $r->input('q', ''), 0, 200);
        $dates = $r->validate(['from' => 'sometimes|required|date_format:Y-m-d', 'to' => 'sometimes|required|date_format:Y-m-d']);
        $from = $dates['from'] ?? now()->startOfMonth()->toDateString();
        $to = $dates['to'] ?? now()->endOfMonth()->toDateString();
        if ($to < $from) {
            throw \Illuminate\Validation\ValidationException::withMessages(['to' => 'The To date must be on or after the From date.']);
        }
        $orders = Order::where('created_at', '>=', $from.' 00:00:00')->where('created_at', '<', \Illuminate\Support\Carbon::parse($to)->addDay()->startOfDay())->when($search, fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->orWhere('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%')))->latest('id')->paginate(20)->withQueryString();

        return view('admin.orders.index', compact('orders', 'search', 'from', 'to'));
    }

    public function printOrder(Order $order)
    {
        $order->load('items');

        return response()->view('orders.print', compact('order'))->header('Cache-Control', 'no-store, private');
    }

    public function updateStatus(Request $r, Order $order, OrderManagement $service)
    {
        $d=$r->validate(['revision'=>'required|integer|min:0','status'=>'required|in:placed,confirmed,preparing,out_for_delivery,delivered,cancelled','q'=>'nullable|string|max:200','page'=>'nullable|integer|min:1','from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d']);
        $service->update($order,[
            'revision'=>$d['revision'],'status'=>$d['status'],
            'payment_status'=>$order->payment_status,
            'delivery'=>$order->delivery_cents===null?'':number_format($order->delivery_cents/100,2,'.',''),
            'tax'=>$order->tax_cents===null?'':number_format($order->tax_cents/100,2,'.',''),
            'reason'=>'Status updated from Orders grid',
        ],$r->user()->id);
        if ($r->expectsJson()) {
            $order->refresh();
            $steps=['placed','confirmed','preparing','out_for_delivery','delivered'];
            $options=[$order->status=>$order->status_label];
            $terminal=in_array($order->status,['delivered','cancelled']);
            if(!$terminal){$next=$steps[array_search($order->status,$steps,true)+1]??null;if($next)$options[$next]=Order::STATUSES[$next];if($order->payment_status==='unpaid')$options['cancelled']='Cancelled';}
            return response()->json(['status'=>$order->status,'revision'=>$order->revision,'options'=>$options,'terminal'=>$terminal,'message'=>'Saved']);
        }
        return redirect()->route('admin.orders.index',array_filter(['q'=>$d['q']??null,'page'=>$d['page']??null,'from'=>$d['from']??null,'to'=>$d['to']??null]))->with('status',$order->number.' status updated.');
    }

    public function update(Request $r, Order $order, OrderManagement $service)
    {
        $d = $r->validate(['revision' => 'required|integer|min:0', 'status' => 'required|in:placed,confirmed,preparing,out_for_delivery,delivered,cancelled', 'payment_status' => 'required|in:unpaid,paid,refunded', 'delivery' => ['nullable', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'tax' => ['nullable', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'reason' => 'nullable|string|max:1000']);
        $service->update($order, $d, $r->user()->id);

        return redirect()->route('admin.orders.show', $order)->with('status', 'Order updated.');
    }

    public function show(Order $order)
    {
        $order->load(['items', 'events.author']);

        return view('admin.orders.show', compact('order'));
    }
}
