<?php

namespace App\Services;

use App\Enums\DealDestinationType;
use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;

class StorageClaimService
{
    public function __construct(private readonly PetroleumAmountCalculator $amounts) {}

    /** @return array{quantity_kg:string,source_value_usd:string,issue_value_usd:string,claim_usd:string,state:string} */
    public function position(int $warehouseId, int $productId): array
    {
        $quantity = '0.000';
        $sourceValue = '0.00';
        $issueValue = '0.00';
        $movements = InventoryMovement::query()
            ->with(['purchaseItem', 'saleItem', 'dealDestination', 'dealSource.allocations.destination'])
            ->where('warehouse_id', $warehouseId)->where('product_id', $productId)
            ->orderBy('occurred_at')->orderBy('id')->get();

        foreach ($movements as $movement) {
            if ($movement->movement_type->direction() === 1) {
                $quantity = bcadd($quantity, $movement->quantity_kg, 3);
                $value = $movement->movement_type === InventoryMovementType::PurchaseIn
                    ? $movement->purchaseItem?->amount
                    : $movement->cost_amount_usd;
                if ($value !== null) {
                    $sourceValue = bcadd($sourceValue, $value, 2);
                }

                continue;
            }
            $quantity = bcsub($quantity, $movement->quantity_kg, 3);
            $value = $movement->movement_type === InventoryMovementType::SaleOut
                ? $movement->saleItem?->amount
                : $this->dealIssueValue($movement);
            if ($value !== null) {
                $issueValue = bcadd($issueValue, $value, 2);
            }
        }

        $claim = bcsub($sourceValue, $issueValue, 2);
        $state = bccomp($quantity, '0.000', 3) === 0
            ? (bccomp($claim, '0.00', 2) === 0 ? 'ZERO_CLOSED' : 'ZERO_RESIDUAL')
            : (bccomp($claim, '0.00', 2) >= 0 ? 'CARRIED_COST' : 'SURPLUS');

        return ['quantity_kg' => $quantity, 'source_value_usd' => $sourceValue, 'issue_value_usd' => $issueValue, 'claim_usd' => $claim, 'state' => $state];
    }

    private function dealIssueValue(InventoryMovement $movement): ?string
    {
        $source = $movement->dealSource;
        if (! $source) {
            return null;
        }
        $total = '0.00';
        foreach ($source->allocations as $allocation) {
            $destination = $allocation->destination;
            $value = $destination->destination_type === DealDestinationType::Customer
                ? $this->amounts->calculate($allocation->quantity_kg, (string) $destination->rate_per_ton)
                : $allocation->allocated_cost_usd;
            $total = bcadd($total, $value, 2);
        }

        return $total;
    }
}
