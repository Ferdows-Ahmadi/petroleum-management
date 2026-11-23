<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use Illuminate\Validation\ValidationException;

class StorageCostService
{
    /** @return array{quantity_kg:string,value_usd:string,average_cost_per_kg:?string} */
    public function position(int $warehouseId, int $productId): array
    {
        $quantity = '0.000';
        $value = '0.000000000000';
        $movements = InventoryMovement::query()
            ->with(['purchaseItem.purchase', 'saleItem.sale', 'dealSource.deal', 'dealDestination.deal'])
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->get()->sortBy(fn (InventoryMovement $movement): string => $movement->valuationOrder());

        foreach ($movements as $movement) {
            if ($movement->movement_type->direction() === 1) {
                $cost = $movement->cost_amount_usd;
                if ($movement->movement_type === InventoryMovementType::PurchaseIn) {
                    $cost = $movement->purchaseItem?->amount;
                }
                if ($cost === null) {
                    throw ValidationException::withMessages(['warehouse_id' => __('settlements.errors.unsupported_storage_cost')]);
                }
                $quantity = bcadd($quantity, $movement->quantity_kg, 3);
                $value = bcadd($value, $cost, 12);

                continue;
            }

            if (bccomp($quantity, $movement->quantity_kg, 3) < 0 || bccomp($quantity, '0.000', 3) <= 0) {
                throw ValidationException::withMessages(['warehouse_id' => __('settlements.errors.insufficient_storage')]);
            }
            $cost = $movement->cost_amount_usd;
            if ($cost === null) {
                $cost = bcmul(bcdiv($value, $quantity, 12), $movement->quantity_kg, 12);
            }
            $quantity = bcsub($quantity, $movement->quantity_kg, 3);
            $value = bcsub($value, $cost, 12);
        }

        return [
            'quantity_kg' => $quantity,
            'value_usd' => $this->roundMoney($value),
            'average_cost_per_kg' => bccomp($quantity, '0.000', 3) > 0 ? bcdiv($value, $quantity, 12) : null,
        ];
    }

    private function roundMoney(string $value): string
    {
        return bccomp($value, '0', 12) >= 0
            ? bcdiv(bcadd($value, '0.005', 3), '1', 2)
            : bcdiv(bcsub($value, '0.005', 3), '1', 2);
    }
}
