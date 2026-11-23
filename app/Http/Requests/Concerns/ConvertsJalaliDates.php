<?php

namespace App\Http\Requests\Concerns;

use App\Support\JalaliDate;
use Illuminate\Contracts\Validation\Validator;
use Throwable;

trait ConvertsJalaliDates
{
    /** @var array<string, string> */
    private array $jalaliDateInputs = [];

    protected function convertJalaliDate(string $field, bool $withTime = false): void
    {
        if (! $this->filled($field)) {
            return;
        }

        try {
            $adapter = app(JalaliDate::class);
            $converted = $withTime
                ? $adapter->toGregorianDateTime((string) $this->input($field))
                : $adapter->toGregorianDate((string) $this->input($field));
            $this->jalaliDateInputs[$field] = $adapter->format($converted, $withTime);
            $this->merge([$field => $converted]);
        } catch (Throwable) {
            // Keep invalid input so the normal validation rule returns the field error.
        }
    }

    /** @return array<string, string> */
    public function jalaliDateInputs(): array
    {
        return $this->jalaliDateInputs;
    }

    protected function failedValidation(Validator $validator): void
    {
        // Flash the user's Solar Hijri input, rather than the converted Gregorian date.
        $this->merge($this->jalaliDateInputs);
        parent::failedValidation($validator);
    }
}
