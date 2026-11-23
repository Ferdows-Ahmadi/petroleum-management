<?php

namespace App\Http\Requests\Concerns;

use App\Services\PetroleumAmountCalculator;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

trait ValidatesPetroleumAmounts
{
    protected function validatePetroleumItemAmounts(Validator $validator, string $quantityField): void
    {
        $validator->after(function (Validator $validator) use ($quantityField): void {
            foreach ((array) $this->input('items', []) as $index => $item) {
                $quantity = is_array($item) ? ($item[$quantityField] ?? null) : null;
                $rate = is_array($item) ? ($item['rate_per_ton'] ?? null) : null;

                if (! is_scalar($quantity) || ! is_scalar($rate)
                    || $validator->errors()->has("items.{$index}.{$quantityField}")
                    || $validator->errors()->has("items.{$index}.rate_per_ton")) {
                    continue;
                }

                try {
                    app(PetroleumAmountCalculator::class)->calculate((string) $quantity, (string) $rate);
                } catch (InvalidArgumentException) {
                    $validator->errors()->add("items.{$index}.rate_per_ton", __('trades.errors.amount_exceeds_precision'));
                }
            }
        });
    }
}
