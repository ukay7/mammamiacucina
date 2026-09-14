<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

/** Consistent matching across SQLite and MySQL, including case and whitespace. */
class ProductImportMatcher
{
    private array $codes = [];

    private array $qrCodes = [];

    private array $keysById = [];

    public function __construct(iterable $products)
    {
        foreach ($products as $product) {
            $this->remember($product);
        }
    }

    public static function key(?string $value, bool $name = false): string
    {
        $value = trim((string) $value);
        if ($name) {
            $value = preg_replace('/\s+/u', ' ', $value);
        }

        return mb_strtolower($value, 'UTF-8');
    }

    public function remember(Product $product): void
    {
        // Remove the old keys when an update changes code or QR code.
        foreach ($this->keysById[$product->id] ?? [] as $index => $key) {
            unset($this->{$index}[$key][$product->id]);
            if (! $this->{$index}[$key]) {
                unset($this->{$index}[$key]);
            }
        }
        $this->keysById[$product->id] = [];
        foreach (['codes' => 'product_code', 'qrCodes' => 'qr_code'] as $index => $field) {
            $key = self::key($product->$field);
            if ($key !== '') {
                $this->{$index}[$key][$product->id] = $product;
                $this->keysById[$product->id][$index] = $key;
            }
        }
    }

    public function match(array $data): ?Product
    {
        $codes = $this->codes[self::key($data['product_code'] ?? null)] ?? [];
        $qrs = $this->qrCodes[self::key($data['qr_code'] ?? null)] ?? [];
        // Code is authoritative; when the QR code also matches, both must identify a common record.
        $candidates = $codes ?: $qrs;
        if ($codes && $qrs) {
            $candidates = array_intersect_key($codes, $qrs);
            if (! $candidates) {
                throw ValidationException::withMessages(['import' => 'Product code and QR code match different products. Correct the row before importing.']);
            }
        }
        if (count($candidates) > 1) {
            // The source catalogue has Regular/Gift records sharing product codes.
            // A matching QR code distinguishes entries with a shared product code.
            $owners = $this->qrCodes[self::key($data['qr_code'] ?? null)] ?? [];
            $candidates = array_intersect_key($candidates, $owners);
            if (count($candidates) !== 1) {
                throw ValidationException::withMessages(['import' => 'Several products match this product code. Supply the existing QR code to identify the correct record.']);
            }
        }
        $product = $candidates ? reset($candidates) : null;
        $qrOwners = $this->qrCodes[self::key($data['qr_code'] ?? null)] ?? [];
        foreach ($qrOwners as $owner) {
            if ($owner->id !== $product?->id) {
                throw ValidationException::withMessages(['import' => 'This QR code belongs to a different product. Match its product code or QR code, or supply a unique QR code.']);
            }
        }

        return $product;
    }
}
