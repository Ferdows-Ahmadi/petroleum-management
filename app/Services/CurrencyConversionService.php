<?php

namespace App\Services;

use App\Enums\Currency;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CurrencyConversionService
{
    public function toUsd(string|int $amount, Currency|string $currency, string|int|null $rate): string
    {
        $amount = (string) $amount;
        $currency = $currency instanceof Currency ? $currency : Currency::from($currency);

        if (bccomp($amount, '0', 6) <= 0) {
            throw new InvalidArgumentException('Amount must be a positive decimal value.');
        }

        if ($currency === Currency::Usd) {
            $converted = bcadd($amount, '0.005', 2);
        } else {
            $rate = (string) $rate;
            if ($rate === '' || bccomp($rate, '0', 6) <= 0) {
                throw new InvalidArgumentException('A positive AFN per USD exchange rate is required.');
            }
            $converted = bcadd(bcdiv($amount, $rate, 8), '0.005', 2);
        }
        if (bccomp($converted, PetroleumAmountCalculator::MAX_SUPPORTED_AMOUNT, 2) > 0) {
            throw ValidationException::withMessages(['amount' => __('trades.errors.amount_exceeds_precision')]);
        }

        return $converted;
    }
}
