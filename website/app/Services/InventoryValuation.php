<?php

namespace App\Services;

use App\Models\Product;
use App\Support\NumberDisplay;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class InventoryValuation
{
    public const BASE_COLUMNS = [
        'record_id' => 'Record ID', 'product_code' => 'Product code',
        'premium_marketing_name' => 'Product name', 'currency' => 'Purchase currency',
        'purchase_basis' => 'Supplier price basis', 'purchase_price' => 'Supplier quoted price',
        'discount' => 'Supplier discount %', 'discounted_price' => 'Discounted price',
        'pieces_per_carton' => 'Pieces per carton',
        'carton_cost' => 'Cost per carton', 'inventory_quantity' => 'Inventory quantity (cartons)',
        'inventory_value' => 'Total value', 'valuation_status' => 'Valuation status',
    ];

    public function filters(Request $request, InventoryProducts $inventory): array
    {
        return $inventory->filters($request) + $request->validate([
            'currency' => 'nullable|in:EUR,CAD,unknown',
            'setup' => 'nullable|in:ready,needs_setup',
        ]);
    }

    public function query(InventoryProducts $inventory, array $filters): Builder
    {
        // Stock/search filters stay in SQL. Money filters use the same exact
        // discounted carton calculation as the totals, table and exports.
        return $inventory->query(Arr::except($filters, [
            'pricing', 'price_min', 'price_max', 'value_min', 'value_max',
        ]), isset($filters['warehouse']) ? (int) $filters['warehouse'] : null);
    }

    public function extraLabels(): array
    {
        return array_diff_key(app(ProductColumns::class)->labels(), self::BASE_COLUMNS);
    }

    public function selected(Request $request): array
    {
        $request->validate(['columns' => 'nullable|string|max:2000']);
        $selected = array_values(array_unique(array_filter(explode(',', (string) $request->input('columns', '')))));
        if (array_diff($selected, array_keys($this->extraLabels() + self::BASE_COLUMNS))) {
            throw ValidationException::withMessages(['columns' => 'Unknown product column selected.']);
        }

        return array_values(array_intersect(array_keys($this->extraLabels()), $selected));
    }

    public function headers(array $selected): array
    {
        return self::BASE_COLUMNS + array_intersect_key($this->extraLabels(), array_flip($selected));
    }

    public function withColumns(Builder $query): Builder
    {
        return $query->withCount('documents')->with(['categories', 'pricingDraft', 'media' => fn ($q) => $q->coverImage()]);
    }

    /** Stream lightweight batches; images and extra fields are loaded only when needed. */
    public function rows(Builder $query, array $filters): \Generator
    {
        foreach ((clone $query)->with('pricingDraft')->lazy(200) as $product) {
            $product->setAttribute('valuation', $this->calculate($product));
            if ($this->matches($product->valuation, $filters)) {
                yield $product;
            }
        }
    }

    public function page(Builder $query, array $filters, int $page, int $perPage): array
    {
        $summary = $this->emptySummary();
        $visible = new Collection;
        $offset = ($page - 1) * $perPage;
        foreach ($this->rows($query, $filters) as $product) {
            $this->accumulate($summary, $product);
            if ($summary['records'] > $offset && $visible->count() < $perPage) {
                $visible->push($product);
            }
        }
        $visible->loadCount('documents')->load(['categories', 'media' => fn ($q) => $q->coverImage()]);

        return [$this->finishSummary($summary), (new LengthAwarePaginator(
            $visible, $summary['records'], $perPage, $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        ))->withQueryString()];
    }

    public function summary(Builder $query, array $filters): array
    {
        $summary = $this->emptySummary();
        foreach ($this->rows($query, $filters) as $product) {
            $this->accumulate($summary, $product);
        }

        return $this->finishSummary($summary);
    }

    private function emptySummary(): array
    {
        return ['records' => 0, 'missing_prices' => 0, 'unset_quantities' => 0, 'needs_setup' => 0,
            'stock_quantity' => BigDecimal::zero(),
            'currencies' => array_fill_keys(['EUR', 'CAD'], ['products' => 0, 'total' => BigDecimal::zero(), 'needs_setup' => 0])];
    }

    private function accumulate(array &$summary, Product $product): void
    {
        $value = $product->valuation;
        $summary['records']++;
        $summary['missing_prices'] += (int) $value['missing_price'];
        $summary['unset_quantities'] += (int) ($product->stock_quantity === null);
        $summary['needs_setup'] += (int) ! empty($value['issues']);
        // The fixed CAD estimate uses physical stock even when supplier pricing needs setup.
        $summary['stock_quantity'] = $summary['stock_quantity']->plus((string) ($product->stock_quantity ?? '0'));
        if (isset($summary['currencies'][$value['currency']])) {
            $group = &$summary['currencies'][$value['currency']];
            $group['products']++;
            $group['needs_setup'] += (int) ! empty($value['issues']);
            if ($value['inventory_value'] !== null) {
                $group['total'] = $group['total']->plus($value['inventory_value']);
            }
        }
    }

    private function finishSummary(array $summary): array
    {
        $summary['stock_estimate_cad'] = (string) $summary['stock_quantity']->multipliedBy(5)->toScale(2, RoundingMode::HalfUp);
        $summary['stock_quantity'] = (string) $summary['stock_quantity'];
        foreach ($summary['currencies'] as &$group) {
            $group['total'] = (string) $group['total']->toScale(2);
        }

        return $summary;
    }

    public function calculate(Product $product): array
    {
        $inputs = $product->pricingDraft?->inputs ?? [];
        $currency = strtoupper(trim((string) ($inputs['currency'] ?? '')));
        $basis = strtolower(trim((string) ($inputs['purchase_basis'] ?? '')));
        $pieces = $inputs['pieces_per_carton'] ?? data_get($product->dna, 'pieces_per_carton');
        $quoted = $inputs['purchase_price'] ?? null;
        $issues = [];
        if (! in_array($currency, ['EUR', 'CAD'], true)) {
            $issues[] = 'Set purchase currency to EUR or CAD.';
        }
        if (! in_array($basis, ['piece', 'carton'], true)) {
            $issues[] = 'Set supplier price basis to Piece or Carton.';
        }
        if ($basis === 'piece' && (! is_numeric($pieces) || (float) $pieces <= 0 || (float) $pieces > 100000 || floor((float) $pieces) !== (float) $pieces)) {
            $issues[] = 'Enter a valid pieces-per-carton quantity.';
        }
        if ($basis === 'piece' && ($inputs['carton_status'] ?? null) === 'conflict') {
            $issues[] = 'Resolve conflicting carton quantities.';
        }
        $missing = ! is_numeric($quoted) || (float) $quoted <= 0;
        $invalidPrice = $quoted !== null && $quoted !== '' && (! is_numeric($quoted) || ! is_finite((float) $quoted) || (float) $quoted < 0 || (float) $quoted > 1000000);
        $price = BigDecimal::of($missing || $invalidPrice ? '0' : (string) $quoted);
        if ($invalidPrice) {
            $issues[] = 'Enter a valid supplier quoted price.';
        }
        $discount = trim((string) ($inputs['discount'] ?? '0'));
        $discount = $discount === '' ? '0' : $discount;
        $validDiscount = true;
        if (strlen($discount) > 80 || ! preg_match('/^\d+(\.\d+)?(\+\d+(\.\d+)?)*$/D', $discount)) {
            $validDiscount = false;
            $issues[] = 'Enter a valid supplier discount.';
        } else {
            // Preserve the pricing form's existing sequential discount convention.
            foreach (explode('+', $discount) as $part) {
                $percent = BigDecimal::of($part);
                if ($percent->isGreaterThan(100)) {
                    $validDiscount = false;
                    $issues[] = 'Supplier discounts must be between 0 and 100%.';
                    break;
                }
                $price = $price->multipliedBy(BigDecimal::one()->minus($percent->exactlyDividedBy(100)));
            }
        }
        $cost = null;
        $total = null;
        if (! $issues) {
            $cost = $basis === 'piece' ? $price->multipliedBy((string) $pieces) : $price;
            // Round once per inventory line, then sum those rounded lines exactly.
            $total = (string) $cost->multipliedBy((string) ($product->stock_quantity ?? '0'))->toScale(2, RoundingMode::HalfUp);
        }

        return ['currency' => $currency ?: null, 'purchase_basis' => $basis ?: null,
            'purchase_price' => $invalidPrice ? null : (string) BigDecimal::of($missing ? '0' : (string) $quoted),
            'discount' => $discount, 'discounted_price' => $validDiscount && ! $invalidPrice ? (string) $price : null,
            'pieces_per_carton' => $pieces, 'carton_cost' => $cost === null ? null : (string) $cost,
            'inventory_value' => $total, 'missing_price' => $missing, 'issues' => $issues,
            'valuation_status' => $issues ? 'Needs setup' : ($missing ? 'Missing price' : 'Priced')];
    }

    private function matches(array $values, array $filters): bool
    {
        if (filled($filters['currency'] ?? null) && ($filters['currency'] === 'unknown'
            ? in_array($values['currency'], ['EUR', 'CAD'], true) : $values['currency'] !== $filters['currency'])) {
            return false;
        }
        if ((($filters['setup'] ?? '') === 'needs_setup' && ! $values['issues'])
            || (($filters['setup'] ?? '') === 'ready' && $values['issues'])
            || (($filters['pricing'] ?? '') === 'missing' && ! $values['missing_price'])
            || (($filters['pricing'] ?? '') === 'priced' && $values['missing_price'])) {
            return false;
        }
        foreach (['price' => 'carton_cost', 'value' => 'inventory_value'] as $filter => $key) {
            foreach (['min', 'max'] as $bound) {
                if (isset($filters[$filter.'_'.$bound])) {
                    if ($values[$key] === null) {
                        return false;
                    }
                    $comparison = BigDecimal::of($values[$key])->compareTo((string) $filters[$filter.'_'.$bound]);
                    if (($bound === 'min' && $comparison < 0) || ($bound === 'max' && $comparison > 0)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function values(Product $product, bool $withExtras = true): array
    {
        return [
            'record_id' => $product->id,
            'product_code' => $product->product_code,
            'premium_marketing_name' => $product->premium_marketing_name,
            'currency' => $product->valuation['currency'],
            'purchase_basis' => $product->valuation['purchase_basis'] === null ? null : ucfirst($product->valuation['purchase_basis']),
            'purchase_price' => $product->valuation['purchase_price'],
            'discount' => $product->valuation['discount'],
            'discounted_price' => $product->valuation['discounted_price'],
            'pieces_per_carton' => $product->valuation['pieces_per_carton'],
            'carton_cost' => $product->valuation['carton_cost'],
            'inventory_quantity' => $product->stock_quantity,
            'inventory_value' => $product->valuation['inventory_value'],
            'valuation_status' => $product->valuation['valuation_status'].($product->valuation['issues'] ? ': '.implode(' ', $product->valuation['issues']) : ''),
        ] + ($withExtras ? app(ProductColumns::class)->values($product) : []);
    }

    public function missingPrice(Product $product): bool
    {
        return $product->valuation['missing_price'];
    }

    public function format(mixed $value, string $key): string
    {
        return match ($key) {
            'purchase_price', 'discounted_price', 'carton_cost' => NumberDisplay::decimal($value, 8, true),
            'discount' => $value === null ? '—' : (is_numeric($value) ? NumberDisplay::decimal($value, 4, true) : (string) $value).'%',
            'inventory_value' => $value === null ? 'Excluded' : NumberDisplay::decimal($value),
            'inventory_quantity' => NumberDisplay::quantity($value, 'Not set'),
            default => NumberDisplay::field($value, $key),
        };
    }

    public function numeric(string $key): bool
    {
        return in_array($key, ['record_id', 'carton_cost', 'inventory_quantity', 'inventory_value'], true)
            || NumberDisplay::mode($key) !== null;
    }
}
