<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductPricing
{
    public function validate(array $data): array
    {
        return Validator::make($data, [
            'purchase_price' => 'required|numeric|min:0|max:1000000', 'purchase_basis' => 'required|in:piece,carton,pack,kg', 'currency' => 'required|in:EUR,CAD',
            'pieces_per_carton' => 'required|integer|min:1|max:100000', 'units_per_pack' => 'nullable|integer|min:1|max:100000', 'grams' => 'nullable|numeric|gt:0|max:1000000',
            'discount' => ['required', 'string', 'max:80', 'regex:/^\d+(\.\d+)?(\+\d+(\.\d+)?)*$/'], 'source_discount' => 'nullable|string|max:80',
            'fx' => 'required|numeric|gt:0|max:1000', 'freight_carton' => 'required|numeric|min:0|max:1000000', 'other_unit_cost' => 'required|numeric|min:0|max:1000000',
            'business_profit_percent'=>'nullable|numeric|min:0|max:10000','profit_mode' => 'required|in:markup,margin', 'profit_percent' => 'required|numeric|min:0|max:10000', 'carton_status' => 'required|in:working,source,verified,conflict',
        ])->validate();
    }

    public function calculate(array $data, array $options = []): array
    {
        $d = $this->validate($data);
        if ($d['carton_status'] === 'conflict') {
            $this->fail('pieces_per_carton', 'Resolve conflicting carton quantities before calculating.');
        }
        $factor = 1;
        foreach (explode('+', $d['discount']) as $discount) {
            if ((float) $discount > 100) {
                $this->fail('discount', 'Each discount must be between 0 and 100.');
            }$factor *= 1 - (float) $discount / 100;
        }
        $divisor = match ($d['purchase_basis']) {
            'piece' => 1,'carton' => $d['pieces_per_carton'],'pack' => $d['units_per_pack'] ?? null,'kg' => ! empty($d['grams']) ? 1000 / $d['grams'] : null
        };
        if (! $divisor) {
            $this->fail('purchase_basis', 'Enter pieces in the supplier-priced pack or weight per piece for the selected price basis.');
        }
        if ($d['profit_mode'] === 'margin' && $d['profit_percent'] >= 100) {
            $this->fail('profit_percent', 'Margin must be below 100%.');
        }
        $unit = (float) $d['purchase_price'] / $divisor;
        $eur = $d['currency'] === 'EUR' ? $unit : $unit / $d['fx'];
        $net = $eur * $factor;
        $cad = $net * $d['fx'];
        $freight = $d['freight_carton'] / $d['pieces_per_carton'];
        $landed = $cad + $freight + $d['other_unit_cost'];
        $sale = $d['profit_mode'] === 'margin' ? $landed / (1 - $d['profit_percent'] / 100) : $landed * (1 + $d['profit_percent'] / 100);
        if (! is_finite($sale) || $sale > 1000000000) {
            $this->fail('profit_percent', 'Calculated price is too large. Check the margin or purchase price.');
        }
        $cents = (int) round($sale * 100);
        if ($cents > 100000000000 || $cents * $d['pieces_per_carton'] > 100000000000) {
            $this->fail('purchase_price', 'Calculated price is too large.');
        }
        $options = Validator::make(['options' => $options], ['options' => 'array|max:20', 'options.*' => 'array:label,quantity,discount', 'options.*.label' => 'required|string|max:80', 'options.*.quantity' => 'required|integer|min:1|max:100000', 'options.*.discount' => 'required|numeric|min:0|max:100'])->validate()['options'];
        $rows = [];
        foreach ($options as $o) {
            $base = $cents * $o['quantity'];
            if ($base > 100000000000) {
                $this->fail('options', 'A pack total is too large.');
            }$total = (int) round($base * (1 - $o['discount'] / 100));
            $rows[] = $o + ['base_cents' => $base, 'total_cents' => $total, 'effective_unit_cents' => (int) round($total / $o['quantity'])];
        }

        $business=$d['business_profit_percent']??$d['profit_percent'];
        if($d['profit_mode']==='margin' && $business>=100)$this->fail('business_profit_percent','Business margin must be below 100%.');
        $businessSale=$d['profit_mode']==='margin'?$landed/(1-$business/100):$landed*(1+$business/100);
        $businessCents=(int)round($businessSale*100);
        if(!is_finite($businessSale)||$businessSale>1000000000||$businessCents*$d['pieces_per_carton']>100000000000)$this->fail('business_profit_percent','Business calculated price is too large.');
        return ['business_unit_cents'=>$businessCents,'business_carton_cents'=>$businessCents*$d['pieces_per_carton'],'unit_eur' => $eur, 'discount_percent' => (1 - $factor) * 100, 'net_eur' => $net, 'cost_cad' => $cad, 'freight_unit' => $freight, 'landed_cad' => $landed, 'unit_cents' => $cents, 'carton_cents' => $cents * $d['pieces_per_carton'], 'margin_percent' => $cents > 0 ? (($cents / 100 - $landed) / ($cents / 100)) * 100 : null, 'options' => $rows];
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
