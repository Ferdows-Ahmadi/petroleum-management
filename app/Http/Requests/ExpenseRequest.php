<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\ExpenseCategory;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    use ConvertsJalaliDates;

    protected function prepareForValidation(): void
    {
        $this->convertJalaliDate('transaction_date');
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'financial_account_id' => ['required', 'integer', Rule::exists('financial_accounts', 'id')->where('is_active', true)],
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,15})(?:\.\d{1,2})?$/'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'exchange_rate_to_usd' => ['nullable', 'required_if:currency,AFN', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,11})(?:\.\d{1,6})?$/'],
            'expense_category' => ['required', Rule::enum(ExpenseCategory::class)],
            'payee' => ['nullable', 'string', 'max:150'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:3000'],
            'business_partner_id' => ['prohibited'],
            'transaction_type' => ['prohibited'],
        ];
    }
}
