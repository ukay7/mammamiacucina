<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\SupplierDirectory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:200', 'active' => 'nullable|in:0,1']);
        $suppliers = Supplier::withCount('products', 'orders')->when(filled($data['q'] ?? null), function ($q) use ($data) {
            $q->where(fn ($q) => $q->where('name', 'like', '%'.$data['q'].'%')->orWhere('contact_name', 'like', '%'.$data['q'].'%')
                ->orWhere('email', 'like', '%'.$data['q'].'%')->orWhere('phone', 'like', '%'.$data['q'].'%')
                ->when(ctype_digit(ltrim($data['q'], '#')), fn ($q) => $q->orWhere('id', ltrim($data['q'], '#'))));
        })->when(isset($data['active']), fn ($q) => $q->where('is_active', $data['active']))->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('admin.suppliers.form', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function edit(Supplier $supplier)
    {
        return view('admin.suppliers.form', compact('supplier'));
    }

    public function show(Supplier $supplier)
    {
        $supplier->loadCount('products', 'orders');
        $products = $supplier->products()->orderBy('premium_marketing_name')->paginate(25)->withQueryString();
        $orders = $supplier->orders()->latest('id')->take(10)->get();

        return view('admin.suppliers.show', compact('supplier', 'products', 'orders'));
    }

    public function store(Request $request)
    {
        return $this->save($request, new Supplier);
    }

    public function update(Request $request, Supplier $supplier)
    {
        return $this->save($request, $supplier);
    }

    private function save(Request $request, Supplier $supplier)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:60', 'address' => 'nullable|string|max:3000',
            'notes' => 'nullable|string|max:5000', 'is_active' => 'required|boolean', 'revision' => 'required|integer|min:0']);
        $data['name'] = SupplierDirectory::name($data['name']);
        if ($data['name'] === '') {
            throw ValidationException::withMessages(['name' => 'Enter a supplier name.']);
        }
        try {
            $supplier = DB::transaction(function () use ($supplier, $data) {
                if ($supplier->exists) {
                    $supplier = Supplier::whereKey($supplier->id)->lockForUpdate()->firstOrFail();
                    if ((int) $supplier->revision !== (int) $data['revision']) {
                        throw ValidationException::withMessages(['supplier' => 'This supplier changed. Reload before saving.']);
                    }
                }
                if (Supplier::where('name_key', SupplierDirectory::key($data['name']))->when($supplier->exists, fn ($q) => $q->where('id', '!=', $supplier->id))->exists()) {
                    throw ValidationException::withMessages(['name' => 'A supplier with this name already exists.']);
                }
                $data['revision'] = $supplier->exists ? $supplier->revision + 1 : 0;
                $supplier->fill($data)->save();

                return $supplier;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'A supplier with this name already exists.']);
        }

        return redirect()->route('admin.suppliers.show', $supplier)->with('status', 'Supplier saved.');
    }

    public function destroy(Supplier $supplier)
    {
        DB::transaction(function () use ($supplier) {
            $supplier = Supplier::whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            if ($supplier->products()->exists() || $supplier->orders()->exists()) {
                throw ValidationException::withMessages(['supplier' => 'This supplier has linked products or orders. Mark it inactive instead of deleting its history.']);
            }
            $supplier->delete();
        }, 3);

        return redirect()->route('admin.suppliers.index')->with('status', 'Unused supplier deleted.');
    }
}
