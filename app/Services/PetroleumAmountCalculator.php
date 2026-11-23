<?php

namespace App\Services;

use InvalidArgumentException;

class PetroleumAmountCalculator
{
    public const AMOUNT_PRECISION = 18;

    public const AMOUNT_SCALE = 2;

    public const MAX_SUPPORTED_AMOUNT = '9999999999999999.99';

    public function calculate(string|int $quantityKg, string|int $ratePerTon): string
    {
        $quantity = (string) $quantityKg;
        $rate = (string) $ratePerTon;

        if (! $this->isNonNegativeDecimal($quantity) || ! $this->isNonNegativeDecimal($rate)) {
            throw new InvalidArgumentException('Quantity and rate must be non-negative decimal values.');
        }

        $unrounded = bcdiv(bcmul($quantity, $rate, 8), '1000', 8);

        $rounded = bcadd($unrounded, '0.005', self::AMOUNT_SCALE);

        if (bccomp($rounded, self::MAX_SUPPORTED_AMOUNT, self::AMOUNT_SCALE) > 0) {
            throw new InvalidArgumentException('Calculated amount exceeds the DECIMAL(18,2) storage limit.');
        }

        return $rounded;
    }

    private function isNonNegativeDecimal(string $value): bool
    {
        return preg_match('/^(?:0|[1-9]\d*)(?:\.\d+)?$/', $value) === 1;
    }
}
