<?php

namespace App\Services;

use App\Models\Product;
use App\Support\NumberDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryColumns
{
    public const DEFAULTS = ['status', 'image', 'qr_code', 'premium_marketing_name', 'product_code', 'category', 'uom'];

    public function labels(): array
    {
        return ['record_id' => 'Record ID'] + app(ProductColumns::class)->labels() + ['uom' => 'UoM'];
    }

    public function presets(): array
    {
        return ['Compact' => ['premium_marketing_name', 'product_code', 'category', 'uom']]
            + app(ProductColumns::class)->presets();
    }

    public function selected(Request $request): array
    {
        $request->validate(['columns' => 'nullable|string|max:2000']);
        if (! $request->has('columns')) {
            return self::DEFAULTS;
        }
        $keys = array_values(array_unique(array_filter(explode(',', (string) $request->input('columns')))));
        if (array_diff($keys, array_keys($this->labels()))) {
            throw ValidationException::withMessages(['columns' => 'Unknown inventory column selected.']);
        }

        return array_values(array_intersect(array_keys($this->labels()), $keys));
    }

    public function headers(array $selected): array
    {
        return array_intersect_key($this->labels(), array_flip($selected)) + ['stock_quantity' => 'Current stock'];
    }

    public function withColumns(Builder $query): Builder
    {
        return $query->withCount('documents')->with(['inventory', 'categories', 'pricingDraft', 'media' => fn ($q) => $q->coverImage()]);
    }

    public function values(Product $product): array
    {
        $values = ['record_id' => $product->id] + app(ProductColumns::class)->values($product)
            + ['uom' => $product->uom, 'stock_quantity' => $product->stock_quantity];
        $image = $product->media->first();
        $values['image'] = $image ? route('admin.inventory.media', [$product, $image]) : null;

        return $values;
    }

    public function format(mixed $value, string $key): string
    {
        return $key === 'stock_quantity' ? NumberDisplay::quantity($value, 'Not set') : NumberDisplay::field($value, $key);
    }

    public function numeric(string $key): bool
    {
        return $key === 'record_id' || NumberDisplay::mode($key) !== null;
    }

    /** Keep all matching rows, in table order, while loading related data in batches. */
    public function rows(Builder $query, bool $embedImages = false): \Generator
    {
        foreach ((clone $query)->lazy(200)->chunk(200) as $batch) {
            $batch = $batch->values();
            $images = $embedImages ? app(DocumentProductImages::class)->forItems(
                $batch->map(fn ($product) => ['product_id' => $product->id])->all()
            ) : [];
            foreach ($batch as $index => $product) {
                $values = $this->values($product);
                if ($embedImages) {
                    $values['image'] = $images[$index] ?? null;
                }
                yield $values;
            }
        }
    }

    /** Wide selections use A4 sections, repeating a selected identifier and stock. */
    public function printGroups(array $selected): array
    {
        $identity = collect(['premium_marketing_name', 'qr_code', 'product_code', 'record_id'])
            ->first(fn ($key) => in_array($key, $selected, true));
        $details = array_values(array_diff($selected, [$identity]));
        $groups = [];
        foreach (array_chunk($details, 6) ?: [[]] as $chunk) {
            $keys = $identity ? array_merge([$identity], $chunk) : $chunk;
            $groups[] = $this->headers($keys);
        }

        return $groups;
    }

    public function printWidths(array $headers): array
    {
        $widths = ['stock_quantity' => 10];
        if (isset($headers['image'])) {
            $widths['image'] = 7;
        }
        $weights = [];
        foreach (array_keys($headers) as $key) {
            if (isset($widths[$key])) {
                continue;
            }
            $weights[$key] = match ($key) {
                'premium_marketing_name', 'owner_notes' => 2.5,
                'original_description' => 2.8,
                'category' => 2,
                'supplier', 'manufacturer', 'recipient', 'delivery_to', 'allocated_to' => 1.8,
                'record_id' => 0.7, 'status' => 0.9, 'qr_code' => 1, 'uom' => 0.6,
                default => 1.25,
            };
        }
        $remaining = 100 - array_sum($widths);
        if (! $weights) {
            $widths[isset($headers['image']) ? 'image' : 'stock_quantity'] += $remaining;
        } else {
            $total = array_sum($weights);
            foreach ($weights as $key => $weight) {
                $widths[$key] = round($remaining * $weight / $total, 3);
            }
        }
        $widths['stock_quantity'] += 100 - array_sum($widths);

        return $widths;
    }
}
