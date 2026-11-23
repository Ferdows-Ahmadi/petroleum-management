<?php

namespace Tests\Unit;

use App\Enums\Currency;
use App\Services\CurrencyConversionService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CurrencyConversionServiceTest extends TestCase
{
    #[Test]
    public function usd_amount_is_its_own_usd_equivalent(): void
    {
        $this->assertSame('25355.00', app(CurrencyConversionService::class)->toUsd('25355', Currency::Usd, null));
    }

    #[Test]
    public function afn_conversion_is_decimal_safe(): void
    {
        $this->assertSame('100.00', app(CurrencyConversionService::class)->toUsd('7000', Currency::Afn, '70'));
    }

    #[Test]
    public function afn_requires_positive_exchange_rate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(CurrencyConversionService::class)->toUsd('7000', Currency::Afn, null);
    }

    #[Test]
    public function conversion_uses_half_up_two_decimal_rounding(): void
    {
        $this->assertSame('100.01', app(CurrencyConversionService::class)->toUsd('700.07', Currency::Afn, '7'));
    }
}
