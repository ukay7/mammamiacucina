<?php

namespace App\Services;

use App\Models\SupplierOrder;
use App\Support\NumberDisplay;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierOrderDocument
{
    public const DEFAULTS = ['serial', 'product_code', 'name', 'quantity', 'unit_cost', 'line_total'];

    public const PRODUCT_DETAILS = ['original_description', 'manufacturer', 'allocated_to'];

    public function labels(): array
    {
        return [
            'serial' => 'Serial number', 'image' => 'Product image', 'product_id' => 'Product record ID',
            'product_code' => 'Supplier product code', 'barcode' => 'Internal code / barcode',
            'name' => 'Product name', 'original_description' => 'Product name on source',
            'manufacturer' => 'Manufacturer', 'allocated_to' => 'Internal brand allocation',
            'supplier' => 'Supplier name', 'quantity' => 'Order quantity (cartons)',
            'currency' => 'Purchase currency', 'purchase_price' => 'Supplier quoted price',
            'purchase_basis' => 'Supplier price basis', 'pieces_per_carton' => 'Pieces per carton',
            'discount' => 'Supplier discount %', 'discounted_price' => 'Discounted quoted price',
            'unit_cost' => 'Cost per carton', 'line_total' => 'Line total',
            'stock_quantity' => 'Stock quantity when ordered', 'stock_value' => 'Stock value when ordered',
        ];
    }

    public function selected(Request $request): array
    {
        $data = $request->validate(['columns' => 'sometimes|required|string|max:1000']);
        $selected = isset($data['columns']) ? explode(',', $data['columns']) : self::DEFAULTS;
        if (array_diff($selected, array_keys($this->labels())) || count($selected) !== count(array_unique($selected))) {
            throw ValidationException::withMessages(['columns' => 'Choose valid, distinct supplier order columns.']);
        }

        // Preserve the user's selection order, including deliberate rearrangement.
        return $selected;
    }

    public function rows(SupplierOrder $order, bool $withImages = false): array
    {
        $order->loadMissing('items.product');
        $images = $withImages ? app(DocumentProductImages::class)->forItems($order->items->map(fn ($item) => [
            'product_id' => $item->product_id, 'qr_code' => $item->barcode, 'product_code' => $item->product_code,
        ])->all()) : [];

        return $order->items->values()->map(function ($item, $index) use ($order, $images) {
            $snapshot = $item->pricing_snapshot ?? [];
            $valuation = $snapshot['valuation'] ?? [];
            $details = $snapshot['product_details'] ?? [
                'original_description' => $item->product?->original_description,
                'manufacturer' => data_get($item->product?->dna, 'manufacturer'),
                'allocated_to' => data_get($item->product?->dna, 'allocated_to'),
            ];
            $money = fn ($value, $places = 8) => $value === null || $value === '' ? '—'
                : $item->currency.' '.NumberDisplay::decimal($value, $places, $places !== 2);

            return [
                'serial' => (string) ($index + 1), 'image' => $images[$index] ?? null,
                'product_id' => (string) ($item->product_id ?? '—'),
                'product_code' => $item->product_code ?: '—', 'barcode' => $item->barcode ?: '—',
                'name' => $item->name, 'original_description' => ($details['original_description'] ?? null) ?: '—',
                'manufacturer' => ($details['manufacturer'] ?? null) ?: '—', 'allocated_to' => ($details['allocated_to'] ?? null) ?: '—',
                'supplier' => $order->supplier_snapshot['name'] ?? '—',
                'quantity' => NumberDisplay::quantity($item->quantity), 'currency' => $item->currency,
                'purchase_price' => $money($valuation['purchase_price'] ?? null),
                'purchase_basis' => isset($valuation['purchase_basis']) ? ucfirst($valuation['purchase_basis']) : '—',
                'pieces_per_carton' => NumberDisplay::quantity($valuation['pieces_per_carton'] ?? null),
                'discount' => isset($valuation['discount']) ? NumberDisplay::decimal($valuation['discount'], 4, true).'%' : '—',
                'discounted_price' => $money($valuation['discounted_price'] ?? null),
                'unit_cost' => $money($item->unit_cost), 'line_total' => $money($item->line_total, 2),
                'stock_quantity' => NumberDisplay::quantity($snapshot['stock_quantity'] ?? null, 'Not set'),
                'stock_value' => $money($valuation['inventory_value'] ?? null, 2),
            ];
        })->all();
    }

    public function notes(SupplierOrder $order, array $selected): array
    {
        $notes = ['Quantities, supplier details and purchase prices are saved order values. This document is not an invoice or proof of payment.'];
        if (array_intersect(self::PRODUCT_DETAILS, $selected) && $order->items->contains(fn ($item) => ! array_key_exists('product_details', $item->pricing_snapshot ?? []))) {
            $notes[] = 'Source name, manufacturer and brand were not saved on older orders; those fields use current product details where available. Saved order names, codes, quantities and prices remain unchanged.';
        }
        if (array_intersect(['stock_quantity', 'stock_value'], $selected)) {
            $notes[] = 'Stock columns are historical values from order placement, not current inventory.';
        }
        if (in_array('image', $selected, true)) {
            $notes[] = 'Product images use the currently available cover photos; saved order quantities and prices are unchanged.';
        }

        return $notes;
    }

    public function groups(array $selected): array
    {
        if (count($selected) <= 8) {
            return [$selected];
        }
        // Repeat only identifiers the user selected, so wide exports stay readable on A4.
        $identity = array_values(array_intersect($selected, ['serial', 'product_code', 'barcode', 'name']));
        $groups = [];
        $remaining = $selected;
        while ($remaining) {
            $repeated = $groups ? array_values(array_diff($identity, $remaining)) : [];
            $groups[] = array_merge($repeated, array_splice($remaining, 0, 8 - count($repeated)));
        }

        return $groups;
    }
}
