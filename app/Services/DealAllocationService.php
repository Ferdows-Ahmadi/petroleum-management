<?php

namespace App\Services;

use App\Enums\CommercialRecordStatus;
use App\Enums\DealAllocationMethod;
use App\Enums\DealQuantityAdjustmentType;
use App\Models\Deal;
use App\Models\DealAllocation;
use App\Models\DealDestination;
use App\Models\DealSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DealAllocationService
{
    public function __construct(private readonly PetroleumAmountCalculator $amounts) {}

    public function allocate(Deal $deal, DealSource $source, DealDestination $destination, string $quantityKg, int $userId): DealAllocation
    {
        return DB::transaction(function () use ($deal, $source, $destination, $quantityKg, $userId): DealAllocation {
            $lockedDeal = Deal::query()->lockForUpdate()->findOrFail($deal->id);
            $source = DealSource::query()->lockForUpdate()->findOrFail($source->id);
            $destination = DealDestination::query()->lockForUpdate()->findOrFail($destination->id);
            $this->ensureMutable($lockedDeal);
            $this->ensureSameDeal($lockedDeal, $source, $destination);
            $this->validateQuantity($source, $destination, $quantityKg);

            return DealAllocation::query()->updateOrCreate(
                ['deal_source_id' => $source->id, 'deal_destination_id' => $destination->id],
                [
                    'deal_id' => $lockedDeal->id,
                    'quantity_kg' => bcadd($quantityKg, '0', 3),
                    'allocated_cost_usd' => $this->amounts->calculate($quantityKg, (string) $source->rate_per_ton),
                    'allocation_method' => DealAllocationMethod::Manual,
                    'created_by' => $userId,
                ],
            );
        });
    }

    public function delete(DealAllocation $allocation): void
    {
        DB::transaction(function () use ($allocation): void {
            Deal::query()->lockForUpdate()->findOrFail($allocation->deal_id);
            $locked = DealAllocation::query()->lockForUpdate()->with('deal')->findOrFail($allocation->id);
            $this->ensureMutable($locked->deal);
            $locked->delete();
        });
    }

    public function ensureAutomatic(Deal $deal, int $userId): void
    {
        $deal->loadMissing(['sources', 'destinations', 'allocations']);
        if ($deal->allocations->isNotEmpty()) {
            return;
        }

        $sources = $deal->sources->filter(fn (DealSource $source): bool => $source->quantity_kg !== null && $source->rate_per_ton !== null)->values();
        $destinations = $deal->destinations->filter(fn (DealDestination $destination): bool => $destination->final_quantity_kg !== null)->values();
        if ($sources->count() !== $deal->sources->count() || $sources->isEmpty() || $destinations->isEmpty() || ($sources->count() > 1 && $destinations->count() > 1)) {
            return;
        }

        if ($sources->count() === 1) {
            $source = $sources->first();
            foreach ($destinations as $destination) {
                DealAllocation::query()->create([
                    'deal_id' => $deal->id,
                    'deal_source_id' => $source->id,
                    'deal_destination_id' => $destination->id,
                    'quantity_kg' => $destination->final_quantity_kg,
                    'allocated_cost_usd' => $this->amounts->calculate($destination->final_quantity_kg, (string) $source->rate_per_ton),
                    'allocation_method' => DealAllocationMethod::Automatic,
                    'created_by' => $userId,
                ]);
            }

            return;
        }

        $destination = $destinations->first();
        $sourceTotal = $sources->reduce(fn (string $sum, DealSource $source): string => bcadd($sum, $source->quantity_kg, 3), '0.000');
        if (bccomp($sourceTotal, $destination->final_quantity_kg, 3) !== 0) {
            return;
        }
        foreach ($sources as $source) {
            DealAllocation::query()->create([
                'deal_id' => $deal->id,
                'deal_source_id' => $source->id,
                'deal_destination_id' => $destination->id,
                'quantity_kg' => $source->quantity_kg,
                'allocated_cost_usd' => $this->amounts->calculate($source->quantity_kg, (string) $source->rate_per_ton),
                'allocation_method' => DealAllocationMethod::Automatic,
                'created_by' => $userId,
            ]);
        }
    }

    private function validateQuantity(DealSource $source, DealDestination $destination, string $quantityKg): void
    {
        if (bccomp($quantityKg, '0.000', 3) <= 0 || $source->rate_per_ton === null) {
            throw ValidationException::withMessages(['quantity_kg' => __('settlements.errors.invalid_allocation')]);
        }
        $sourceAllocated = DealAllocation::query()->where('deal_source_id', $source->id)->where('deal_destination_id', '!=', $destination->id)->get()
            ->reduce(fn (string $sum, DealAllocation $row): string => bcadd($sum, $row->quantity_kg, 3), '0.000');
        $destinationAllocated = DealAllocation::query()->where('deal_destination_id', $destination->id)->where('deal_source_id', '!=', $source->id)->get()
            ->reduce(fn (string $sum, DealAllocation $row): string => bcadd($sum, $row->quantity_kg, 3), '0.000');
        $excess = $source->deal->quantityAdjustments()
            ->where('deal_source_id', $source->id)
            ->where('status', CommercialRecordStatus::Confirmed->value)
            ->where('adjustment_type', DealQuantityAdjustmentType::Excess->value)->get()
            ->reduce(fn (string $sum, $row): string => bcadd($sum, $row->quantity_kg, 3), '0.000');
        if (bccomp(bcadd($sourceAllocated, $quantityKg, 3), bcadd((string) $source->quantity_kg, $excess, 3), 3) > 0
            || bccomp(bcadd($destinationAllocated, $quantityKg, 3), (string) $destination->final_quantity_kg, 3) > 0) {
            throw ValidationException::withMessages(['quantity_kg' => __('settlements.errors.over_allocation')]);
        }
    }

    private function ensureSameDeal(Deal $deal, DealSource $source, DealDestination $destination): void
    {
        if ($source->deal_id !== $deal->id || $destination->deal_id !== $deal->id) {
            throw ValidationException::withMessages(['allocation' => __('settlements.errors.cross_deal')]);
        }
    }

    private function ensureMutable(Deal $deal): void
    {
        $latest = $deal->settlements()->latest('revision')->first();
        if ($latest?->status === CommercialRecordStatus::Confirmed) {
            throw ValidationException::withMessages(['allocation' => __('settlements.errors.confirmed_immutable')]);
        }
        if ($deal->sources()->whereHas('inventoryMovement')->exists() || $deal->destinations()->whereHas('inventoryMovement')->exists()) {
            throw ValidationException::withMessages(['allocation' => __('settlements.errors.confirmed_immutable')]);
        }
    }
}
