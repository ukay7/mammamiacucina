<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $r)
    {
        $search = mb_substr((string) $r->input('q', ''), 0, 200);
        $orders = Order::when($search, fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->orWhere('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%')))->latest('id')->paginate(20)->withQueryString();

        return view('admin.orders.index', compact('orders', 'search'));
    }

    public function show(Order $order)
    {
        $order->load('items');

        return view('admin.orders.show',compact('order'));
    }
}
