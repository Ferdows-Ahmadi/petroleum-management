<?php

namespace App\Http\Requests;

use App\Enums\WeighingMode;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WeighingRecordRequest extends OperationalEntryRequest
{
    use ConvertsJalaliDates;

    protected function prepareForValidation(): void
    {
        $this->convertJalaliDate('weighing_at', true);
        $this->merge(['weighing_mode' => $this->input('weighing_mode', WeighingMode::TwoStage->value)]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->input('weighing_mode') === WeighingMode::TwoStage->value && $this->filled('second_weight_kg') && bccomp((string) $this->input('first_weight_kg'), (string) $this->input('second_weight_kg'), 3) <= 0) {
                $validator->errors()->add('first_weight_kg', __('weighings.errors.first_must_exceed_second'));
            }
        });
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sale_item_id' => [$this->route('weighingRecord') ? 'prohibited' : 'required', 'integer', Rule::exists('sale_items', 'id')],
            'ticket_number' => ['nullable', 'string', 'max:100'],
            'station_name' => ['nullable', 'string', 'max:150'],
            'weighing_at' => ['required', 'date'],
            'weighing_mode' => ['required', Rule::enum(WeighingMode::class)],
            'first_weight_kg' => ['nullable', 'required_if:weighing_mode,'.WeighingMode::TwoStage->value, 'prohibited_if:weighing_mode,'.WeighingMode::DirectNet->value, 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'second_weight_kg' => ['nullable', 'prohibited_if:weighing_mode,'.WeighingMode::DirectNet->value, 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'net_weight_kg' => ['nullable', 'required_if:weighing_mode,'.WeighingMode::DirectNet->value, 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'vehicle_number' => ['nullable', 'string', 'max:100'],
            'driver_name' => ['nullable', 'string', 'max:150'],
            'ticket_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'first_weight_kg.gt' => __('weighings.errors.first_must_exceed_second'),
            'ticket_image.mimes' => __('weighings.errors.ticket_type'),
            'ticket_image.max' => __('weighings.errors.ticket_size'),
        ];
    }
}
