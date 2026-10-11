<?php

namespace Tests\Unit;

use App\Support\NumberDisplay;
use PHPUnit\Framework\TestCase;

class NumberDisplayTest extends TestCase
{
    public function test_display_rounds_without_changing_the_source_or_identifiers(): void
    {
        $value = '39.444444444444';
        $this->assertSame('39.44', NumberDisplay::field($value, 'Calculated margin %'));
        $this->assertSame('39.444444444444', $value);
        $this->assertSame('136.20', NumberDisplay::field('136.20000000', 'published_price'));
        $this->assertSame('0.00', NumberDisplay::decimal('-0.00001'));
        $this->assertSame('-20.56', NumberDisplay::decimal('-20.555'));
        $this->assertSame('8.257', NumberDisplay::field('8.25678', 'net_carton_kg'));
        $this->assertSame('60', NumberDisplay::quantity('60.000000'));
        foreach (['qr_code', 'Internal code', 'Supplier SKU', 'barcode', 'phone'] as $field) {
            $this->assertSame('0010020', NumberDisplay::field('0010020', $field));
        }
        $this->assertSame('35+3', NumberDisplay::field('35+3', 'discount'));
        $this->assertSame('—', NumberDisplay::decimal(null));
    }
}
