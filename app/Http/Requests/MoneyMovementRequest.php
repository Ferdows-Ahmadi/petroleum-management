<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\MoneyMovementType;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use Illuminate\Validation\Rule;

class MoneyMovementRequest extends OperationalEntryRequest
{
    use ConvertsJalaliDates;

    protected function prepareForValidation(): void
    {
        $this->convertJalaliDate('movement_date');
        foreach (['counterparty_id', 'saraf_id', 'deal_id', 'financial_account_id'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'مبلغ همین دریافت یا پرداخت را وارد کنید.',
            'amount.regex' => 'مبلغ را با حداکثر دو رقم اعشار وارد کنید.',
            'exchange_rate_to_usd.required_if' => 'برای ثبت مبلغ افغانی، نرخ یک دالر به افغانی را وارد کنید.',
        ];
    }

    public function rules(): array
    {
        return [
            'movement_type' => ['required', Rule::enum(MoneyMovementType::class)],
            'counterparty_id' => ['nullable', 'integer', Rule::exists('business_partners', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'saraf_id' => ['nullable', 'integer', 'different:counterparty_id', Rule::exists('business_partners', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'deal_id' => ['nullable', 'integer', 'exists:deals,id'],
            'financial_account_id' => ['nullable', 'integer', Rule::exists('financial_accounts', 'id')->where('is_active', true)],
            'movement_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,15})(?:\.\d{1,2})?$/'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'exchange_rate_to_usd' => ['nullable', 'required_if:currency,AFN', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,11})(?:\.\d{1,6})?$/'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'confirm_now' => $this->isMethod('POST') ? ['required', 'accepted'] : ['nullable', 'boolean'],
            'return_to' => ['nullable', Rule::in(['accounts', 'deal', 'journal'])],
        ];
    }
}
