<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\FinancialAccountType;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialAccountRequest extends FormRequest
{
    use ConvertsJalaliDates;

    protected function prepareForValidation(): void
    {
        $this->convertJalaliDate('opening_balance_date');
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('financial_accounts', 'name')->ignore($this->route('financialAccount'))],
            'account_type' => ['required', Rule::enum(FinancialAccountType::class)],
            'currency' => ['required', Rule::enum(Currency::class)],
            'opening_balance' => ['required', 'numeric', 'gte:0', 'regex:/^(?:0|[1-9]\d{0,15})(?:\.\d{1,2})?$/'],
            'opening_balance_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
