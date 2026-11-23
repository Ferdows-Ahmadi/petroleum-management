<?php

namespace App\Http\Requests;

use App\Enums\PartnerOpeningBalanceType;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartnerOpeningBalanceRequest extends FormRequest
{
    use ConvertsJalaliDates;

    protected function prepareForValidation(): void
    {
        $this->convertJalaliDate('effective_date');
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'balance_type' => ['required', Rule::enum(PartnerOpeningBalanceType::class)],
            'amount_usd' => ['required', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,15})(?:\.\d{1,2})?$/'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
