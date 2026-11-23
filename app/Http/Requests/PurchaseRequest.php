<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\PartnerRole;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Http\Requests\Concerns\ValidatesPetroleumAmounts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PurchaseRequest extends FormRequest
{
    use ConvertsJalaliDates, ValidatesPetroleumAmounts;

    protected function prepareForValidation(): void
    {
        $this->convertJalaliDate('purchase_date');
    }

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $this->validatePetroleumItemAmounts($validator, 'quantity_kg');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'business_partner_id' => [
                'required',
                'integer',
                Rule::exists('business_partners', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
                Rule::exists('business_partner_roles', 'business_partner_id')->where('role', PartnerRole::Supplier->value),
            ],
            'purchase_date' => ['required', 'date_format:Y-m-d'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', Rule::in([Currency::Usd->value])],
            'notes' => ['nullable', 'string', 'max:3000'],
            'ticket_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'items.*.warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'items.*.quantity_kg' => ['required', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'items.*.rate_per_ton' => ['required', 'numeric', 'gte:0', 'regex:/^(?:0|[1-9]\d{0,13})(?:\.\d{1,4})?$/'],
        ];
    }
}
