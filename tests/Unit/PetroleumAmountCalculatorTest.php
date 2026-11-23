<?php

namespace Tests\Unit;

use App\Services\PetroleumAmountCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PetroleumAmountCalculatorTest extends TestCase
{
    public function test_verified_petroleum_amount_is_calculated_exactly(): void
    {
        $calculator = new PetroleumAmountCalculator;

        $this->assertSame('25355.05', $calculator->calculate('19355', '1310'));
    }

    public function test_result_is_rounded_to_two_decimal_places_without_binary_floats(): void
    {
        $calculator = new PetroleumAmountCalculator;

        $this->assertSame('1.01', $calculator->calculate('1', '1005'));
    }

    public function test_negative_values_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PetroleumAmountCalculator)->calculate('-1', '1310');
    }

    public function test_amount_larger_than_decimal_eighteen_two_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PetroleumAmountCalculator)->calculate('999999999999999', '99999999999999');
    }
}
