<?php

namespace App\Http\Requests;

use App\Enums\WeighingMode;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Http\Requests\Concerns\ValidatesPetroleumAmounts;
use App\Models\FinancialAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SimpleSaleRequest extends FormRequest
{
    use ConvertsJalaliDates, ValidatesPetroleumAmounts;

    protected function prepareForValidation(): void
    {
        $this->convertJalaliDate('sale_date');
        if ($this->filled('weighing_at')) {
            $this->convertJalaliDate('weighing_at', true);
        } elseif ($this->filled('first_weight_kg') || $this->filled('net_weight_kg')) {
            $this->merge(['weighing_at' => now()->toDateTimeString()]);
        }
        $this->merge([
            'weighing_mode' => $this->input('weighing_mode', WeighingMode::TwoStage->value),
            'items' => [[
                'product_id' => $this->input('product_id'),
                'warehouse_id' => $this->input('warehouse_id'),
                'requested_quantity_kg' => $this->input('requested_quantity_kg'),
                'rate_per_ton' => $this->input('rate_per_ton'),
            ]],
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $this->validatePetroleumItemAmounts($validator, 'requested_quantity_kg');
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $mode = $this->input('weighing_mode');
            if ($mode === WeighingMode::TwoStage->value && $this->filled('second_weight_kg') && ! $this->filled('first_weight_kg')) {
                $validator->errors()->add('first_weight_kg', __('weighings.errors.first_must_exceed_second'));
            }
            if ($mode === WeighingMode::TwoStage->value && $this->filled('first_weight_kg') && $this->filled('second_weight_kg') && bccomp((string) $this->input('first_weight_kg'), (string) $this->input('second_weight_kg'), 3) <= 0) {
                $validator->errors()->add('first_weight_kg', __('weighings.errors.first_must_exceed_second'));
            }
            if ($this->input('submit_action') === 'confirm') {
                if ($mode === WeighingMode::DirectNet->value && ! $this->filled('net_weight_kg')) {
                    $validator->errors()->add('net_weight_kg', __('weighings.errors.net_required'));
                }
                if ($mode === WeighingMode::TwoStage->value && ! $this->filled('second_weight_kg')) {
                    $validator->errors()->add('second_weight_kg', __('office.errors.second_weight_required'));
                }
            }
            if ($this->hasFile('ticket_image') && ! $this->filled('first_weight_kg') && ! $this->filled('net_weight_kg')) {
                $validator->errors()->add('ticket_image', __('weighings.errors.weight_required_for_ticket'));
            }
            if ($this->filled('payment_amount') && $this->input('submit_action') !== 'confirm') {
                $validator->errors()->add('payment_amount', __('office.errors.payment_requires_confirmation'));
            }
            $account = FinancialAccount::query()->find($this->integer('financial_account_id'));
            if ($this->filled('payment_amount') && $account?->currency->value === 'AFN' && ! $this->filled('exchange_rate_to_usd')) {
                $validator->errors()->add('exchange_rate_to_usd', __('office.errors.exchange_rate_required'));
            }
        });
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'business_partner_id' => ['nullable', 'integer', Rule::exists('business_partners', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'partner_name' => ['nullable', 'required_without:business_partner_id', 'string', 'max:255'],
            'partner_phone' => ['nullable', 'string', 'max:50'],
            'sale_date' => ['required', 'date_format:Y-m-d'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'requested_quantity_kg' => ['nullable', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'rate_per_ton' => ['required', 'numeric', 'gte:0', 'regex:/^(?:0|[1-9]\d{0,13})(?:\.\d{1,4})?$/'],
            'weighing_mode' => ['required', Rule::enum(WeighingMode::class)],
            'weighing_at' => ['nullable', 'required_with:first_weight_kg,net_weight_kg,ticket_image', 'date'],
            'first_weight_kg' => ['nullable', 'prohibited_if:weighing_mode,'.WeighingMode::DirectNet->value, 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'second_weight_kg' => ['nullable', 'prohibited_if:weighing_mode,'.WeighingMode::DirectNet->value, 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'net_weight_kg' => ['nullable', 'prohibited_if:weighing_mode,'.WeighingMode::TwoStage->value, 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'ticket_number' => ['nullable', 'string', 'max:100'],
            'station_name' => ['nullable', 'string', 'max:150'],
            'vehicle_number' => ['nullable', 'string', 'max:100'],
            'driver_name' => ['nullable', 'string', 'max:150'],
            'ticket_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'submit_action' => ['required', Rule::in(['draft', 'confirm'])],
            'payment_amount' => ['nullable', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,15})(?:\.\d{1,2})?$/'],
            'financial_account_id' => ['nullable', 'required_with:payment_amount', 'integer', Rule::exists('financial_accounts', 'id')->where('is_active', true)],
            'exchange_rate_to_usd' => ['nullable', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,11})(?:\.\d{1,6})?$/'],
            'items' => ['required', 'array', 'size:1'],
            'items.*.product_id' => ['required'],
            'items.*.warehouse_id' => ['required'],
            'items.*.requested_quantity_kg' => ['nullable'],
            'items.*.rate_per_ton' => ['required'],
        ];
    }
}
