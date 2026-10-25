<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\InventoryProducts;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index(Request $r, InventoryProducts $inventoryProducts)
    {
        $filters = $inventoryProducts->filters($r);
        $warehouses = Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
        $selectedWarehouse = $warehouses->firstWhere('id', (int) $r->input('warehouse')) ?? $warehouses->firstWhere('is_default', true) ?? $warehouses->first();
        $query = $inventoryProducts->query($filters, $selectedWarehouse?->id);
        [$stockSummary, $products] = DB::transaction(fn () => [
            $inventoryProducts->stockSummary($query),
            (clone $query)
                ->with(['inventory', 'categories', 'media' => fn ($q) => $q->coverImage()])
                ->paginate((int) $r->input('per_page', 20))->withQueryString(),
        ]);
        $categories = Category::orderBy('name')->get();

        return view('admin.inventory.index', compact('products', 'warehouses', 'selectedWarehouse', 'categories', 'stockSummary'));
    }

    public function summary(Request $request, InventoryProducts $inventoryProducts)
    {
        $filters = $inventoryProducts->filters($request);
        $warehouses = Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
        $warehouse = $warehouses->firstWhere('id', (int) $request->input('warehouse'))
            ?? $warehouses->firstWhere('is_default', true) ?? $warehouses->first();

        return response()->json($inventoryProducts->stockSummary($inventoryProducts->query($filters, $warehouse?->id)));
    }

    public function show(Product $product)
    {
        $product->load('inventory');

        return view('admin.inventory.show', ['product' => $product, 'movements' => $product->movements()->with(['author', 'warehouse'])->latest('id')->paginate(20)]);
    }

    public function adjust(Request $r, Product $product)
    {
        $d = $r->validate(['warehouse_id' => 'nullable|exists:warehouses,id', 'action' => 'required|in:set,add,remove', 'quantity' => 'required|numeric|min:0|max:999999999|decimal:0,3', 'expected_quantity' => 'nullable|numeric', 'low_stock_threshold' => 'required|numeric|min:0|max:999999999|decimal:0,3', 'reason' => 'required|string|max:2000']);
        $d['warehouse_id'] = $d['warehouse_id'] ?? Warehouse::where('is_default', true)->value('id');
        DB::transaction(function () use ($d, $product, $r) {
            $inventory = Inventory::where('product_id', $product->id)->lockForUpdate()->firstOrFail();
            $warehouseInventory = WarehouseInventory::where('product_id', $product->id)->where('warehouse_id', $d['warehouse_id'])->lockForUpdate()->first();
            $before = $warehouseInventory?->quantity_on_hand;
            $expected = $d['expected_quantity'] ?? null;
            if (($before === null) !== ($expected === null) || ($before !== null && (float) $before !== (float) $expected)) {
                throw ValidationException::withMessages(['quantity' => 'Stock has changed since this page was opened. Reload and try again.']);
            }
            if ($before === null && $d['action'] !== 'set') {
                throw ValidationException::withMessages(['quantity' => 'Set opening stock before adding or removing stock.']);
            }
            // Quantities are represented as thousandths during arithmetic to avoid floating-point drift.
            $old = (int) round((float) ($before ?? 0) * 1000);
            $amount = (int) round((float) $d['quantity'] * 1000);
            $after = match ($d['action']) {
                'set' => $amount,'add' => $old + $amount,'remove' => $old - $amount
            };
            if ($after < 0 || $after > 999999999000) {
                throw ValidationException::withMessages(['quantity' => 'Adjustment must leave stock between 0 and 999,999,999.']);
            }
            WarehouseInventory::updateOrCreate(['product_id' => $product->id, 'warehouse_id' => $d['warehouse_id']], ['quantity_on_hand' => $after / 1000]);
            $total = WarehouseInventory::where('product_id', $product->id)->whereNotNull('quantity_on_hand')->sum('quantity_on_hand');
            Inventory::withoutEvents(fn () => $inventory->update(['quantity_on_hand' => $total, 'low_stock_threshold' => $d['low_stock_threshold']]));
            InventoryMovement::create(['product_id' => $product->id, 'warehouse_id' => $d['warehouse_id'], 'quantity_change' => ($after - $old) / 1000, 'quantity_before' => $before, 'quantity_after' => $after / 1000, 'reason' => $d['reason'], 'created_by' => $r->user()->id, 'created_at' => now()]);
        }, 3);

        $inventory = $product->fresh()->inventory;
        $warehouseQuantity = WarehouseInventory::where('product_id', $product->id)->where('warehouse_id', $d['warehouse_id'])->value('quantity_on_hand');
        $payload = ['message' => 'Warehouse inventory updated and the adjustment was recorded.', 'quantity' => (float) $warehouseQuantity, 'threshold' => (float) $inventory->low_stock_threshold];

        return $r->expectsJson() ? response()->json($payload) : back()->with('status', $payload['message']);
    }

    public function orders(Product $product)
    {
        $rows = DB::table('order_items as oi')->join('orders as o', 'o.id', '=', 'oi.order_id')->whereNull('o.deleted_at')
            ->leftJoin('users as u', 'u.id', '=', 'o.created_by')
            ->where('oi.product_id', $product->id)
            ->orderByDesc('o.ordered_at')->orderByDesc('o.id')->limit(50)
            ->get(['o.id', 'o.number', 'o.ordered_at', 'o.source', 'o.status', 'o.first_name', 'o.last_name', 'oi.quantity', 'oi.inventory_shortage_quantity', 'oi.stock_deducted', 'u.name as creator']);

        return response()->json(['product' => $product->premium_marketing_name, 'orders' => $rows->map(fn ($row) => [
            'number' => $row->number, 'date' => Carbon::parse($row->ordered_at)->format('d M Y H:i'),
            'source' => $row->source === 'pos' ? 'Quick Sale / POS' : 'Website', 'customer' => trim($row->first_name.' '.$row->last_name),
            'quantity' => (float) $row->quantity, 'shortage' => $row->inventory_shortage_quantity === null ? null : (float) $row->inventory_shortage_quantity,
            'status' => Order::STATUSES[$row->status] ?? ucfirst(str_replace('_', ' ', $row->status)), 'creator' => $row->creator ?: 'Website customer',
            'url' => route('admin.orders.show', $row->id),
        ])]);
    }
}
