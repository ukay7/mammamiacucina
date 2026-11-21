<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Product, Order};
use App\Services\{AdminNavigation, CustomerInvoicing, PosCustomer, PosSale};
use App\Support\BusinessTime;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    public function index()
    {
        return view('admin.pos.index',[
            'customers'=>\App\Models\Customer::with('user')->whereHas('user',fn($q)=>$q->where('is_active',true)->whereIn('account_type',['individual','business']))->orderBy('name')->get(),
            'categories'=>\App\Models\Category::whereHas('products')->orderBy('name')->get(['id','name']),
        ]);
    }

    public function products(Request $request)
    {
        $data = $request->validate(['customer_id'=>'required|integer','q' => 'nullable|string|max:255', 'scan' => 'nullable|boolean',
            'category_id'=>'nullable|integer|exists:categories,id','page'=>'nullable|integer|min:1']);
        $profile = app(\App\Services\PosCustomer::class)->resolve($data['customer_id']);
        $term = trim($data['q'] ?? '');
        $query = Product::with(['inventory','categories','category', 'media' => fn ($q) => $q->coverImage()]);
        if ($request->boolean('scan')) {
            $query->where(function ($q) use ($term) {
                $q->where('qr_code', $term)->orWhere('product_code', $term);
            });
        } else {
            $query->searchTerm($term, false, true);
            if (!empty($data['category_id'])) $query->where(fn($q)=>$q->where('category_id',$data['category_id'])->orWhereHas('categories',fn($c)=>$c->where('categories.id',$data['category_id'])));
        }
        $page=$query->orderBy('premium_marketing_name')->orderBy('id')->paginate(20);
        $prices=app(\App\Services\CustomerTerms::class)->prices($page->getCollection(),$profile);
        return response()->json(['customer'=>$profile->only(['id','name','email','phone','account_type','address','city','province','postal_code','country','business_name','delivery_address']),
            'products'=>$page->getCollection()->map(fn($p)=>$this->productCard($p,$prices[$p->id]['unit_cents'])),
            'total'=>$page->total(),'next_page'=>$page->hasMorePages()?$page->currentPage()+1:null]);
    }

    private function productCard(Product $product, ?int $price): array
    {
        $pieces=data_get($product->dna,'pieces_per_carton');
        $pack=filled($pieces)?\App\Support\NumberDisplay::quantity($pieces).' pcs / carton'
            :(filled($product->pieces_per_pack)?\App\Support\NumberDisplay::quantity($product->pieces_per_pack).' pcs / pack':null);
        if (filled($product->pack_weight_kg)) $pack=implode(' · ',array_filter([$pack,\App\Support\NumberDisplay::quantity($product->pack_weight_kg).' kg / pack']));
        return ['id'=>$product->id,'name'=>$product->premium_marketing_name,'barcode'=>$product->barcode_number,
            'subtitle'=>$product->italian_subtitle,'source_name'=>$product->original_description,'qr_code'=>$product->qr_code,
            'code'=>$product->product_code,'active'=>$product->is_active,'unit_cents'=>$price,'stock'=>$product->inventory?->quantity_on_hand,'uom'=>$product->uom,
            'category'=>$product->categories->pluck('name')->join(', ') ?: $product->category?->name,'pack'=>$pack,
            'image'=>($image=$product->media->first())?route('admin.pos.media',[$product,$image]):null,
            'details_url'=>route('admin.pos.product',$product)];
    }

    public function product(Request $request, Product $product)
    {
        $data=$request->validate(['customer_id'=>'required|integer']);
        $customer=app(\App\Services\PosCustomer::class)->resolve($data['customer_id']);
        $product->load(['inventory','categories','category','allergies','media'=>fn($q)=>$q->coverImage()]);
        $fields=[];
        foreach (['product_code','qr_code','italian_subtitle','original_description','uom','unit_weight_g','pack_weight_kg','pieces_per_pack','size_diameter_cm','product_notes'] as $key) {
            if (filled($product->$key)) $fields[config('product_fields.'.$key)[0]]=\App\Support\NumberDisplay::field($product->$key,$key);
        }
        foreach (['net_carton_kg'=>'Net carton weight (kg)','pieces_per_carton'=>'Pieces per carton'] as $key=>$label) {
            if (filled($value=data_get($product->dna,$key))) $fields[$label]=\App\Support\NumberDisplay::quantity($value);
        }
        if ($product->allergies->isNotEmpty()) $fields['Allergens']=$product->allergies->pluck('name')->join(', ');
        return response()->json(['product'=>$this->productCard($product,app(\App\Services\CustomerTerms::class)->price($product,$customer)['unit_cents']), 'details'=>$fields]);
    }

    public function recommendations(Request $request, \App\Services\PosRecommendations $service)
    {
        $data=$request->validate(['customer_id'=>'required|integer']);
        $customer=app(\App\Services\PosCustomer::class)->resolve($data['customer_id']);
        $groups=$service->forCustomer($customer);
        $ids=$groups['frequent']->pluck('product_id')->merge($groups['slow']->pluck('product_id'))->unique();
        $products=Product::with(['inventory','categories','category','media'=>fn($q)=>$q->coverImage()])->whereIn('id',$ids)->get()->keyBy('id');
        $prices=app(\App\Services\CustomerTerms::class)->prices($products,$customer);
        $result=['customer_id'=>$customer->id,'days'=>90];
        foreach ($groups as $key=>$rows) {
            $result[$key]=$rows->filter(fn($row)=>$products->has($row->product_id))->map(function($row) use ($products,$prices,$key) {
                $product=$products[$row->product_id];
                return $this->productCard($product,$prices[$product->id]['unit_cents'])+
                    ($key==='frequent'?['order_count'=>(int)$row->order_count]:['sold_units'=>(float)$row->sold_units]);
            })->values();
        }
        return response()->json($result)->header('Cache-Control','private, no-store');
    }

    public function customerSummary(Request $request, PosCustomer $profiles, CustomerInvoicing $invoicing)
    {
        $data = $request->validate(['customer_id' => 'required|integer']);
        $profile = $profiles->resolve((int) $data['customer_id']);
        $actor = $request->user();
        $nodes = AdminNavigation::nodes();
        $canViewProfile = $actor->hasAdminPermission('orders.view')
            && AdminNavigation::allowed($actor, $nodes['access.admin.customers.index']);
        $describeOrder = function (Order $order) use ($actor, $nodes) {
            $url = null;
            $orderPage = $order->status === 'completed' ? 'access.admin.orders.completed' : 'access.admin.orders.index';
            if ($order->source === 'pos' && ((int) $order->created_by === $actor->id || $actor->hasAdminPermission('orders.manage'))) {
                $url = route('admin.pos.order', $order);
            } elseif ($actor->hasAdminPermission('orders.view') && !$actor->isWarehouseOnly() && AdminNavigation::allowed($actor, $nodes[$orderPage])) {
                $url = route('admin.orders.show', $order);
            }
            return [
                'number' => $order->number,
                'date' => BusinessTime::format($order->ordered_at, 'd M Y'),
                'status' => $order->status_label,
                'total_cents' => $order->final_total_cents,
                'url' => $url,
            ];
        };
        $customerOrders = $profile->orders()->whereDoesntHave('payment', fn ($query) => $query->where('mode', 'sandbox'));
        $lastOrder = (clone $customerOrders)
            ->when(!$canViewProfile, fn ($query) => $query->where('source', 'pos')->where('created_by', $actor->id))
            ->orderByDesc('ordered_at')->orderByDesc('id')->first();

        $thisMonth = BusinessTime::today()->startOfMonth();
        $lastMonth = $thisMonth->subMonth();
        $balance = 0;
        $dueCount = 0;
        $dueOrders = [];
        $orders = (clone $customerOrders)->where('status', '!=', 'cancelled')
            ->where('ordered_at', '>=', $lastMonth->utc())->where('ordered_at', '<', $thisMonth->utc())
            ->with(['payment', 'settlements'])->lazyById(100);
        foreach ($orders as $order) {
            $due = $order->balance_cents;
            if ($due <= 0) continue;
            $balance += $due;
            $dueCount++;
            if ($canViewProfile && count($dueOrders) < 25) {
                $dueOrders[] = $describeOrder($order) + ['balance_cents' => $due];
            }
        }

        return response()->json([
            'customer_id' => $profile->id,
            'tier' => $profile->tier ?? 'standard',
            'tier_label' => $profile->tier_label,
            'profile_url' => $canViewProfile ? route('admin.customers.show', $profile) : null,
            'available_credit_cents' => $invoicing->balances($profile)['available'],
            'credit_enabled' => $profile->credit_limit_cents > 0,
            'last_order' => $lastOrder ? $describeOrder($lastOrder) : null,
            'last_month' => ['label' => $lastMonth->format('F Y'), 'amount_cents' => $balance, 'count' => $dueCount, 'orders' => $dueOrders],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function quote(Request $request, PosSale $service)
    {
        $data = $request->validate(['customer_id'=>'required|integer','items' => 'required|array|min:1|max:100', 'items.*.id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|integer|min:1|max:9999', 'fulfillment' => 'required|in:pickup,delivery','postal_code'=>'nullable|string|max:30','country'=>'nullable|string|max:100','delivery_service'=>'nullable|string|max:20'] + PosSale::adjustmentRules());
        $data += $request->validate(['delivery_address_type'=>'sometimes|required|in:company,alternate',
            'address'=>'nullable|string|max:255','city'=>'nullable|string|max:100','province'=>'nullable|string|max:100']);
        return response()->json($service->quote($data['items'], $data['fulfillment'], $request->user()->id,$data));
    }

    public function store(Request $request, PosSale $service)
    {
        $packingRule = $request->user()->isWarehouseUserType() ? 'required|accepted' : 'nullable';
        $data = $request->validate(['delivery_service'=>'nullable|string|max:20','customer_id'=>'required|integer|exists:customers,id','account_type'=>'sometimes|in:individual,business','packing_confirmed'=>$packingRule,'quote' => 'required|string|max:100000', 'first_name' => 'required_without:customer_id|nullable|string|max:100',
            'last_name' => 'nullable|string|max:100', 'email' => 'required_without:customer_id|nullable|email|max:255', 'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:255', 'city' => 'nullable|string|max:100', 'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:30', 'country' => 'nullable|string|max:100', 'notes' => 'nullable|string|max:2000',
            'payment_method' => 'required|in:cash,etransfer,cheque,credit', 'payment_status' => 'required|in:paid,unpaid'] + \App\Services\BusinessDetails::rules(!$request->filled('customer_id') && $request->input('account_type')==='business'));
        $data += $request->validate(['delivery_address_type'=>'sometimes|required|in:company,alternate']);
        $data += $request->validate(['order_channel'=>['nullable',Rule::in(array_keys(Order::POS_CHANNELS))]]);
        $data += $request->validate(['pos_draft_id'=>'sometimes|nullable|integer|min:1','pos_draft_revision'=>'required_with:pos_draft_id|integer|min:0']);
        $data += $request->only(\App\Services\DeliverySchedule::FIELDS);
        $order = $service->complete($data['quote'], $data, $request->user()->id)->refresh();
        return response()->json(['number' => $order->number, 'total' => $order->final_total_cents,
            'receipt_url'=>route('orders.transfer-receipt.store',$order),'revision'=>$order->revision,'status'=>$order->status_label,'payment_method'=>$order->payment_method,'payment_status'=>$order->payment_status,'payment_status_label'=>$order->payment_status_label,'order_url'=>route('admin.pos.order',$order),'print_url' => route('admin.pos.receipt', $order)]);
    }

    public function customer(Request $request, \App\Services\PosCustomer $service)
    {
        $data = $request->validate(['first_name'=>'required|string|max:100','last_name'=>'nullable|string|max:100',
            'email'=>'required|email|max:255','phone'=>'required|string|max:40','account_type'=>'required|in:individual,business',
            'address'=>'nullable|string|max:255','city'=>'nullable|string|max:100','province'=>'nullable|string|max:100',
            'postal_code'=>'nullable|string|max:30','country'=>'nullable|string|max:100'] + \App\Services\BusinessDetails::rules($request->input('account_type')==='business'));
        $customer=$service->create($data);
        return response()->json(['customer'=>$customer->only(['id','name','email','phone','account_type','business_name','address','city','province','postal_code','country']), 'pending'=>$customer->user->businessApprovalPending()]);
    }

    public function orders(Request $request)
    {
        $orders=Order::with('customer')->where('source','pos')->where('created_by',$request->user()->id)->orderByDesc('ordered_at')->orderByDesc('id')->paginate(20);
        return view('admin.pos.orders',compact('orders'));
    }

    public function order(Request $request, Order $order)
    {
        abort_unless($order->source === 'pos' && ((int)$order->created_by === $request->user()->id || $request->user()->hasAdminPermission('orders.manage')),403);
        return view('admin.pos.order',['order'=>$order->load(['items.product','events.author'])]);
    }

    public function handover(Request $request, Order $order, \App\Services\OrderManagement $service)
    {
        abort_unless($order->source==='pos' && $request->user()->hasAdminPermission('orders.manage'),403);
        $data=$request->validate(['revision'=>'required|integer|min:0','handed_over'=>'accepted']);
        if ($order->fulfillment!=='pickup' || $order->status!=='ready_to_dispatch') {
            throw \Illuminate\Validation\ValidationException::withMessages(['order'=>'Complete packing before handing over this pickup.']);
        }
        $service->update($order,['revision'=>$data['revision'],'status'=>'delivered','payment_status'=>$order->payment_status,
            'delivery'=>'0','tax'=>number_format($order->tax_cents/100,2,'.',''),'reason'=>'POS staff confirmed items handed to customer.'],$request->user()->id);
        return redirect()->route('admin.pos.order',$order)->with('status','Pickup delivered. Payment settlement is tracked separately.');
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
