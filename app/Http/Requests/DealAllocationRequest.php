<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deal_source_id' => ['required', 'integer', Rule::exists('deal_sources', 'id')],
            'deal_destination_id' => ['required', 'integer', Rule::exists('deal_destinations', 'id')],
            'quantity_kg' => ['required', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
        ];
    }
}
