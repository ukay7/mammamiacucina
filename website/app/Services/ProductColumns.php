<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductColumns
{
    public const DEFAULTS = ['status', 'image', 'qr_code', 'premium_marketing_name', 'supplier', 'product_code', 'category', 'published_price'];

    public function labels(): array
    {
        return [
            'status' => 'Status', 'image' => 'Image', 'qr_code' => 'Internal code', 'premium_marketing_name' => 'Mamma Mia name', 'supplier' => 'Supplier', 'product_code' => 'Supplier product code',
            'original_description' => 'Product name on source', 'manufacturer' => 'Manufacturer', 'recipient' => 'Purchaser on document', 'delivery_to' => 'Delivery destination on source',
            'allocated_to' => 'Internal brand allocation', 'category' => 'Category', 'gluten_status' => 'Gluten-free status', 'unit_weight_g' => 'Weight per piece (g)', 'pieces_per_carton' => 'Pieces per carton',
            'net_carton_kg' => 'Net carton weight (kg)', 'sale_unit' => 'Sell as', 'minimum_sale' => 'Minimum sale', 'purchase_price' => 'Supplier quoted price', 'purchase_basis' => 'Supplier price basis',
            'currency' => 'Purchase currency', 'unit_eur' => 'Price per piece (EUR)', 'discount' => 'Discount %', 'net_eur' => 'After discount (EUR)', 'fx' => 'CAD per 1 EUR', 'cost_cad' => 'Product cost (CAD / piece)',
            'freight_carton' => 'Freight (CAD / carton)', 'freight_unit' => 'Freight (CAD / piece)', 'other_unit_cost' => 'Other cost (CAD / piece)', 'landed_cad' => 'Total unit cost (CAD)',
            'profit_mode' => 'Pricing method', 'business_profit_percent'=>'Profit setting % (Business)', 'profit_percent' => 'Profit setting % (Individual)', 'unit_cents' => 'Calculated selling price / piece (CAD)', 'carton_cents' => 'Calculated selling price / carton (CAD)',
            'margin_percent' => 'Calculated margin %', 'business_price'=>'Current selling price CAD (Business)', 'published_price' => 'Current selling price CAD (Individual)', 'image_status' => 'Image status', 'ingredients_file' => 'Ingredients file', 'owner_notes' => 'Review notes',
        ];
    }

    public function presets(): array
    {
        return ['Identity' => ['status', 'image', 'qr_code', 'premium_marketing_name', 'supplier', 'product_code', 'original_description', 'manufacturer', 'category'],
            'Pricing' => ['qr_code', 'premium_marketing_name', 'pieces_per_carton', 'purchase_price', 'purchase_basis', 'currency', 'unit_eur', 'discount', 'net_eur', 'fx', 'cost_cad', 'freight_carton', 'freight_unit', 'other_unit_cost', 'landed_cad', 'profit_mode', 'business_profit_percent', 'profit_percent', 'unit_cents', 'carton_cents', 'margin_percent', 'published_price'],
            'Missing data' => ['qr_code', 'premium_marketing_name', 'manufacturer', 'gluten_status', 'unit_weight_g', 'pieces_per_carton', 'net_carton_kg', 'image_status', 'ingredients_file', 'owner_notes'],
            'Name and price' => ['premium_marketing_name', 'published_price', 'business_price']];
    }

    public function selected(Request $r): array
    {
        if (! $r->has('columns')) {
            return self::DEFAULTS;
        }
        $r->validate(['columns' => 'nullable|string|max:2000']);
        if (! $r->filled('columns')) {
            return [];
        }
        $keys = explode(',', $r->input('columns'));
        if (array_diff($keys, array_keys($this->labels()))) {
            throw ValidationException::withMessages(['columns' => 'Unknown product column selected.']);
        }

        return array_values(array_intersect(array_keys($this->labels()), $keys));
    }

    public function values(Product $p): array
    {
        $inputs = $p->pricingDraft?->inputs ?? [];
        $result = [];
        if ($inputs) {
            try {
                $result = app(ProductPricing::class)->calculate($inputs);
            } catch (ValidationException) {
            }
        }
        $values = array_fill_keys(array_keys($this->labels()), null);
        foreach (['qr_code', 'premium_marketing_name', 'supplier', 'product_code', 'original_description', 'unit_weight_g'] as $key) {
            $values[$key] = $p->$key;
        }
        foreach (['manufacturer', 'recipient', 'delivery_to', 'allocated_to', 'gluten_status', 'pieces_per_carton', 'net_carton_kg', 'sale_unit', 'owner_notes'] as $key) {
            $values[$key] = data_get($p->dna, $key);
        }
        foreach (['purchase_price', 'purchase_basis', 'currency', 'discount', 'fx', 'freight_carton', 'other_unit_cost', 'profit_mode', 'business_profit_percent', 'profit_percent'] as $key) {
            $values[$key] = $inputs[$key] ?? null;
        }
        foreach (['unit_eur', 'net_eur', 'cost_cad', 'freight_unit', 'landed_cad', 'margin_percent'] as $key) {
            $values[$key] = isset($result[$key]) ? number_format($result[$key], 4, '.', '') : null;
        }
        foreach (['unit_cents', 'carton_cents'] as $key) {
            $values[$key] = isset($result[$key]) ? number_format($result[$key] / 100, 2, '.', '') : null;
        }
        $values['pieces_per_carton'] = $inputs['pieces_per_carton'] ?? $values['pieces_per_carton'];
        $values['status'] = $p->is_active ? 'Active' : 'Inactive';
        $values['category'] = $p->categories->pluck('name')->join(', ');
        $values['published_price'] = $p->total_selling_price_cad; $values['business_price']=$p->business_selling_price_cad;
        $values['image'] = $p->media->first() ? route('admin.products.media', [$p, $p->media->first()]) : null;
        $values['image_status'] = $p->media->isEmpty() ? 'Missing' : ('File present · '.(data_get($p->dna, 'image_verification') ?: 'Not verified'));
        $values['ingredients_file'] = $p->documents_count > 0 ? 'File present' : (data_get($p->dna, 'ingredients_link') ? 'Linked document' : (data_get($p->dna, 'ingredients_present') ? 'Declared present' : 'Missing'));
        $minimum = [];
        if (data_get($p->dna, 'minimum_cartons')) {
            $minimum[] = data_get($p->dna, 'minimum_cartons').' cartons';
        }if (data_get($p->dna, 'minimum_pieces')) {
            $minimum[] = data_get($p->dna, 'minimum_pieces').' pieces';
        }$values['minimum_sale'] = implode(' / ', $minimum) ?: null;
        $values['profit_mode'] = match ($values['profit_mode']) {
            'markup' => 'Markup on cost','margin' => 'Target margin',default => null
        };

        return $values;
    }
}
