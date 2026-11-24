<?php

namespace App\Http\Requests;

use App\Enums\WeighingMode;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PendingWeighingRequest extends OperationalEntryRequest
{
    use ConvertsJalaliDates;

    protected function prepareForValidation(): void
    {
        if ($this->filled('weighing_at')) {
            $this->convertJalaliDate('weighing_at', true);
        } else {
            $this->merge(['weighing_at' => now()->toDateTimeString()]);
        }
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
            'weighing_at' => ['required', 'date'],
            'weighing_mode' => ['required', Rule::enum(WeighingMode::class)],
            'first_weight_kg' => ['nullable', 'required_if:weighing_mode,'.WeighingMode::TwoStage->value, 'prohibited_if:weighing_mode,'.WeighingMode::DirectNet->value, 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'second_weight_kg' => ['nullable', 'prohibited_if:weighing_mode,'.WeighingMode::DirectNet->value, 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'net_weight_kg' => ['nullable', 'required_if:weighing_mode,'.WeighingMode::DirectNet->value, 'prohibited_if:weighing_mode,'.WeighingMode::TwoStage->value, 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'ticket_number' => ['nullable', 'string', 'max:100'],
            'station_name' => ['nullable', 'string', 'max:150'],
            'vehicle_number' => ['nullable', 'string', 'max:100'],
            'driver_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'ticket_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
