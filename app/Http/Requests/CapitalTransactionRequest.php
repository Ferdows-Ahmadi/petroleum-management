<?php

namespace App\Http\Requests;

use App\Enums\CapitalAdjustmentDirection;
use App\Enums\CapitalTransactionType;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CapitalTransactionRequest extends FormRequest
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
        $cashTypes = [CapitalTransactionType::InitialCapital->value, CapitalTransactionType::Contribution->value, CapitalTransactionType::Withdrawal->value];
        $isCash = in_array($this->input('transaction_type'), $cashTypes, true);
        $isAdjustment = $this->input('transaction_type') === CapitalTransactionType::Adjustment->value;

        return [
            'capital_partner_id' => ['required', 'integer', Rule::exists('capital_partners', 'id')->where('is_active', true)],
            'financial_account_id' => [Rule::requiredIf($isCash), Rule::prohibitedIf(! $isCash), 'nullable', 'integer', Rule::exists('financial_accounts', 'id')->where(fn ($query) => $query->where('is_active', true)->where('currency', 'USD'))],
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'transaction_type' => ['required', Rule::enum(CapitalTransactionType::class)],
            'adjustment_direction' => [Rule::requiredIf($isAdjustment), Rule::prohibitedIf(! $isAdjustment), 'nullable', Rule::enum(CapitalAdjustmentDirection::class)],
            'amount_usd' => ['required', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,15})(?:\.\d{1,2})?$/'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
