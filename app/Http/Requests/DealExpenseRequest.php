<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\DealExpenseCategory;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use Illuminate\Validation\Rule;

class DealExpenseRequest extends OperationalEntryRequest
{
    use ConvertsJalaliDates;

    protected function prepareForValidation(): void
    {
        $this->convertJalaliDate('expense_date');
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(DealExpenseCategory::class)],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,15})(?:\.\d{1,2})?$/'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'exchange_rate_to_usd' => ['nullable', 'required_if:currency,AFN', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,11})(?:\.\d{1,6})?$/'],
            'payee_id' => ['nullable', Rule::exists('business_partners', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'financial_transaction_id' => ['nullable', 'integer', Rule::exists('financial_transactions', 'id')],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
