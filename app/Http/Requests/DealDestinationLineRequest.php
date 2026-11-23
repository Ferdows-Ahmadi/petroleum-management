<?php

namespace App\Http\Requests;

use App\Enums\DealDestinationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealDestinationLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'destination_type' => ['required', Rule::enum(DealDestinationType::class)],
            'customer_id' => ['nullable', 'required_if:destination_type,CUSTOMER', 'prohibited_if:destination_type,STORAGE', Rule::exists('business_partners', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'warehouse_id' => ['nullable', 'required_if:destination_type,STORAGE', 'prohibited_if:destination_type,CUSTOMER', Rule::exists('warehouses', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'final_quantity_kg' => ['required', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'rate_per_ton' => ['nullable', 'required_if:destination_type,CUSTOMER', 'prohibited_if:destination_type,STORAGE', 'numeric', 'gte:0', 'regex:/^(?:0|[1-9]\d{0,13})(?:\.\d{1,4})?$/'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
