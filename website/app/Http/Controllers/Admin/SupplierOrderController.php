<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\Warehouse;
use App\Services\DocumentTheme;
use App\Services\InventoryValuation;
use App\Services\SupplierOrderDocument;
use App\Services\SupplierPurchasing;
use App\Services\SupplierReceiving;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;

class SupplierOrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:200', 'status' => 'nullable|in:placed,packing,ready,received,completed,deleted,all']);
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
        $supplierOrder->load('items', 'events.author', 'author', 'receipt.items');
        $warehouses = Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();

        return view('admin.supplier-orders.show', ['order' => $supplierOrder, 'warehouses' => $warehouses]);
    }

    public function pdfPreview(SupplierOrder $supplierOrder, SupplierOrderDocument $document)
    {
        return response()->json([
            'number' => $supplierOrder->number, 'supplier' => $supplierOrder->supplier_snapshot['name'] ?? '',
            'status' => SupplierOrder::STATUS_LABELS[$supplierOrder->status],
            'labels' => $document->labels(), 'defaults' => SupplierOrderDocument::DEFAULTS,
            'rows' => $document->rows($supplierOrder, true), 'totals' => $supplierOrder->totals,
            'legacy_details' => $supplierOrder->items->contains(fn ($item) => ! array_key_exists('product_details', $item->pricing_snapshot ?? [])),
        ]);
    }

    public function pdf(Request $request, SupplierOrder $supplierOrder, SupplierOrderDocument $document, DocumentTheme $theme)
    {
        $selected = $document->selected($request);
        $rows = $document->rows($supplierOrder, in_array('image', $selected, true));
        $groups = $document->groups($selected);
        $labels = $document->labels();
        $notes = $document->notes($supplierOrder, $selected);
        $order = $supplierOrder;
        $landscape = count($selected) > 6;
        $doc = ['brand' => $theme->brand(), 'title' => 'Supplier Order', 'number' => $order->number,
            'date' => $order->created_at->format('d M Y H:i').' '.config('app.timezone')];
        $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false,
            'defaultFont' => 'DejaVu Sans', 'defaultMediaType' => 'print']));
        $pdf->loadHtml(view('documents.supplier-order', compact('doc', 'order', 'selected', 'groups', 'labels', 'rows', 'notes', 'landscape'))->render());
        $pdf->setPaper('A4', $landscape ? 'landscape' : 'portrait');
        $pdf->render();
        $pdf->getCanvas()->page_text($landscape ? 725 : 480, $landscape ? 579 : 820, 'Page {PAGE_NUM} of {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('Helvetica'), 8);

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="supplier-order-'.(int) $order->id.'.pdf"']);
    }

    public function status(Request $request, SupplierOrder $supplierOrder, SupplierPurchasing $purchasing)
    {
        $purchasing->transition($supplierOrder, $request->all(), $request->user()->id);

        return back()->with('status', 'Supplier order status updated. Stock changes only when Mark Received is confirmed with Add to inventory = Yes.');
    }

    public function receive(Request $request, SupplierOrder $supplierOrder, SupplierReceiving $receiving)
    {
        $receipt = $receiving->receive($supplierOrder, $request->all(), $request->user());

        return redirect()->route('admin.supplier-orders.show', $supplierOrder)->with('status', $receipt->add_to_inventory
            ? 'Order received. Ordered quantities were added once to '.$receipt->warehouse_name.'.'
            : 'Order received. Inventory was left unchanged as requested.');
    }
}
