<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\Warehouse;
use App\Services\InventoryValuation;
use App\Services\SupplierPurchasing;
use Illuminate\Http\Request;

class SupplierOrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:200', 'status' => 'nullable|in:placed,completed,deleted,all']);
        $status = $filters['status'] ?? 'placed';
        $orders = SupplierOrder::with('author')->withCount('items')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when(filled($filters['q'] ?? null), fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$filters['q'].'%')
                ->orWhere('supplier_snapshot->name', 'like', '%'.$filters['q'].'%')))
            ->latest('id')->paginate(25)->withQueryString();

        return view('admin.supplier-orders.index', compact('orders', 'status'));
    }

    public function create(Request $request, SupplierPurchasing $purchasing, InventoryValuation $valuation)
    {
        $data = $request->validate(['supplier_id' => 'nullable|integer|exists:suppliers,id', 'warehouse_id' => 'nullable|integer|exists:warehouses,id', 'active' => 'nullable|in:0,1']);
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
        $supplier = $suppliers->firstWhere('id', $data['supplier_id'] ?? null);
        $warehouse = $warehouses->firstWhere('id', $data['warehouse_id'] ?? null);
        $products = $supplier ? $purchasing->products($supplier->id, $warehouse?->id, $request->has('active') ? ($data['active'] ?? null) : '1')->get() : collect();
        foreach ($products as $product) {
            $product->setAttribute('valuation', $valuation->calculate($product));
        }

        return view('admin.supplier-orders.create', compact('suppliers', 'warehouses', 'supplier', 'warehouse', 'products'));
    }

    public function preview(Request $request, SupplierPurchasing $purchasing)
    {
        return response()->json($purchasing->preview($request->all()));
    }

    public function store(Request $request, SupplierPurchasing $purchasing)
    {
        $order = $purchasing->create($request->all(), $request->user()->id);
        $url = route('admin.supplier-orders.show', $order);

        return $request->expectsJson() ? response()->json(['redirect' => $url, 'number' => $order->number])
            : redirect($url)->with('status', 'Supplier order created.');
    }

    public function show(SupplierOrder $supplierOrder)
    {
        $supplierOrder->load('items', 'events.author', 'author');

        return view('admin.supplier-orders.show', ['order' => $supplierOrder]);
    }

    public function status(Request $request, SupplierOrder $supplierOrder, SupplierPurchasing $purchasing)
    {
        $purchasing->transition($supplierOrder, $request->all(), $request->user()->id);

        return back()->with('status', 'Supplier order status updated. Inventory is managed separately.');
    }
}
