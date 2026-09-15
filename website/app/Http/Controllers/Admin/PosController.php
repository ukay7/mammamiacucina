<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Product, Order};
use App\Services\PosSale;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function index()
    {
        return view('admin.pos.index');
    }

    public function products(Request $request)
    {
        $data = $request->validate(['q' => 'required|string|max:255', 'scan' => 'nullable|boolean']);
        $term = trim($data['q']);
        $query = Product::with(['inventory', 'media' => fn ($q) => $q->where('kind', 'image')->limit(1)]);
        if ($request->boolean('scan')) {
            $query->where(function ($q) use ($term) {
                $q->where('qr_code', $term)->orWhere('product_code', $term);
            });
        } else {
            $query->where(function ($q) use ($term) {
                $q->where('premium_marketing_name', 'like', '%'.$term.'%')->orWhere('product_code', 'like', '%'.$term.'%')->orWhere('qr_code', 'like', '%'.$term.'%');
            });
        }
        return response()->json(['products' => $query->orderBy('premium_marketing_name')->limit(20)->get()->map(function ($product) {
            return ['id' => $product->id, 'name' => $product->premium_marketing_name, 'barcode' => $product->barcode_number,
                'qr_code' => $product->qr_code, 'code' => $product->product_code, 'active' => $product->is_active,
                'unit_cents' => $product->total_selling_price_cad === null ? null : (int) round((float) $product->total_selling_price_cad * 100),
                'stock' => $product->inventory?->quantity_on_hand, 'uom' => $product->uom,
                'image' => ($image = $product->media->first()) ? route('admin.pos.media', [$product, $image]) : null];
        })]);
    }

    public function quote(Request $request, PosSale $service)
    {
        $data = $request->validate(['items' => 'required|array|min:1|max:100', 'items.*.id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|integer|min:1|max:9999', 'fulfillment' => 'required|in:pickup,delivery']);
        return response()->json($service->quote($data['items'], $data['fulfillment'], $request->user()->id));
    }

    public function store(Request $request, PosSale $service)
    {
        $data = $request->validate(['quote' => 'required|string|max:100000', 'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:255', 'city' => 'nullable|string|max:100', 'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:30', 'country' => 'nullable|string|max:100', 'notes' => 'nullable|string|max:2000',
            'payment_method' => 'required|in:cash,card', 'payment_status' => 'required|in:paid,unpaid']);
        $order = $service->complete($data['quote'], $data, $request->user()->id);
        return response()->json(['number' => $order->number, 'total' => $order->final_total_cents,
            'print_url' => route('admin.pos.receipt', $order)]);
    }

    public function receipt(Request $request, Order $order)
    {
        abort_unless($order->source === 'pos' && ((int) $order->created_by === $request->user()->id || $request->user()->hasAdminPermission('orders.view')), 403);
        return response()->view('orders.print', ['order' => $order->load('items')]);
    }

    public function labels(Request $request)
    {
        $data = $request->validate(['product_ids' => 'required|array|min:1|max:100', 'product_ids.*' => 'required|integer|distinct|exists:products,id', 'copies' => 'nullable|integer|min:1|max:30']);
        $products = Product::whereIn('id', $data['product_ids'])->orderBy('premium_marketing_name')->get();
        $copies = $data['copies'] ?? 1;
        abort_if($products->count() * $copies > 300, 422, 'Print up to 300 labels at once.');
        return view('admin.products.labels', compact('products', 'copies'));
    }
}