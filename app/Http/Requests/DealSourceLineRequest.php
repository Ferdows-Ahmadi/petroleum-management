<?php

namespace App\Http\Requests;

use App\Enums\DealSourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealSourceLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_type' => ['required', Rule::enum(DealSourceType::class)],
            'supplier_id' => ['nullable', 'required_if:source_type,SUPPLIER', 'prohibited_if:source_type,STORAGE', Rule::exists('business_partners', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'warehouse_id' => ['nullable', 'required_if:source_type,STORAGE', 'prohibited_if:source_type,SUPPLIER', Rule::exists('warehouses', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'quantity_kg' => ['required', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'rate_per_ton' => ['nullable', 'required_if:source_type,SUPPLIER', 'prohibited_if:source_type,STORAGE', 'numeric', 'gte:0', 'regex:/^(?:0|[1-9]\d{0,13})(?:\.\d{1,4})?$/'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
