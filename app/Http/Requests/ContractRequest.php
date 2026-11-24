<?php

namespace App\Http\Requests;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\Currency;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use Illuminate\Validation\Rule;

class ContractRequest extends OperationalEntryRequest
{
    use ConvertsJalaliDates;

    protected function prepareForValidation(): void
    {
        $this->convertJalaliDate('start_date');
        $this->convertJalaliDate('end_date');
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'business_partner_id' => ['required', 'integer', Rule::exists('business_partners', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'contract_number' => ['required', 'string', 'max:100', Rule::unique('contracts', 'contract_number')->ignore($this->route('contract'))],
            'contract_type' => ['required', Rule::enum(ContractType::class)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'agreed_amount' => ['nullable', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,15})(?:\.\d{1,2})?$/'],
            'currency' => ['nullable', 'required_with:agreed_amount', Rule::enum(Currency::class)],
            'terms' => ['required', 'string', 'max:10000'],
            'status' => ['required', Rule::enum(ContractStatus::class)->only(ContractStatus::registrationChoices($this->route('contract')?->status))],
        ];
    }
}
