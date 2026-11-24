<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConvertsJalaliDates;
use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
{
    use ConvertsJalaliDates;

    protected function prepareForValidation(): void
    {
        $this->convertJalaliDate('day');
        $this->convertJalaliDate('date_from');
        $this->convertJalaliDate('date_to');
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'day' => ['nullable', 'date_format:Y-m-d'],
            'book' => ['nullable', 'string', 'regex:/^(account|saraf):[1-9][0-9]{0,18}$/D'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'partner_id' => ['nullable', 'integer', 'exists:business_partners,id'],
            'capital_partner_id' => ['nullable', 'integer', 'exists:capital_partners,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'financial_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'status' => ['nullable', 'string', 'max:30'],
            'category' => ['nullable', 'string', 'max:30'],
        ];
    }
}
