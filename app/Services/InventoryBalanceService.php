<?php

namespace App\Services;

use App\Models\InventoryMovement;
use Illuminate\Support\Collection;

class InventoryBalanceService
{
    public function balanceFor(int $warehouseId, int $productId): string
    {
        $balance = '0.000';

        InventoryMovement::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->orderBy('id')
            ->each(function (InventoryMovement $movement) use (&$balance): void {
                $balance = $movement->movement_type->direction() === 1
                    ? bcadd($balance, $movement->quantity_kg, 3)
                    : bcsub($balance, $movement->quantity_kg, 3);
            });

        return $balance;
    }

    /** @return Collection<int, string> */
    public function balancesForWarehouse(int $warehouseId): Collection
    {
        $balances = collect();

        InventoryMovement::query()
            ->where('warehouse_id', $warehouseId)
            ->orderBy('id')
            ->each(function (InventoryMovement $movement) use ($balances): void {
                $current = $balances->get($movement->product_id, '0.000');
                $balances->put(
                    $movement->product_id,
                    $movement->movement_type->direction() === 1
                        ? bcadd($current, $movement->quantity_kg, 3)
                        : bcsub($current, $movement->quantity_kg, 3),
                );
            });

        return $balances;
    }
}
