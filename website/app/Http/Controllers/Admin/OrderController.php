<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderAmendment;
use App\Services\OrderManagement;
use App\Services\WarehousePacking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class OrderController extends Controller
{
    private function warehouseOnly(): bool
    {
        return auth()->user()->hasAdminPermission('warehouse.pack') && ! auth()->user()->hasAdminPermission('orders.manage');
    }

    private function readable(Order $order): void
    {
        if ($this->warehouseOnly()) {
            abort_unless(Order::whereKey($order->id)->visibleToWarehouse(auth()->id())->exists(), 404);
        }
    }

    public function packing(Request $r, Order $order, WarehousePacking $service)
    {
        $data = $r->validate(['revision' => 'required|integer|min:0', 'action' => 'required|in:save,return,ready', 'note' => 'nullable|string|max:2000', 'items' => 'required|array|min:1|max:100', 'items.*' => 'array:packed,note', 'items.*.packed' => 'required|boolean', 'items.*.note' => 'nullable|string|max:1000']);
        $service->save($order, $data, $r->user()->id);

        return redirect()->route($data['action'] === 'return' ? 'admin.orders.index' : 'admin.orders.show', $data['action'] === 'return' ? [] : [$order])->with('status', $data['action'] === 'return' ? 'Returned to admin with item notes.' : ($data['action'] === 'ready' ? 'All items packed. Order is ready for dispatch.' : 'Packing progress saved.'));
    }

    public function amend(Request $r, Order $order, OrderAmendment $service)
    {
        $money = ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'];
        $rules = ['delivery_service'=>'nullable|string|max:20','revision' => 'required|integer|min:0', 'fulfillment' => 'required|in:pickup,delivery', 'delivery' => $money, 'tax' => $money, 'tax_mode' => 'required|in:manual,recalculate', 'reason' => 'required|string|max:1000',
            'items' => 'required|array|min:1|max:100', 'items.*' => 'array:quantity,unit_price', 'items.*.quantity' => 'required|integer|min:0|max:999', 'items.*.unit_price' => $money,
            'add_items' => 'sometimes|array|max:20', 'add_items.*' => 'array:product_id,quantity,unit_price', 'add_items.*.product_id' => 'required|integer|distinct|exists:products,id', 'add_items.*.quantity' => 'required|integer|min:1|max:999', 'add_items.*.unit_price' => ['nullable', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'first_name' => 'required|string|max:100', 'last_name' => 'nullable|string|max:100', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:40'];
        foreach (['address', 'city', 'province', 'postal_code', 'country'] as $key) {
            $rules[$key] = 'nullable|string|max:255';
        }
        $data = $r->validate($rules);
        foreach (['last_name', 'email', 'phone', 'address', 'city', 'province', 'postal_code', 'country'] as $field) {
            $data[$field] = $data[$field] ?? '';
        }
        $service->amend($order, $data, $r->user()->id);

        return back()->with('status', 'Order updated. Review the new total and any balance or refund due before sending it back to warehouse.');
    }

    public function payments(Order $order)
    {
        $order->load(['payment', 'settlements.author']);
        return view('admin.orders.payments', compact('order'));
    }

    public function paymentReceipt(Order $order, \App\Models\OrderSettlement $settlement)
    {
        abort_unless((int) $settlement->order_id === (int) $order->id && $settlement->receipt_path, 404);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($disk->exists($settlement->receipt_path), 404);
        return response()->file($disk->path($settlement->receipt_path), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ])->setPrivate();
    }

    public function settlement(Request $r, Order $order, OrderAmendment $service)
    {
        $data = $r->validate(['revision' => 'required|integer|min:0', 'direction' => 'required|in:collect,refund', 'method' => 'required|in:cash,card,paypal,etransfer', 'amount' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'reference' => 'nullable|required_unless:method,cash|string|max:255', 'note' => 'required|string|max:1000', 'received' => 'accepted', 'receipt' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);
        $path = $r->hasFile('receipt') ? $r->file('receipt')->store('order-payment-receipts', 'local') : null;
        if ($r->hasFile('receipt') && !$path) {
            throw ValidationException::withMessages(['receipt'=>'Unable to save the payment receipt. Please try again.']);
        }
        // Keep each verified entry's evidence when the customer replaces their pending receipt.
        if (!$path && $data['direction'] === 'collect' && $data['method'] === 'etransfer' && $order->transfer_receipt_path) {
            $disk = \Illuminate\Support\Facades\Storage::disk('local');
            $path = 'order-payment-receipts/'.\Illuminate\Support\Str::uuid().'.'.pathinfo($order->transfer_receipt_path, PATHINFO_EXTENSION);
            if (!$disk->exists($order->transfer_receipt_path) || !$disk->copy($order->transfer_receipt_path, $path)) {
                throw ValidationException::withMessages(['receipt'=>'The order receipt is unavailable. Attach a receipt to this payment entry.']);
            }
        }

        try {
            $data['receipt_path'] = $path;
            $service->settle($order, $data, $r->user()->id);
        } catch (\Throwable $e) {
            if ($path) \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
            throw $e;
        }

        return back()->with('status', 'Actual collection/refund recorded. No gateway charge was initiated.');
    }

    private function listing(Request $r)
    {
        $search = mb_substr((string) $r->input('q', ''), 0, 200);
        $dates = $r->validate(['from' => 'sometimes|required|date_format:Y-m-d', 'to' => 'sometimes|required|date_format:Y-m-d']);
        $from = $dates['from'] ?? now()->startOfMonth()->toDateString();
        $to = $dates['to'] ?? now()->endOfMonth()->toDateString();
        if ($to < $from) {
            throw ValidationException::withMessages(['to' => 'The To date must be on or after the From date.']);
        }
        $warehouseOnly = $this->warehouseOnly();
        $completed = $r->routeIs('admin.orders.completed') || ($r->routeIs('admin.orders.export') && $r->input('view')==='completed');
        $r->validate(['status' => 'nullable|in:'.implode(',', array_keys(Order::STATUSES))]);
        $orders = Order::with(['payment', 'settlements', 'creator'])->where('status', $completed ? '=' : '!=', 'completed')->when($warehouseOnly, fn ($q) => $completed ? $q->where('created_by',$r->user()->id) : $q->visibleToWarehouse($r->user()->id))->when($r->filled('status'), fn ($q) => $q->where('status', $r->input('status')))->where('created_at', '>=', $from.' 00:00:00')->where('created_at', '<', Carbon::parse($to)->addDay()->startOfDay())->when($search, fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->orWhere('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%')));
        $r->validate(['per_page' => 'sometimes|in:10,20,50,100', 'sort' => 'sometimes|in:date,number,customer,creator,status', 'direction' => 'sometimes|in:asc,desc']);
        $sort = $r->input('sort', 'date');
        $direction = $r->input('direction', 'desc');
        if ($sort === 'creator') {
            $orders->orderBy(User::select('name')->whereColumn('users.id', 'orders.created_by')->limit(1), $direction);
        } else {
            $orders->orderBy(['date' => 'created_at', 'number' => 'number', 'customer' => 'first_name', 'status' => 'status'][$sort], $direction);
        }
        $orders->orderBy('id', $direction);

        return [$orders, $search, $from, $to, $warehouseOnly];
    }

    public function index(Request $r)
    {
        [$query, $search, $from, $to, $warehouseOnly] = $this->listing($r);
        $orders = $query->paginate((int) $r->input('per_page', 20))->withQueryString();

        $completed = $r->routeIs('admin.orders.completed');
        $listRoute = $completed ? 'admin.orders.completed' : 'admin.orders.index';
        return view('admin.orders.index', compact('orders', 'search', 'from', 'to', 'warehouseOnly','completed','listRoute'));
    }

    public function export(Request $r)
    {
        $r->validate(['format' => 'required|in:xlsx,pdf,print']);
        [$query, $search, $from, $to, $warehouseOnly] = $this->listing($r);
        $headers = ['Order', 'Date', 'Source', 'Fulfillment', 'Customer', 'Email', 'Created By', 'Status'];
        if (! $warehouseOnly) {
            $headers = array_merge($headers, ['Subtotal CAD', 'Payment method', 'Payment status']);
        }
        $headers = array_merge($headers, ['Delivery service','From postal','From zone','To postal','To zone','Delivery CAD']);
        $rows = function () use ($query, $warehouseOnly) {
            foreach ($query->lazy(200) as $order) {
                $row = [$order->number, $order->created_at->format('Y-m-d H:i'), $order->source==='pos'?'Quick Sale / POS':'Website', $order->fulfillment, trim($order->first_name.' '.$order->last_name), $order->email, $order->creator?->name ?? ($order->created_by ? 'Former user' : 'Website customer'), $order->status_label];
                if (! $warehouseOnly) {
                    $row = array_merge($row, [number_format($order->subtotal_cents / 100, 2, '.', ''), $order->payment_method, $order->payment_status]);
                }
                $row=array_merge($row,[$order->delivery_service_name??$order->delivery_service??($order->fulfillment==='pickup'?'Pick up':'Not recorded'),$order->delivery_from_postal??'', $order->delivery_from_zone??'', $order->delivery_to_postal??$order->postal_code, $order->delivery_to_zone??'', $order->delivery_cents===null?'':number_format($order->delivery_cents/100,2,'.','')]);
                yield $row;
            }
        };
        if ($r->input('format') !== 'xlsx') {
            return response()->view('admin.orders.export', ['headers' => $headers, 'rows' => $rows(), 'from' => $from, 'to' => $to, 'pdf' => $r->input('format') === 'pdf'])->header('Cache-Control', 'no-store, private');
        }

        return response()->streamDownload(function () use ($headers, $rows) {
            $path = tempnam(sys_get_temp_dir(), 'mmc-orders');
            $writer = new Writer;
            try {
                $writer->openToFile($path);
                $writer->addRow(Row::fromValues($headers));
                foreach ($rows() as $row) {
                    $writer->addRow(new Row(array_map(fn ($value) => new StringCell((string) $value, null), $row)));
                }
                $writer->close();
                readfile($path);
            } finally {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }, 'orders-'.now()->format('Y-m-d').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'no-store, private']);
    }

    public function printOrder(Order $order)
    {
        $this->readable($order);
        $order->load('items.product');
        if ($this->warehouseOnly() && ! ($order->source === 'pos' && (int) $order->created_by === auth()->id())) {
            return response()->view('admin.orders.packing-print', compact('order'))->header('Cache-Control', 'no-store, private');
        }

        return response()->view('orders.print', compact('order'))->header('Cache-Control', 'no-store, private');
    }

    public function updateStatus(Request $r, Order $order, OrderManagement $service)
    {
        $d = $r->validate(['revision' => 'required|integer|min:0', 'status' => 'required|in:'.implode(',', array_keys(Order::STATUSES)), 'q' => 'nullable|string|max:200', 'page' => 'nullable|integer|min:1', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d']);
        $service->update($order, [
            'revision' => $d['revision'], 'status' => $d['status'],
            'payment_status' => $order->payment_status,
            'delivery' => $order->delivery_cents === null ? '' : number_format($order->delivery_cents / 100, 2, '.', ''),
            'tax' => $order->tax_cents === null ? '' : number_format($order->tax_cents / 100, 2, '.', ''),
            'reason' => 'Status updated from Orders grid',
        ], $r->user()->id);
        if ($r->expectsJson()) {
            $order->refresh();
            $options = $order->status_options;
            $terminal = in_array($order->status, ['completed', 'cancelled']);

            return response()->json(['status' => $order->status, 'revision' => $order->revision, 'options' => $options, 'terminal' => $terminal, 'message' => 'Saved']);
        }

        return redirect()->route('admin.orders.index', array_filter(['q' => $d['q'] ?? null, 'page' => $d['page'] ?? null, 'from' => $d['from'] ?? null, 'to' => $d['to'] ?? null]))->with('status', $order->number.' status updated.');
    }

    public function update(Request $r, Order $order, OrderManagement $service)
    {
        $d = $r->validate(['revision' => 'required|integer|min:0', 'status' => 'required|in:'.implode(',', array_keys(Order::STATUSES)), 'payment_status' => 'required|in:unpaid,pending,paid,partially_refunded,refunded', 'delivery' => ['nullable', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'tax' => ['nullable', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'reason' => 'nullable|string|max:1000']);
        $service->update($order, $d, $r->user()->id);

        return redirect()->route('admin.orders.show', $order)->with('status', 'Order updated.');
    }

    public function show(Order $order)
    {
        $this->readable($order);
        $order->load(['items.product', 'events.author', 'payment', 'settlements']);
        if ($this->warehouseOnly()) {
            return view($order->source === 'pos' && !in_array($order->status,['warehouse_pending','packing','ready_to_dispatch']) ? 'admin.orders.own-sale' : 'admin.orders.warehouse', compact('order'));
        }

        return view('admin.orders.show', compact('order'));
    }
}
