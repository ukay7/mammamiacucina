<?php

namespace App\Services;

use Illuminate\Validation\Rule;

class ProductData
{
    public static function rules(?int $id = null, bool $unique = true): array
    {
        $rules = [];
        foreach (config('product_fields') as $key => $field) {
            $required = in_array($key, ['supplier', 'qr_code', 'premium_marketing_name']);
            $rules[$key] = [$required ? 'required' : 'nullable'];
            if ($field[1] === 'decimal') {
                $rules[$key] = [...$rules[$key], 'numeric', 'min:0', 'max:999999999999'];
            } else {
                $rules[$key] = [...$rules[$key], 'string', 'max:'.($field[1] === 'textarea' ? 30000 : 255)];
            }
        }
        if ($unique) {
            $rules['qr_code'][] = Rule::unique('products', 'qr_code')->ignore($id);
        }

        return $rules;
    }

    public static function attributes(): array
    {
        return array_map(fn ($f) => $f[0], config('product_fields'));
    }
}
