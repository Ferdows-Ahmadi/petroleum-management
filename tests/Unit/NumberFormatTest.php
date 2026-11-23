<?php

namespace Tests\Unit;

use App\Support\NumberFormat;
use PHPUnit\Framework\TestCase;

class NumberFormatTest extends TestCase
{
    public function test_financial_display_retains_every_cent_at_decimal_storage_limit(): void
    {
        $format = new NumberFormat;
        $this->assertSame('9,999,999,999,999,999.99', $format->money('9999999999999999.99'));
        $this->assertSame('-9,999,999,999,999,999.99', $format->money('-9999999999999999.99'));
        $this->assertSame('9,999,999,999,999,999.99', $format->absoluteMoney('-9999999999999999.99'));
        $this->assertSame('1.01', $format->money('1.005'));
        $this->assertSame('19,355', $format->weight('19355.000'));
        $this->assertSame('0.00', $format->money('-0.000'));
    }
}
