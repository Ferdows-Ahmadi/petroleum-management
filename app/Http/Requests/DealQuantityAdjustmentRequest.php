<?php

namespace App\Http\Requests;

use App\Enums\DealQuantityAdjustmentType;
use Illuminate\Validation\Rule;

class DealQuantityAdjustmentRequest extends OperationalEntryRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deal_source_id' => ['required', 'integer', Rule::exists('deal_sources', 'id')],
            'adjustment_type' => ['required', Rule::enum(DealQuantityAdjustmentType::class)],
            'quantity_kg' => ['required', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'reason' => ['required', 'string', 'max:3000'],
        ];
    }
}
