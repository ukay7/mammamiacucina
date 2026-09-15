<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderTrackingController extends Controller
{
    public function form()
    {
        return response()->view('pages.track-order')->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function lookup(Request $r)
    {
        $d = $r->validate(['number' => 'required|string|max:100']);
        $order = Order::where('number', strtolower(trim($d['number'])))->first();
        if (! $order) {
            return back()->withErrors(['order' => 'Order not found. Please check your order number.'])->withInput($r->only('number'));
        }

return redirect()->route('order.track', $order->number);
    }

    public function show(string $token)
    {
        if (preg_match('/^mmc-[0-9]+$/i', $token)) {
            $order=Order::where('number',strtolower($token))->firstOrFail();
            if ($this->canView($order)) {
                $order->load('items');
                return response()->view('pages.order-tracking',compact('order'))->header('Cache-Control','no-store, private')->header('Referrer-Policy','no-referrer')->header('X-Robots-Tag','noindex, nofollow');
            }
            return response()->view('pages.order-status',compact('order'))->header('Cache-Control','no-store, private')->header('X-Robots-Tag','noindex, nofollow');
        }
        $order = $this->find($token);

        return response()->view('pages.order-tracking', compact('order'))->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function printOrder(string $token)
    {
        $order = $this->find($token);

        return response()->view('orders.print', compact('order'))->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function verify(Request $r, string $token)
    {
        $d=$r->validate(['email'=>'required|email|max:255']);
        $order=Order::where('number',strtolower($token))->firstOrFail();
        if (!hash_equals(mb_strtolower(trim($order->email)),mb_strtolower(trim($d['email'])))) {
            return back()->withErrors(['email'=>'This email does not match the order. Please use the email entered at checkout.']);
        }
        $verified=session('verified_order_ids',[]);
        $verified[]=$order->id;
        session(['verified_order_ids'=>array_slice(array_values(array_unique($verified)),-30)]);
        return redirect()->route('order.track',$order->number);
    }

    private function canView(Order $order):bool
    {
        return (int)session('last_order_id')===$order->id
            || in_array($order->id,session('verified_order_ids',[]),true)
            || (auth()->user()?->hasAdminPermission('orders.view')??false);
    }

    private function find(string $token): Order
    {
        if(preg_match('/^mmc-[0-9]+$/i',$token)) {
            $order=Order::with('items')->where('number',strtolower($token))->firstOrFail();
            abort_unless($this->canView($order),404);
            return $order;
        }
        abort_unless(strlen($token) === 48, 404);

        return Order::with('items')->where('tracking_token',$token)->firstOrFail();
    }
}
