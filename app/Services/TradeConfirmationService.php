<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Enums\TransactionStatus;
use App\Enums\WeighingStatus;
use App\Models\InventoryMovement;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TradeConfirmationService
{
    public function __construct(
        private readonly InventoryBalanceService $balances,
        private readonly PetroleumAmountCalculator $amountCalculator,
    ) {}

    public function confirmPurchase(Purchase $purchase, int $userId): bool
    {
        return DB::transaction(function () use ($purchase, $userId): bool {
            $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($locked->status === TransactionStatus::Confirmed) {
                return false;
            }

            $this->ensureConfirmable($locked->status);
            $items = $locked->items()->get();
            $this->ensureHasItems($items->isNotEmpty());
            Warehouse::query()->whereKey($items->pluck('warehouse_id')->unique()->sort()->values())->orderBy('id')->lockForUpdate()->get();

            foreach ($items as $item) {
                InventoryMovement::firstOrCreate(
                    ['purchase_item_id' => $item->id],
                    [
                        'warehouse_id' => $item->warehouse_id,
                        'product_id' => $item->product_id,
                        'movement_type' => InventoryMovementType::PurchaseIn,
                        'quantity_kg' => $item->quantity_kg,
                        'occurred_at' => now(),
                    ],
                );
            }

            $locked->update([
                'status' => TransactionStatus::Confirmed,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            return true;
        });
    }

    public function confirmSale(Sale $sale, int $userId): bool
    {
        return DB::transaction(function () use ($sale, $userId): bool {
            $locked = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($locked->status === TransactionStatus::Confirmed) {
                return false;
            }

            $this->ensureConfirmable($locked->status);
            $items = $locked->items()->with('weighingRecord')->lockForUpdate()->get();
            $this->ensureHasItems($items->isNotEmpty());

            foreach ($items as $item) {
                if (
                    ! $item->weighingRecord
                    || $item->weighingRecord->status !== WeighingStatus::Finalized
                    || $item->final_quantity_kg === null
                    || bccomp($item->final_quantity_kg, $item->weighingRecord->net_weight_kg, 3) !== 0
                ) {
                    throw ValidationException::withMessages(['weighing' => __('weighings.errors.sale_requires_finalized')]);
                }

                $item->update([
                    'amount' => $this->amountCalculator->calculate($item->final_quantity_kg, $item->rate_per_ton),
                ]);
            }

            $warehouseIds = $items->pluck('warehouse_id')->unique()->sort()->values();
            Warehouse::query()->whereKey($warehouseIds)->orderBy('id')->lockForUpdate()->get();

            $requiredByStockKey = [];
            foreach ($items as $item) {
                $key = $item->warehouse_id.':'.$item->product_id;
                $requiredByStockKey[$key] = bcadd($requiredByStockKey[$key] ?? '0.000', $item->final_quantity_kg, 3);
            }

            foreach ($requiredByStockKey as $key => $required) {
                [$warehouseId, $productId] = array_map('intval', explode(':', $key));
                $available = $this->balances->balanceFor($warehouseId, $productId);

                if (bccomp($available, $required, 3) < 0) {
                    throw ValidationException::withMessages([
                        'stock' => __('trades.errors.insufficient_stock', [
                            'available' => number_format((float) $available, 3),
                            'required' => number_format((float) $required, 3),
                        ]),
                    ]);
                }
            }

            foreach ($items as $item) {
                InventoryMovement::firstOrCreate(
                    ['sale_item_id' => $item->id],
                    [
                        'warehouse_id' => $item->warehouse_id,
                        'product_id' => $item->product_id,
                        'movement_type' => InventoryMovementType::SaleOut,
                        'quantity_kg' => $item->final_quantity_kg,
                        'occurred_at' => now(),
                    ],
                );
            }

            $locked->update([
                'status' => TransactionStatus::Confirmed,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            return true;
        });
    }

    public function cancelPurchase(Purchase $purchase): bool
    {
        return $this->cancel(Purchase::class, $purchase->id);
    }

    public function cancelSale(Sale $sale): bool
    {
        return $this->cancel(Sale::class, $sale->id);
    }

    /** @param class-string<Purchase|Sale> $modelClass */
    private function cancel(string $modelClass, int $id): bool
    {
        return DB::transaction(function () use ($modelClass, $id): bool {
            $transaction = $modelClass::query()->lockForUpdate()->findOrFail($id);

            if ($transaction->status === TransactionStatus::Cancelled) {
                return false;
            }

            if ($transaction->status === TransactionStatus::Confirmed) {
                throw ValidationException::withMessages(['status' => __('trades.errors.confirmed_cannot_cancel')]);
            }

            $transaction->update(['status' => TransactionStatus::Cancelled]);

            return true;
        });
    }

    private function ensureConfirmable(TransactionStatus $status): void
    {
        if ($status !== TransactionStatus::Draft) {
            throw ValidationException::withMessages(['status' => __('trades.errors.not_confirmable')]);
        }
    }

    private function ensureHasItems(bool $hasItems): void
    {
        if (! $hasItems) {
            throw ValidationException::withMessages(['items' => __('trades.errors.items_required')]);
        }
    }
}
