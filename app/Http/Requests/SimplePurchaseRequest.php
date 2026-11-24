<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Http\Requests\Concerns\ValidatesPetroleumAmounts;
use App\Models\FinancialAccount;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SimplePurchaseRequest extends OperationalEntryRequest
{
    use ConvertsJalaliDates, ValidatesPetroleumAmounts;

    protected function prepareForValidation(): void
    {
        $this->convertJalaliDate('purchase_date');
        $this->merge(['items' => [[
            'product_id' => $this->input('product_id'),
            'warehouse_id' => $this->input('warehouse_id'),
            'quantity_kg' => $this->input('quantity_kg'),
            'rate_per_ton' => $this->input('rate_per_ton'),
        ]]]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $this->validatePetroleumItemAmounts($validator, 'quantity_kg');
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('payment_amount')) {
                return;
            }

            if ($this->input('submit_action') !== 'confirm') {
                $validator->errors()->add('payment_amount', __('office.errors.payment_requires_confirmation'));
            }

            $account = FinancialAccount::query()->find($this->integer('financial_account_id'));
            if ($account?->currency->value === 'AFN' && ! $this->filled('exchange_rate_to_usd')) {
                $validator->errors()->add('exchange_rate_to_usd', __('office.errors.exchange_rate_required'));
            }
        });
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'business_partner_id' => ['nullable', 'integer', Rule::exists('business_partners', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'partner_name' => ['nullable', 'required_without:business_partner_id', 'string', 'max:150'],
            'partner_phone' => ['nullable', 'string', 'max:30'],
            'purchase_date' => ['required', 'date_format:Y-m-d'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'ticket_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'quantity_kg' => ['required', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'rate_per_ton' => ['required', 'numeric', 'gte:0', 'regex:/^(?:0|[1-9]\d{0,13})(?:\.\d{1,4})?$/'],
            'submit_action' => ['required', Rule::in(['confirm'])],
            'payment_amount' => ['nullable', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,15})(?:\.\d{1,2})?$/'],
            'financial_account_id' => ['nullable', 'required_with:payment_amount', 'integer', Rule::exists('financial_accounts', 'id')->where('is_active', true)],
            'exchange_rate_to_usd' => ['nullable', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,11})(?:\.\d{1,6})?$/'],
            'items' => ['required', 'array', 'size:1'],
            'items.*.product_id' => ['required'],
            'items.*.warehouse_id' => ['required'],
            'items.*.quantity_kg' => ['required'],
            'items.*.rate_per_ton' => ['required'],
        ];
    }
}
