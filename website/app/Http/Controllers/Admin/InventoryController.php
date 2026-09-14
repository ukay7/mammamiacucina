<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index(Request $r)
    {
        $products = Product::with(['inventory', 'categories'])->when($r->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('premium_marketing_name', 'like', '%'.mb_substr($r->string('q'), 0, 200).'%')->orWhere('qr_code', 'like', '%'.mb_substr($r->string('q'), 0, 200).'%')))->when($r->input('stock') === 'unset', fn ($q) => $q->whereHas('inventory', fn ($i) => $i->whereNull('quantity_on_hand')))->when($r->input('stock') === 'low', fn ($q) => $q->whereHas('inventory', fn ($i) => $i->whereColumn('quantity_on_hand', '<=', 'low_stock_threshold')))->orderBy('premium_marketing_name')->paginate(20)->withQueryString();

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

        return back()->with('status','Stock updated and adjustment recorded.');
    }
}
