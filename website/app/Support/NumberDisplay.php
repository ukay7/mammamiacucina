<?php

namespace App\Support;

/** Presentation only. Never use these strings for pricing, persistence or matching identifiers. */
class NumberDisplay
{
    public static function decimal(mixed $value, int $places = 2, bool $trim = false, string $empty = '—'): string
    {
        if ($value === null || $value === '') {
            return $empty;
        }
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            return (string) $value;
        }
        $number = round((float) $value, $places);
        $formatted = number_format($number == 0 ? 0 : $number, $places, '.', '');

        return $trim ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }

    public static function quantity(mixed $value, string $empty = '—'): string
    {
        return self::decimal($value, 3, true, $empty);
    }

    public static function mode(string $field): ?string
    {
        $field = strtolower($field);
        if (preg_match('/weight|grams|quantity|pieces|cartons|net_carton_kg|minimum_cartons|minimum_pieces|units_per_pack|threshold|invoice_quantity/', $field)) {
            return 'quantity';
        }
        if (preg_match('/price|cost|margin|discount|profit_percent|business_profit_percent|profit setting|percent|%|eur|cad|freight|shipping|surcharge|exchange|^fx$|^published_price$|^business_price$|^unit_cents$|^carton_cents$/', $field)) {
            return 'decimal';
        }

        return null;
    }

    public static function field(mixed $value, string $field): string
    {
        return match (self::mode($field)) {
            'quantity' => self::quantity($value),
            'decimal' => self::decimal($value),
            default => $value === null || $value === '' ? '—' : (string) $value,
        };
    }
}
