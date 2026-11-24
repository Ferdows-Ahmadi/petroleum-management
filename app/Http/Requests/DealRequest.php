<?php

namespace App\Http\Requests;

use App\Enums\WeighingMode;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Services\PetroleumAmountCalculator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DealRequest extends OperationalEntryRequest
{
    use ConvertsJalaliDates;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_type' => $this->input('source_type', 'SUPPLIER'),
            'destination_type' => $this->input('destination_type', 'CUSTOMER'),
        ]);
        if ($this->input('destination_type') === 'STORAGE' && ! $this->filled('final_quantity_kg')
            && ! $this->filled('first_weight_kg') && ! $this->filled('net_weight_kg')) {
            $this->merge(['final_quantity_kg' => $this->input('source_quantity_kg')]);
        }
        $this->convertJalaliDate('deal_date');
        $this->convertJalaliDate('requested_delivery_date');
        if ($this->filled('weighing_at')) {
            $this->convertJalaliDate('weighing_at', true);
        } elseif ($this->filled('first_weight_kg') || $this->filled('net_weight_kg')) {
            $this->merge(['weighing_at' => now()->toDateTimeString()]);
        }
        $this->merge(['weighing_mode' => $this->input('weighing_mode', WeighingMode::DirectNet->value)]);
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
            $mode = $this->input('weighing_mode');
            if ($this->input('source_type') === 'STORAGE' && $this->input('destination_type') === 'STORAGE') {
                $validator->errors()->add('destination_type', 'انتقال مستقیم بین ذخایر در این فورم پشتیبانی نمی‌شود.');
            }
            if ($this->hasFile('ticket_image') && ! $this->filled('first_weight_kg') && ! $this->filled('net_weight_kg')) {
                $validator->errors()->add('ticket_image', __('weighings.errors.weight_required_for_ticket'));
            }
            if ($mode === WeighingMode::TwoStage->value && $this->filled('first_weight_kg') && $this->filled('second_weight_kg')
                && bccomp((string) $this->input('first_weight_kg'), (string) $this->input('second_weight_kg'), 3) <= 0) {
                $validator->errors()->add('first_weight_kg', __('weighings.errors.first_must_exceed_second'));
            }
            $waitingForWeight = $this->input('submit_action') === 'register' && $mode === WeighingMode::TwoStage->value
                && $this->filled('first_weight_kg') && ! $this->filled('second_weight_kg');
            if ($waitingForWeight && $this->boolean('partial_delivery')) {
                $validator->errors()->add('partial_delivery', 'ابتدا وزن اول را ثبت کنید. تحویل قسمی را هنگام ثبت مقدار نهایی انتخاب کنید.');
            }
            if (! $waitingForWeight) {
                $finalQuantity = $this->input('final_quantity_kg');
                if ($mode === WeighingMode::DirectNet->value && $this->filled('net_weight_kg')) {
                    $finalQuantity = $this->input('net_weight_kg');
                }
                if ($mode === WeighingMode::TwoStage->value && $this->filled('first_weight_kg') && $this->filled('second_weight_kg')) {
                    $finalQuantity = bcsub((string) $this->input('first_weight_kg'), (string) $this->input('second_weight_kg'), 3);
                }
                $comparison = is_scalar($finalQuantity) ? bccomp((string) $this->input('source_quantity_kg'), (string) $finalQuantity, 3) : null;
                if ($this->boolean('partial_delivery')) {
                    if ($comparison !== 1) {
                        $validator->errors()->add('final_quantity_kg', 'برای تحویل قسمی، مقدار تحویل باید مثبت و کمتر از مقدار دریافت باشد.');
                    }
                } elseif ($comparison !== 0) {
                    $validator->errors()->add('final_quantity_kg', __('deals.errors.quantities_must_balance'));
                }
            }
            foreach ([['source_type', 'source_quantity_kg', 'source_rate_per_ton'], ['destination_type', 'final_quantity_kg', 'destination_rate_per_ton']] as [$type, $quantity, $rate]) {
                if ($this->input($type) !== 'STORAGE' && $this->filled($quantity) && $this->filled($rate)) {
                    try {
                        app(PetroleumAmountCalculator::class)->calculate((string) $this->input($quantity), (string) $this->input($rate));
                    } catch (\InvalidArgumentException) {
                        $validator->errors()->add($rate, __('trades.errors.amount_exceeds_precision'));
                    }
                }
            }
        });
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'deal_date' => ['required', 'date_format:Y-m-d'],
            'requested_delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'requested_quantity_kg' => ['nullable', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'source_type' => ['required', Rule::in(['SUPPLIER', 'STORAGE'])],
            'source_warehouse_id' => ['exclude_unless:source_type,STORAGE', 'required', 'integer', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'supplier_id' => ['exclude_if:source_type,STORAGE', 'nullable', 'integer', Rule::exists('business_partners', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'supplier_name' => ['exclude_if:source_type,STORAGE', 'required_without:supplier_id', 'string', 'max:150'],
            'source_quantity_kg' => ['required', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'source_rate_per_ton' => ['exclude_if:source_type,STORAGE', 'required', 'numeric', 'gte:0', 'regex:/^(?:0|[1-9]\d{0,13})(?:\.\d{1,4})?$/'],
            'source_reference_number' => ['nullable', 'string', 'max:100'],
            'destination_type' => ['required', Rule::in(['CUSTOMER', 'STORAGE'])],
            'destination_warehouse_id' => ['exclude_unless:destination_type,STORAGE', 'required', 'integer', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'customer_id' => ['exclude_if:destination_type,STORAGE', 'nullable', 'integer', Rule::exists('business_partners', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'customer_name' => ['exclude_if:destination_type,STORAGE', 'required_without:customer_id', 'string', 'max:150'],
            'final_quantity_kg' => ['nullable', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'destination_rate_per_ton' => ['exclude_if:destination_type,STORAGE', 'required', 'numeric', 'gte:0', 'regex:/^(?:0|[1-9]\d{0,13})(?:\.\d{1,4})?$/'],
            'destination_reference_number' => ['nullable', 'string', 'max:100'],
            'weighing_mode' => ['nullable', Rule::enum(WeighingMode::class)],
            'weighing_at' => ['nullable', 'required_with:first_weight_kg,net_weight_kg,ticket_image', 'date'],
            'first_weight_kg' => ['nullable', 'required_with:second_weight_kg', 'prohibited_if:weighing_mode,'.WeighingMode::DirectNet->value, 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'second_weight_kg' => ['nullable', 'prohibited_if:weighing_mode,'.WeighingMode::DirectNet->value, 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'net_weight_kg' => ['nullable', 'prohibited_if:weighing_mode,'.WeighingMode::TwoStage->value, 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'ticket_number' => ['nullable', 'string', 'max:100'],
            'station_name' => ['nullable', 'string', 'max:150'],
            'vehicle_number' => ['nullable', 'string', 'max:100'],
            'driver_name' => ['nullable', 'string', 'max:150'],
            'ticket_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'partial_delivery' => ['sometimes', 'accepted', 'prohibited_unless:submit_action,register'],
            'submit_action' => ['required', Rule::in(['register', 'complete'])],
        ];
    }
}
