<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPricingDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductPriceWorkflow
{
    public function savePrice(Product $product, array $inputs, int $revision, int $editorRevision, string $choice, int $user): array
    {
        $result = app(ProductPricing::class)->calculate($inputs);
        $cents = $choice === 'carton' ? $result['carton_cents'] : $result['unit_cents'];

        return DB::transaction(function () use ($product, $inputs, $revision, $editorRevision, $choice, $user, $result, $cents) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $settings = $product->pricingDraft()->lockForUpdate()->first();
            if (($settings?->revision ?? 0) !== $revision || (int) $product->editor_revision !== $editorRevision) {
                throw ValidationException::withMessages(['pricing' => 'This product or pricing changed. Reload before saving.']);
            }
            $previous = $product->total_selling_price_cad;
            // Retain the existing storage and history without an approval stage.
            $settings = ProductPricingDraft::updateOrCreate(['product_id' => $product->id], ['inputs' => $inputs, 'options' => $settings?->options ?? [], 'revision' => $revision + 1, 'updated_by' => $user]);
            $product->priceReviews()->where('status', 'pending')->update(['status' => 'superseded']);
            $product->priceReviews()->create(['draft_revision' => $settings->revision, 'snapshot' => ['inputs' => $inputs, 'result' => $result, 'choice' => $choice], 'previous_price' => $previous, 'proposed_cents' => $cents, 'sale_label' => $choice === 'carton' ? 'Carton: '.$inputs['pieces_per_carton'].' pieces' : '1 piece', 'status' => 'saved', 'submitted_by' => $user, 'reviewed_by' => null, 'reviewed_at' => null]);
            $product->total_selling_price_cad = number_format($cents / 100, 2, '.', '');
            $product->business_selling_price_cad=number_format(($choice==='carton'?$result['business_carton_cents']:$result['business_unit_cents'])/100,2,'.','');
            $product->editor_revision++;
            $product->save();

            return ['revision' => $settings->revision, 'editor_revision' => $product->editor_revision, 'business_price'=>$product->business_selling_price_cad, 'price' => $product->total_selling_price_cad, 'result' => $result, 'message' => 'Price saved. The website and POS now use this selling price.'];
        });
    }
}
