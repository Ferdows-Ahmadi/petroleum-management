<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\DealDestinationType;
use App\Enums\DealSourceType;
use App\Enums\DealStatus;
use App\Enums\PartnerRole;
use App\Enums\WeighingMode;
use App\Enums\WeighingStatus;
use App\Models\Deal;
use App\Models\DealDestination;
use App\Models\DealSource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DealService
{
    public function __construct(
        private readonly PetroleumAmountCalculator $amounts,
        private readonly WeighingService $weighings,
        private readonly DealAllocationService $allocations,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, int $userId): Deal
    {
        return DB::transaction(function () use ($data, $userId): Deal {
            // Selecting a party for a trade establishes that commercial role,
            // just as contextual party selection does in the HTTP workflow.
            foreach (['supplier_id' => PartnerRole::Supplier, 'customer_id' => PartnerRole::Customer] as $field => $role) {
                if (! empty($data[$field])) {
                    app(InlinePartnerService::class)->resolve((int) $data[$field], null, null, $role);
                }
            }
            $deal = Deal::query()->create([
                'deal_date' => $data['deal_date'],
                'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
                'requested_quantity_kg' => $data['requested_quantity_kg'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'status' => DealStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);
            $deal->update(['reference_number' => 'DL-'.str_pad((string) $deal->id, 6, '0', STR_PAD_LEFT)]);

            $deal->sources()->create($this->sourceData($data));
            $destination = $deal->destinations()->create($this->destinationData($data));

            $weighing = null;
            if (! empty($data['first_weight_kg']) || ! empty($data['net_weight_kg'])) {
                $weighing = $this->weighings->create([
                    'deal_destination_id' => $destination->id,
                    'weighing_at' => $data['weighing_at'],
                    'weighing_mode' => $data['weighing_mode'] ?? WeighingMode::DirectNet->value,
                    'first_weight_kg' => $data['first_weight_kg'] ?? null,
                    'second_weight_kg' => $data['second_weight_kg'] ?? null,
                    'net_weight_kg' => $data['net_weight_kg'] ?? null,
                    'ticket_number' => $data['ticket_number'] ?? null,
                    'station_name' => $data['station_name'] ?? null,
                    'vehicle_number' => $data['vehicle_number'] ?? null,
                    'driver_name' => $data['driver_name'] ?? null,
                    'ticket_image_path' => $data['ticket_image_path'] ?? null,
                ], $userId);
            }

            if ($data['submit_action'] === 'complete') {
                if ($weighing) {
                    $this->weighings->finalize($weighing, $userId);
                }
                $this->completeLocked($deal, $userId);
            }

            return $deal->refresh()->load(['product', 'sources.supplier', 'destinations.customer']);
        });
    }

    public function complete(Deal $deal, int $userId): Deal
    {
        return DB::transaction(function () use ($deal, $userId): Deal {
            $locked = Deal::query()->lockForUpdate()->findOrFail($deal->id);
            $this->completeLocked($locked, $userId);

            return $locked->refresh();
        });
    }

    /** Correct a single-source, single-destination draft in place; completed history remains protected. */
    public function updateDraft(Deal $deal, array $data, int $userId): Deal
    {
        return DB::transaction(function () use ($deal, $data, $userId): Deal {
            $locked = Deal::query()->lockForUpdate()->findOrFail($deal->id);
            $this->ensureCommercialLinesMutable($locked);
            $locked->load(['sources', 'destinations.weighingRecord']);
            if ($locked->sources->count() !== 1 || $locked->destinations->count() !== 1) {
                throw ValidationException::withMessages(['deal' => __('deals.errors.lines_locked')]);
            }
            $destination = $locked->destinations->first();
            $record = $destination->weighingRecord;
            if ($record && $record->status === WeighingStatus::Finalized) {
                throw ValidationException::withMessages(['weighing' => __('weighings.errors.finalized_immutable')]);
            }
            $locked->update(Arr::only($data, ['deal_date', 'requested_delivery_date', 'requested_quantity_kg', 'product_id', 'notes']));
            $locked->sources->first()->update($this->sourceData($data));
            $destination->update($this->destinationData($data));
            $hasWeight = ! empty($data['first_weight_kg']) || ! empty($data['net_weight_kg']);
            if ($hasWeight) {
                $weightData = Arr::only($data, ['weighing_at', 'weighing_mode', 'first_weight_kg', 'second_weight_kg', 'net_weight_kg', 'ticket_number', 'station_name', 'vehicle_number', 'driver_name', 'ticket_image_path']);
                $record = $record ? $this->weighings->update($record, $weightData)
                    : $this->weighings->create($weightData + ['deal_destination_id' => $destination->id], $userId);
            } elseif ($record) {
                // Weight evidence is never silently removed by a commercial edit.
                $destination->update(['final_quantity_kg' => null, 'amount' => null]);
            }
            $locked->allocations()->delete();
            if ($data['submit_action'] === 'complete') {
                if ($hasWeight && $record) {
                    $this->weighings->finalize($record, $userId);
                }
                $this->completeLocked($locked, $userId);
            }

            return $locked->refresh();
        });
    }

    /** @param array<string,mixed> $data */
    public function addSource(Deal $deal, array $data): DealSource
    {
        return DB::transaction(function () use ($deal, $data): DealSource {
            $locked = Deal::query()->lockForUpdate()->findOrFail($deal->id);
            $this->ensureCommercialLinesMutable($locked);
            $type = DealSourceType::from($data['source_type']);
            $quantity = (string) $data['quantity_kg'];
            $rate = $type === DealSourceType::Supplier ? (string) $data['rate_per_ton'] : null;
            $attributes = [
                'source_type' => $type,
                'supplier_id' => $type === DealSourceType::Supplier ? $data['supplier_id'] : null,
                'warehouse_id' => $type === DealSourceType::Storage ? $data['warehouse_id'] : null,
                'quantity_kg' => $quantity,
                'rate_per_ton' => $rate,
                'amount' => $rate !== null ? $this->calculateAmount($quantity, $rate) : null,
                'currency' => Currency::Usd,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];
            $placeholder = $locked->sources()->whereNull('supplier_id')->whereNull('warehouse_id')->whereNull('quantity_kg')->first();
            if ($placeholder) {
                $placeholder->update($attributes);

                return $placeholder->refresh();
            }

            return $locked->sources()->create($attributes);
        });
    }

    /** @param array<string,mixed> $data */
    public function addDestination(Deal $deal, array $data): DealDestination
    {
        return DB::transaction(function () use ($deal, $data): DealDestination {
            $locked = Deal::query()->lockForUpdate()->findOrFail($deal->id);
            $this->ensureCommercialLinesMutable($locked);
            $type = DealDestinationType::from($data['destination_type']);
            $quantity = (string) $data['final_quantity_kg'];
            $rate = $type === DealDestinationType::Customer ? (string) $data['rate_per_ton'] : null;
            $attributes = [
                'destination_type' => $type,
                'customer_id' => $type === DealDestinationType::Customer ? $data['customer_id'] : null,
                'warehouse_id' => $type === DealDestinationType::Storage ? $data['warehouse_id'] : null,
                'final_quantity_kg' => $quantity,
                'rate_per_ton' => $rate,
                'amount' => $rate !== null ? $this->calculateAmount($quantity, $rate) : null,
                'currency' => Currency::Usd,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];
            $placeholder = $locked->destinations()->whereNull('customer_id')->whereNull('warehouse_id')->whereNull('final_quantity_kg')->first();
            if ($placeholder) {
                $placeholder->update($attributes);

                return $placeholder->refresh();
            }

            return $locked->destinations()->create($attributes);
        });
    }

    private function completeLocked(Deal $deal, int $userId): void
    {
        $deal->load(['sources', 'destinations.weighingRecord']);
        if ($deal->status !== DealStatus::Draft && $deal->status !== DealStatus::InProgress) {
            throw ValidationException::withMessages(['deal' => __('deals.errors.not_completable')]);
        }
        $source = $deal->sources->first();
        $destination = $deal->destinations->first();
        if (! $deal->product_id || ! $source || ! $destination) {
            throw ValidationException::withMessages(['deal' => __('deals.errors.incomplete')]);
        }
        foreach ($deal->sources as $line) {
            if (! $line->quantity_kg || ($line->source_type === DealSourceType::Supplier && (! $line->supplier_id || $line->rate_per_ton === null)) || ($line->source_type === DealSourceType::Storage && ! $line->warehouse_id)) {
                throw ValidationException::withMessages(['deal' => __('deals.errors.incomplete')]);
            }
            if ($line->source_type === DealSourceType::Supplier && (! $line->supplier?->is_active || ! $line->supplier->roles->contains('role', PartnerRole::Supplier))) {
                throw ValidationException::withMessages(['supplier_id' => __('finance.errors.counterparty_role')]);
            }
        }
        foreach ($deal->destinations as $line) {
            if ($line->weighingRecord && $line->weighingRecord->status !== WeighingStatus::Finalized) {
                throw ValidationException::withMessages(['weighing' => __('settlements.errors.incomplete_weighing')]);
            }
            if ($line->destination_type === DealDestinationType::Customer && (! $line->customer?->is_active || ! $line->customer->roles->contains('role', PartnerRole::Customer))) {
                throw ValidationException::withMessages(['customer_id' => __('finance.errors.counterparty_role')]);
            }
            if (! $line->final_quantity_kg || ($line->destination_type === DealDestinationType::Customer && (! $line->customer_id || $line->rate_per_ton === null)) || ($line->destination_type === DealDestinationType::Storage && ! $line->warehouse_id)) {
                throw ValidationException::withMessages(['deal' => __('deals.errors.incomplete')]);
            }
        }
        $simpleDirect = $deal->sources->count() === 1 && $deal->destinations->count() === 1
            && $source->source_type === DealSourceType::Supplier && $destination->destination_type === DealDestinationType::Customer;
        if ($simpleDirect && bccomp($source->quantity_kg, $destination->final_quantity_kg, 3) !== 0) {
            throw ValidationException::withMessages(['final_quantity_kg' => __('deals.errors.quantities_must_balance')]);
        }
        foreach ($deal->sources->where('source_type', DealSourceType::Supplier) as $line) {
            $line->update(['amount' => $this->calculateAmount($line->quantity_kg, $line->rate_per_ton)]);
        }
        foreach ($deal->destinations->where('destination_type', DealDestinationType::Customer) as $line) {
            $line->update(['amount' => $this->calculateAmount($line->final_quantity_kg, $line->rate_per_ton)]);
        }
        $deal->update(['status' => DealStatus::Completed, 'completed_by' => $userId, 'completed_at' => now()]);
        $this->allocations->ensureAutomatic($deal->refresh(), $userId);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sourceData(array $data): array
    {
        $quantity = $data['source_quantity_kg'] ?? null;
        $type = DealSourceType::from($data['source_type'] ?? 'SUPPLIER');
        $rate = $type === DealSourceType::Supplier ? ($data['source_rate_per_ton'] ?? null) : null;

        return [
            'source_type' => $type,
            'supplier_id' => $type === DealSourceType::Supplier ? ($data['supplier_id'] ?? null) : null,
            'warehouse_id' => $type === DealSourceType::Storage ? ($data['source_warehouse_id'] ?? null) : null,
            'quantity_kg' => $quantity,
            'rate_per_ton' => $rate,
            'amount' => $quantity !== null && $rate !== null ? $this->calculateAmount((string) $quantity, (string) $rate) : null,
            'currency' => Currency::Usd,
            'reference_number' => $data['source_reference_number'] ?? null,
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function destinationData(array $data): array
    {
        $quantity = $this->destinationQuantity($data);
        $type = DealDestinationType::from($data['destination_type'] ?? 'CUSTOMER');
        $rate = $type === DealDestinationType::Customer ? ($data['destination_rate_per_ton'] ?? null) : null;

        return [
            'destination_type' => $type,
            'customer_id' => $type === DealDestinationType::Customer ? ($data['customer_id'] ?? null) : null,
            'warehouse_id' => $type === DealDestinationType::Storage ? ($data['destination_warehouse_id'] ?? null) : null,
            'requested_quantity_kg' => $data['requested_quantity_kg'] ?? null,
            'final_quantity_kg' => $quantity,
            'rate_per_ton' => $rate,
            'amount' => $quantity !== null && $rate !== null ? $this->calculateAmount((string) $quantity, (string) $rate) : null,
            'currency' => Currency::Usd,
            'reference_number' => $data['destination_reference_number'] ?? null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function destinationQuantity(array $data): ?string
    {
        if (($data['weighing_mode'] ?? null) === WeighingMode::DirectNet->value && ! empty($data['net_weight_kg'])) {
            return bcadd((string) $data['net_weight_kg'], '0', 3);
        }
        if (($data['weighing_mode'] ?? null) === WeighingMode::TwoStage->value && ! empty($data['first_weight_kg']) && ! empty($data['second_weight_kg'])) {
            return bcsub((string) $data['first_weight_kg'], (string) $data['second_weight_kg'], 3);
        }
        if (($data['weighing_mode'] ?? null) === WeighingMode::TwoStage->value && ! empty($data['first_weight_kg'])) {
            return null;
        }

        return isset($data['final_quantity_kg']) ? (string) $data['final_quantity_kg'] : null;
    }

    private function calculateAmount(string $quantity, string $rate): string
    {
        try {
            return $this->amounts->calculate($quantity, $rate);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['rate_per_ton' => __('trades.errors.amount_exceeds_precision')]);
        }
    }

    private function ensureCommercialLinesMutable(Deal $deal): void
    {
        if (! in_array($deal->status, [DealStatus::Draft, DealStatus::InProgress], true)) {
            throw ValidationException::withMessages(['deal' => __('deals.errors.lines_locked')]);
        }
    }

    public function removeSource(DealSource $source): void
    {
        DB::transaction(function () use ($source): void {
            $deal = Deal::query()->lockForUpdate()->findOrFail($source->deal_id);
            $this->ensureCommercialLinesMutable($deal);
            if ($deal->quantityAdjustments()->where('deal_source_id', $source->id)->exists()) {
                throw ValidationException::withMessages(['deal' => __('deals.errors.lines_locked')]);
            }
            $deal->allocations()->where('deal_source_id', $source->id)->delete();
            $source->delete();
            if (! $deal->sources()->exists()) {
                $deal->sources()->create([]);
            }
        });
    }

    public function removeDestination(DealDestination $destination): void
    {
        DB::transaction(function () use ($destination): void {
            $deal = Deal::query()->lockForUpdate()->findOrFail($destination->deal_id);
            $this->ensureCommercialLinesMutable($deal);
            if ($destination->weighingRecord()->exists()) {
                throw ValidationException::withMessages(['weighing' => __('weighings.errors.sale_edit_locked')]);
            }
            $deal->allocations()->where('deal_destination_id', $destination->id)->delete();
            $destination->delete();
            if (! $deal->destinations()->exists()) {
                $deal->destinations()->create([]);
            }
        });
    }
}
