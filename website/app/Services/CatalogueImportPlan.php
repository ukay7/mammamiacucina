<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Plan all matches and changes before saving any product. */
class CatalogueImportPlan
{
    public function build(iterable $rows, ProductImportMatcher $matcher): array
    {
        $plan = [];
        $targets = [];
        $qrTargets = [];
        foreach ($rows as $row) {
            $data = $row->normalized_data;
            $product = null;
            $errors = [];
            $action = 'insert';
            try {
                $product = $matcher->match($data);
            } catch (ValidationException $e) {
                $errors = $e->validator->errors()->all();
            }
            if (! $product) {
                $errors = array_merge($errors, Validator::make($data, ProductData::rules(null, false), [], ProductData::attributes())->errors()->all());
            }
            $qr = ProductImportMatcher::key($data['qr_code'] ?? $product?->qr_code);
            if ($qr !== '' && isset($qrTargets[$qr])) {
                $errors[] = 'Another row supplies this QR code. Keep one row per product.';
            }
            if ($qr !== '') {
                $qrTargets[$qr] = true;
            }
            if ($product) {
                $rules = ProductData::rules(null, false);
                // Blank spreadsheet cells retain the existing value, including identifiers.
                foreach (['supplier', 'premium_marketing_name', 'qr_code'] as $field) {
                    $rules[$field][0] = 'nullable';
                }
                $errors = array_merge($errors, Validator::make($data, $rules, [], ProductData::attributes())->errors()->all());
                if (isset($targets[$product->id])) {
                    $errors[] = 'Another row already updates this product ('.$targets[$product->id].').';
                }
                $targets[$product->id] = $row->source_sheet.' row '.$row->row_number;
                $candidate = clone $product;
                $candidate->fill(array_filter($data, fn ($value) => $value !== null && $value !== ''));
                $action = $errors ? 'invalid' : ($candidate->isDirty() ? 'ready' : 'unchanged');
            }
            $plan[] = ['row' => $row, 'product' => $product, 'errors' => $errors, 'action' => $errors ? 'invalid' : $action];
        }

        return $plan;
    }
}
