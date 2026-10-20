<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['stock' => 'nullable|in:tracked,unset,healthy,low,out,shortage', 'per_page' => 'nullable|in:10,20,50,100']);
        $products = Product::with(['inventory', 'categories', 'media' => fn ($q) => $q->coverImage()])
            ->when($r->filled('q'), fn ($q) => $q->searchTerm((string)$r->input('q')))
            ->when($r->input('stock') === 'tracked', fn ($q) => $q->whereHas('inventory', fn ($i) => $i->whereNotNull('quantity_on_hand')))
            ->when($r->input('stock') === 'unset', fn ($q) => $q->whereHas('inventory', fn ($i) => $i->whereNull('quantity_on_hand')))
            ->when($r->input('stock') === 'healthy', fn ($q) => $q->whereHas('inventory', fn ($i) => $i->whereColumn('quantity_on_hand', '>', 'low_stock_threshold')))
            ->when($r->input('stock') === 'low', fn ($q) => $q->whereHas('inventory', fn ($i) => $i->where('quantity_on_hand', '>', 0)->whereColumn('quantity_on_hand', '<=', 'low_stock_threshold')))
            ->when($r->input('stock') === 'out', fn ($q) => $q->whereHas('inventory', fn ($i) => $i->where('quantity_on_hand', 0)))
            ->when($r->input('stock') === 'shortage', fn ($q) => $q->whereHas('inventory', fn ($i) => $i->where('quantity_on_hand', '<', 0)))
            ->orderBy('premium_marketing_name')->paginate((int)$r->input('per_page', 20))->withQueryString();

        return view('admin.inventory.index', compact('products'));
    }

    public function show(Product $product)
    {
        $product->load('inventory');

        return view('admin.inventory.show', ['product' => $product, 'movements' => $product->movements()->with('author')->latest('id')->paginate(20)]);
    }

    public function adjust(Request $r, Product $product)
    {
        $d = $r->validate(['action' => 'required|in:set,add,remove', 'quantity' => 'required|numeric|min:0|max:999999999|decimal:0,3', 'expected_quantity' => 'nullable|numeric', 'low_stock_threshold' => 'required|numeric|min:0|max:999999999|decimal:0,3', 'reason' => 'required|string|max:2000']);
        DB::transaction(function () use ($d, $product, $r) {
            $inventory = Inventory::where('product_id', $product->id)->lockForUpdate()->firstOrFail();
            $before = $inventory->quantity_on_hand;
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
            $inventory->update(['quantity_on_hand' => $after / 1000, 'low_stock_threshold' => $d['low_stock_threshold']]);
            InventoryMovement::create(['product_id' => $product->id, 'quantity_change' => ($after - $old) / 1000, 'quantity_before' => $before, 'quantity_after' => $after / 1000, 'reason' => $d['reason'], 'created_by' => $r->user()->id, 'created_at' => now()]);
        }, 3);

        $inventory = $product->fresh()->inventory;
        $payload = ['message' => 'Inventory updated and the adjustment was recorded.', 'quantity' => (float)$inventory->quantity_on_hand, 'threshold' => (float)$inventory->low_stock_threshold];

        return $r->expectsJson() ? response()->json($payload) : back()->with('status',$payload['message']);
    }

    public function orders(Product $product)
    {
        $rows = DB::table('order_items as oi')->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.created_by')
            ->where('oi.product_id', $product->id)
            ->orderByDesc('o.ordered_at')->orderByDesc('o.id')->limit(50)
            ->get(['o.id','o.number','o.ordered_at','o.source','o.status','o.first_name','o.last_name','oi.quantity','oi.inventory_shortage_quantity','oi.stock_deducted','u.name as creator']);

        return response()->json(['product' => $product->premium_marketing_name, 'orders' => $rows->map(fn ($row) => [
            'number'=>$row->number,'date'=>\Illuminate\Support\Carbon::parse($row->ordered_at)->format('d M Y H:i'),
            'source'=>$row->source === 'pos' ? 'Quick Sale / POS' : 'Website','customer'=>trim($row->first_name.' '.$row->last_name),
            'quantity'=>(float)$row->quantity,'shortage'=>$row->inventory_shortage_quantity === null ? null : (float)$row->inventory_shortage_quantity,
            'status'=>Order::STATUSES[$row->status] ?? ucfirst(str_replace('_',' ',$row->status)),'creator'=>$row->creator ?: 'Website customer',
            'url'=>route('admin.orders.show',$row->id),
        ])]);
    }
}
