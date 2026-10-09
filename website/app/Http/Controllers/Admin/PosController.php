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
        return view('admin.pos.index',['customers'=>\App\Models\Customer::with('user')->whereHas('user',fn($q)=>$q->where('is_active',true)->whereIn('account_type',['individual','business']))->orderBy('name')->get()]);
    }

    public function products(Request $request)
    {
        $data = $request->validate(['customer_id'=>'required|integer','q' => 'nullable|string|max:255', 'scan' => 'nullable|boolean']);
        $profile = app(\App\Services\PosCustomer::class)->resolve($data['customer_id']);
        $term = trim($data['q'] ?? '');
        $query = Product::with(['inventory', 'media' => fn ($q) => $q->coverImage()]);
        if ($request->boolean('scan')) {
            $query->where(function ($q) use ($term) {
                $q->where('qr_code', $term)->orWhere('product_code', $term);
            });
        } else {
            $query->searchTerm($term);
        }
        return response()->json(['products' => $query->orderBy('premium_marketing_name')->limit(20)->get()->map(function ($product) use ($profile) {
            return ['id' => $product->id, 'name' => $product->premium_marketing_name, 'barcode' => $product->barcode_number,
                'qr_code' => $product->qr_code, 'code' => $product->product_code, 'active' => $product->is_active,
                'unit_cents' => app(\App\Services\PosCustomer::class)->price($product, $profile),
                'stock' => $product->inventory?->quantity_on_hand, 'uom' => $product->uom,
                'image' => ($image = $product->media->first()) ? route('admin.pos.media', [$product, $image]) : null];
        })]);
    }

    public function quote(Request $request, PosSale $service)
    {
        $data = $request->validate(['customer_id'=>'required|integer','items' => 'required|array|min:1|max:100', 'items.*.id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|integer|min:1|max:9999', 'fulfillment' => 'required|in:pickup,delivery','postal_code'=>'nullable|string|max:30','country'=>'nullable|string|max:100','delivery_service'=>'nullable|string|max:20']);
        return response()->json($service->quote($data['items'], $data['fulfillment'], $request->user()->id,$data));
    }

    public function store(Request $request, PosSale $service)
    {
        $data = $request->validate(['delivery_service'=>'nullable|string|max:20','customer_id'=>'required|integer|exists:customers,id','account_type'=>'sometimes|in:individual,business','quote' => 'required|string|max:100000', 'first_name' => 'required_without:customer_id|nullable|string|max:100',
            'last_name' => 'nullable|string|max:100', 'email' => 'required_without:customer_id|nullable|email|max:255', 'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:255', 'city' => 'nullable|string|max:100', 'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:30', 'country' => 'nullable|string|max:100', 'notes' => 'nullable|string|max:2000',
            'payment_method' => 'required|in:cash,etransfer', 'payment_status' => 'required|in:paid,unpaid'] + \App\Services\BusinessDetails::rules(!$request->filled('customer_id') && $request->input('account_type')==='business'));
        $order = $service->complete($data['quote'], $data, $request->user()->id)->refresh();
        return response()->json(['number' => $order->number, 'total' => $order->final_total_cents,
            'receipt_url'=>route('orders.transfer-receipt.store',$order),'revision'=>$order->revision,'status'=>$order->status_label,'payment_method'=>$order->payment_method,'order_url'=>route('admin.pos.order',$order),'print_url' => route('admin.pos.receipt', $order)]);
    }

    public function customer(Request $request, \App\Services\PosCustomer $service)
    {
        $data = $request->validate(['first_name'=>'required|string|max:100','last_name'=>'nullable|string|max:100',
            'email'=>'required|email|max:255','phone'=>'required|string|max:40','account_type'=>'required|in:individual,business',
            'address'=>'nullable|string|max:255','city'=>'nullable|string|max:100','province'=>'nullable|string|max:100',
            'postal_code'=>'nullable|string|max:30','country'=>'nullable|string|max:100'] + \App\Services\BusinessDetails::rules($request->input('account_type')==='business'));
        $customer=$service->create($data);
        return response()->json(['customer'=>$customer->only(['id','name','email','phone','account_type','address','city','province','postal_code','country']), 'pending'=>$customer->user->businessApprovalPending()]);
    }

    public function orders(Request $request)
    {
        $orders=Order::where('source','pos')->where('created_by',$request->user()->id)->latest()->paginate(20);
        return view('admin.pos.orders',compact('orders'));
    }

    public function order(Request $request, Order $order)
    {
        abort_unless($order->source === 'pos' && ((int)$order->created_by === $request->user()->id || $request->user()->hasAdminPermission('orders.manage')),403);
        return view('admin.pos.order',['order'=>$order->load(['items.product','events.author'])]);
    }

    public function handover(Request $request, Order $order, \App\Services\OrderManagement $service)
    {
        abort_unless($order->source==='pos' && ((int)$order->created_by===$request->user()->id || $request->user()->hasAdminPermission('orders.manage')),403);
        $data=$request->validate(['revision'=>'required|integer|min:0','handed_over'=>'accepted']);
        if ($order->fulfillment!=='pickup' || $order->status!=='ready_to_dispatch' || $order->payment_status!=='paid' || $order->balance_cents!==0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['order'=>'Complete packing and confirm full payment before handing over this pickup.']);
        }
        $service->update($order,['revision'=>$data['revision'],'status'=>'completed','payment_status'=>'paid',
            'delivery'=>'0','tax'=>number_format($order->tax_cents/100,2,'.',''),'reason'=>'POS staff confirmed items handed to customer.'],$request->user()->id);
        return redirect()->route('admin.pos.order',$order)->with('status','Pickup completed.');
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
