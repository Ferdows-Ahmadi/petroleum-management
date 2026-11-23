<?php

namespace App\Services;

use App\Enums\CommercialRecordStatus;
use App\Enums\DealDestinationType;
use App\Enums\DealQuantityAdjustmentType;
use App\Enums\DealSourceType;
use App\Enums\DealStatus;
use App\Enums\InventoryMovementType;
use App\Enums\WeighingStatus;
use App\Models\Deal;
use App\Models\DealSettlement;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DealSettlementService
{
    public function __construct(
        private readonly DealAllocationService $allocations,
        private readonly StorageCostService $storageCosts,
    ) {}

    /** @return array<string,mixed> */
    public function preview(Deal $deal, ?int $userId = null): array
    {
        if ($userId !== null) {
            $this->allocations->ensureAutomatic($deal, $userId);
        }
        $deal->refresh()->load([
            'sources.supplier', 'sources.warehouse', 'destinations.customer', 'destinations.warehouse',
            'destinations.weighingRecord', 'allocations.source', 'allocations.destination',
            'quantityAdjustments', 'expenses', 'settlements',
        ]);

        $confirmedAdjustments = $deal->quantityAdjustments->where('status', CommercialRecordStatus::Confirmed);
        $confirmedExpenses = $deal->expenses->where('status', CommercialRecordStatus::Confirmed);
        $sourceQuantity = $this->sum($deal->sources, 'quantity_kg', 3);
        $customerQuantity = $this->sum($deal->destinations->where('destination_type', DealDestinationType::Customer), 'final_quantity_kg', 3);
        $storageInQuantity = $this->sum($deal->destinations->where('destination_type', DealDestinationType::Storage), 'final_quantity_kg', 3);
        $storageOutQuantity = $this->sum($deal->sources->where('source_type', DealSourceType::Storage), 'quantity_kg', 3);
        $shortageQuantity = $this->sum($confirmedAdjustments->where('adjustment_type', DealQuantityAdjustmentType::Shortage), 'quantity_kg', 3);
        $excessQuantity = $this->sum($confirmedAdjustments->where('adjustment_type', DealQuantityAdjustmentType::Excess), 'quantity_kg', 3);
        $unexplained = bcadd(bcsub(bcsub(bcsub($sourceQuantity, $customerQuantity, 3), $storageInQuantity, 3), $shortageQuantity, 3), $excessQuantity, 3);

        $sourceValue = $this->sum($deal->sources, 'amount', 2);
        $saleRevenue = $this->sum($deal->destinations->where('destination_type', DealDestinationType::Customer), 'amount', 2);
        $customerCost = $this->sum($deal->allocations->filter(fn ($allocation): bool => $allocation->destination->destination_type === DealDestinationType::Customer), 'allocated_cost_usd', 2);
        $storageCost = $this->sum($deal->allocations->filter(fn ($allocation): bool => $allocation->destination->destination_type === DealDestinationType::Storage), 'allocated_cost_usd', 2);
        $expenseTotal = $this->sum($confirmedExpenses, 'amount_usd', 2);
        $shortageCost = $this->sum($confirmedAdjustments->where('adjustment_type', DealQuantityAdjustmentType::Shortage), 'cost_amount_usd', 2);
        $finalResult = bcsub(bcsub(bcsub($saleRevenue, $customerCost, 2), $expenseTotal, 2), $shortageCost, 2);

        $warnings = $this->warnings($deal, $unexplained);

        return compact(
            'sourceQuantity', 'customerQuantity', 'storageInQuantity', 'storageOutQuantity', 'shortageQuantity',
            'excessQuantity', 'unexplained', 'sourceValue', 'saleRevenue', 'customerCost', 'storageCost',
            'expenseTotal', 'shortageCost', 'finalResult', 'warnings',
        );
    }

    public function prepare(Deal $deal, int $userId, ?string $notes = null): DealSettlement
    {
        return DB::transaction(function () use ($deal, $userId, $notes): DealSettlement {
            $locked = Deal::query()->lockForUpdate()->findOrFail($deal->id);
            $this->hydrateStorageSourceCosts($locked);
            $preview = $this->preview($locked, $userId);
            $this->reconcileAllocationCosts($locked);
            $preview = $this->preview($locked);
            $latest = $locked->settlements()->lockForUpdate()->latest('revision')->first();
            $settlement = $latest && $latest->status === CommercialRecordStatus::Draft
                ? $latest
                : new DealSettlement(['deal_id' => $locked->id, 'revision' => ($latest?->revision ?? 0) + 1, 'created_by' => $userId]);
            $settlement->fill($this->settlementData($locked, $preview, $notes));
            $settlement->status = CommercialRecordStatus::Draft;
            $settlement->save();

            return $settlement->refresh();
        });
    }

    public function confirm(Deal $deal, int $userId, ?string $notes = null): DealSettlement
    {
        return DB::transaction(function () use ($deal, $userId, $notes): DealSettlement {
            $locked = Deal::query()->lockForUpdate()->findOrFail($deal->id);
            $settlement = $this->prepare($locked, $userId, $notes);
            $preview = $this->preview($locked);
            if ($preview['warnings'] !== []) {
                throw ValidationException::withMessages(['settlement' => $preview['warnings']]);
            }

            $this->postStorageMovements($locked, $preview);
            $settlement->update([
                'status' => CommercialRecordStatus::Confirmed,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            return $settlement->refresh();
        });
    }

    /** @return array<int,string> */
    private function warnings(Deal $deal, string $unexplained): array
    {
        $warnings = [];
        if ($deal->status !== DealStatus::Completed) {
            $warnings[] = __('settlements.errors.deal_not_completed');
        }
        if (bccomp($unexplained, '0.000', 3) !== 0) {
            $warnings[] = __('settlements.errors.unexplained_quantity');
        }
        if ($deal->quantityAdjustments->contains(fn ($row): bool => $row->status === CommercialRecordStatus::Draft)
            || $deal->expenses->contains(fn ($row): bool => $row->status === CommercialRecordStatus::Draft)) {
            $warnings[] = __('settlements.errors.draft_records');
        }
        foreach ($deal->sources as $source) {
            if ($source->quantity_kg === null || $source->rate_per_ton === null || ($source->source_type === DealSourceType::Supplier && ! $source->supplier_id) || ($source->source_type === DealSourceType::Storage && ! $source->warehouse_id)) {
                $warnings[] = __('settlements.errors.incomplete_sources');
                break;
            }
            $allocated = $this->sum($deal->allocations->where('deal_source_id', $source->id), 'quantity_kg', 3);
            $shortage = $this->sum($deal->quantityAdjustments->where('status', CommercialRecordStatus::Confirmed)->where('deal_source_id', $source->id)->where('adjustment_type', DealQuantityAdjustmentType::Shortage), 'quantity_kg', 3);
            $excess = $this->sum($deal->quantityAdjustments->where('status', CommercialRecordStatus::Confirmed)->where('deal_source_id', $source->id)->where('adjustment_type', DealQuantityAdjustmentType::Excess), 'quantity_kg', 3);
            if (bccomp(bcadd($allocated, $shortage, 3), bcadd($source->quantity_kg, $excess, 3), 3) !== 0) {
                $warnings[] = __('settlements.errors.source_allocation');
                break;
            }
        }
        foreach ($deal->destinations as $destination) {
            if ($destination->final_quantity_kg === null || ($destination->destination_type === DealDestinationType::Customer && ($destination->rate_per_ton === null || ! $destination->customer_id)) || ($destination->destination_type === DealDestinationType::Storage && ! $destination->warehouse_id)) {
                $warnings[] = __('settlements.errors.incomplete_destinations');
                break;
            }
            if ($destination->weighingRecord && $destination->weighingRecord->status !== WeighingStatus::Finalized) {
                $warnings[] = __('settlements.errors.incomplete_weighing');
                break;
            }
            $allocated = $this->sum($deal->allocations->where('deal_destination_id', $destination->id), 'quantity_kg', 3);
            if (bccomp($allocated, $destination->final_quantity_kg, 3) !== 0) {
                $warnings[] = __('settlements.errors.destination_allocation');
                break;
            }
        }

        return array_values(array_unique($warnings));
    }

    private function hydrateStorageSourceCosts(Deal $deal): void
    {
        $deal->load('sources.inventoryMovement');
        $warehouseIds = $deal->sources->pluck('warehouse_id')->merge($deal->destinations()->pluck('warehouse_id'))->filter()->unique()->sort()->values();
        Warehouse::query()->whereKey($warehouseIds)->orderBy('id')->lockForUpdate()->get();
        foreach ($deal->sources->where('source_type', DealSourceType::Storage) as $source) {
            if ($source->inventoryMovement) {
                continue;
            }
            $position = $this->storageCosts->position($source->warehouse_id, $deal->product_id);
            if ($position['average_cost_per_kg'] === null || bccomp($position['quantity_kg'], $source->quantity_kg, 3) < 0) {
                throw ValidationException::withMessages(['warehouse_id' => __('settlements.errors.insufficient_storage')]);
            }
            $raw = bcmul($position['average_cost_per_kg'], $source->quantity_kg, 12);
            $amount = bcdiv(bcadd($raw, '0.005', 3), '1', 2);
            $source->update(['rate_per_ton' => bcmul($position['average_cost_per_kg'], '1000', 4), 'amount' => $amount]);
            foreach ($source->allocations()->get() as $allocation) {
                $allocation->update(['allocated_cost_usd' => app(PetroleumAmountCalculator::class)->calculate($allocation->quantity_kg, $source->rate_per_ton)]);
            }
        }
    }

    /** Distribute purchased cost exactly; rounding must not create or destroy cents. */
    private function reconcileAllocationCosts(Deal $deal): void
    {
        // Previously confirmed snapshots and posted stock retain their cost basis.
        if ($deal->settlements()->where('status', CommercialRecordStatus::Confirmed->value)->exists()) {
            return;
        }
        $deal->load(['sources', 'allocations', 'quantityAdjustments']);
        foreach ($deal->sources as $source) {
            $rows = $deal->allocations->where('deal_source_id', $source->id)->sortBy('id');
            if ($source->amount === null || $rows->isEmpty()) {
                continue;
            }
            $adjustments = $deal->quantityAdjustments->where('deal_source_id', $source->id)->where('status', CommercialRecordStatus::Confirmed);
            $shortageCost = $this->sum($adjustments->where('adjustment_type', DealQuantityAdjustmentType::Shortage), 'cost_amount_usd', 2);
            $quantity = $this->sum($rows, 'quantity_kg', 3);
            $expectedQuantity = bcadd(bcsub($source->quantity_kg, $this->sum($adjustments->where('adjustment_type', DealQuantityAdjustmentType::Shortage), 'quantity_kg', 3), 3), $this->sum($adjustments->where('adjustment_type', DealQuantityAdjustmentType::Excess), 'quantity_kg', 3), 3);
            if (bccomp($quantity, '0', 3) <= 0 || bccomp($quantity, $expectedQuantity, 3) !== 0) {
                continue;
            }
            $availableCost = bcsub($source->amount, $shortageCost, 2);
            if (bccomp($availableCost, '0', 2) < 0) {
                throw ValidationException::withMessages(['settlement' => __('settlements.errors.invalid_allocation')]);
            }
            $shares = [];
            $distributed = '0.00';
            foreach ($rows as $row) {
                $raw = bcdiv(bcmul($availableCost, $row->quantity_kg, 12), $quantity, 12);
                $floor = bcdiv($raw, '1', 2);
                $shares[] = ['row' => $row, 'cost' => $floor, 'remainder' => bcsub($raw, $floor, 12)];
                $distributed = bcadd($distributed, $floor, 2);
            }
            usort($shares, fn (array $a, array $b): int => bccomp($b['remainder'], $a['remainder'], 12) ?: ($a['row']->id <=> $b['row']->id));
            $remainingCents = (int) bcmul(bcsub($availableCost, $distributed, 2), '100', 0);
            foreach ($shares as $index => $share) {
                $share['row']->update(['allocated_cost_usd' => $index < $remainingCents ? bcadd($share['cost'], '0.01', 2) : $share['cost']]);
            }
        }
    }

    /** @param array<string,mixed> $preview @return array<string,mixed> */
    private function settlementData(Deal $deal, array $preview, ?string $notes): array
    {
        return [
            'source_quantity_kg' => $preview['sourceQuantity'], 'customer_quantity_kg' => $preview['customerQuantity'],
            'storage_in_quantity_kg' => $preview['storageInQuantity'], 'storage_out_quantity_kg' => $preview['storageOutQuantity'],
            'shortage_quantity_kg' => $preview['shortageQuantity'], 'excess_quantity_kg' => $preview['excessQuantity'],
            'unexplained_quantity_kg' => $preview['unexplained'], 'source_value_usd' => $preview['sourceValue'],
            'sale_revenue_usd' => $preview['saleRevenue'], 'direct_expenses_usd' => $preview['expenseTotal'],
            'shortage_cost_usd' => $preview['shortageCost'], 'final_result_usd' => $preview['finalResult'],
            'snapshot' => [
                'deal_reference' => $deal->reference_code,
                'allocations' => $deal->allocations->map(fn ($row) => ['source_id' => $row->deal_source_id, 'destination_id' => $row->deal_destination_id, 'quantity_kg' => $row->quantity_kg, 'cost_usd' => $row->allocated_cost_usd])->values()->all(),
                'adjustment_ids' => $deal->quantityAdjustments->where('status', CommercialRecordStatus::Confirmed)->pluck('id')->all(),
                'expense_ids' => $deal->expenses->where('status', CommercialRecordStatus::Confirmed)->pluck('id')->all(),
            ],
            'notes' => $notes,
        ];
    }

    /** @param array<string,mixed> $preview */
    private function postStorageMovements(Deal $deal, array $preview): void
    {
        $deal->load(['sources.inventoryMovement', 'destinations.inventoryMovement', 'allocations']);
        foreach ($deal->sources->where('source_type', DealSourceType::Storage) as $source) {
            if ($source->inventoryMovement) {
                continue;
            }
            Warehouse::query()->lockForUpdate()->findOrFail($source->warehouse_id);
            $position = $this->storageCosts->position($source->warehouse_id, $deal->product_id);
            if (bccomp($position['quantity_kg'], $source->quantity_kg, 3) < 0) {
                throw ValidationException::withMessages(['warehouse_id' => __('settlements.errors.insufficient_storage')]);
            }
            InventoryMovement::query()->create([
                'warehouse_id' => $source->warehouse_id, 'product_id' => $deal->product_id,
                'movement_type' => InventoryMovementType::DealStorageOut, 'quantity_kg' => $source->quantity_kg,
                'cost_amount_usd' => $source->amount, 'occurred_at' => $deal->deal_date->startOfDay(),
                'deal_source_id' => $source->id, 'notes' => __('settlements.storage_out_note', ['reference' => $deal->reference_code]),
            ]);
        }
        foreach ($deal->destinations->where('destination_type', DealDestinationType::Storage) as $destination) {
            if ($destination->inventoryMovement) {
                continue;
            }
            $cost = $this->sum($deal->allocations->where('deal_destination_id', $destination->id), 'allocated_cost_usd', 2);
            InventoryMovement::query()->create([
                'warehouse_id' => $destination->warehouse_id, 'product_id' => $deal->product_id,
                'movement_type' => InventoryMovementType::DealStorageIn, 'quantity_kg' => $destination->final_quantity_kg,
                'cost_amount_usd' => $cost, 'occurred_at' => $deal->deal_date->startOfDay(),
                'deal_destination_id' => $destination->id, 'notes' => __('settlements.storage_in_note', ['reference' => $deal->reference_code]),
            ]);
        }
    }

    private function sum(iterable $items, string $field, int $scale): string
    {
        $total = $scale === 3 ? '0.000' : '0.00';
        foreach ($items as $item) {
            $total = bcadd($total, (string) ($item->{$field} ?? 0), $scale);
        }

        return $total;
    }
}
