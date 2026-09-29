<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductPriceWorkflow;
use App\Services\ProductPricing;
use Illuminate\Http\Request;

class ProductPricingController extends Controller
{
    private function payload(Request $r): array
    {
        return $r->validate([
            'inputs' => 'required|array:purchase_price,purchase_basis,currency,pieces_per_carton,units_per_pack,grams,discount,source_discount,fx,freight_carton,other_unit_cost,profit_mode,profit_percent,business_profit_percent,carton_status',
            'inputs.*' => 'nullable|string|max:100',
        ]);
    }

    public function quote(Request $r, Product $product, ProductPricing $pricing)
    {
        $data = $this->payload($r);

        return response()->json($pricing->calculate($data['inputs']));
    }

    public function save(Request $r, Product $product, ProductPriceWorkflow $workflow)
    {
        $data = $this->payload($r);
        $state = $r->validate(['revision' => 'required|integer|min:0', 'editor_revision' => 'required|integer|min:0', 'choice' => 'required|in:unit,carton']);

        return response()->json($workflow->savePrice($product, $data['inputs'], (int) $state['revision'], (int) $state['editor_revision'], $state['choice'], $r->user()->id));
    }
}
