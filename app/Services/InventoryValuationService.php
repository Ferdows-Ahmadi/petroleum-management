<?php

namespace App\Services;

use App\Enums\DealDestinationType;
use App\Enums\DealStatus;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Enums\InventoryMovementType;
use App\Models\Deal;
use App\Models\FinancialTransaction;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;

class InventoryValuationService
{
    /** @return array<string, mixed> */
    public function report(?string $from = null, ?string $to = null, ?int $productId = null, ?int $warehouseId = null): array
    {
        $movements = InventoryMovement::query()
            ->with(['purchaseItem.purchase', 'saleItem.sale', 'dealSource.deal', 'dealDestination.deal', 'product', 'warehouse'])
            ->when($productId, fn (Builder $query) => $query->where('product_id', $productId))
            ->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId))
            ->get()
            ->map(function (InventoryMovement $movement): array {
                $isIncoming = $movement->movement_type->direction() === 1;
                $source = match ($movement->movement_type) {
                    InventoryMovementType::PurchaseIn => $movement->purchaseItem,
                    InventoryMovementType::SaleOut => $movement->saleItem,
                    InventoryMovementType::DealStorageIn => $movement->dealDestination,
                    InventoryMovementType::DealStorageOut => $movement->dealSource,
                };
                $header = match ($movement->movement_type) {
                    InventoryMovementType::PurchaseIn => $movement->purchaseItem?->purchase,
                    InventoryMovementType::SaleOut => $movement->saleItem?->sale,
                    InventoryMovementType::DealStorageIn => $movement->dealDestination?->deal,
                    InventoryMovementType::DealStorageOut => $movement->dealSource?->deal,
                };

                return [
                    'movement' => $movement,
                    'date' => $movement->businessDate(),
                    'order' => $isIncoming ? 0 : 1,
                    'quantity' => $movement->quantity_kg,
                    'cost' => $isIncoming ? ($movement->cost_amount_usd ?? $source?->amount) : $movement->cost_amount_usd,
                    'revenue' => $movement->movement_type === InventoryMovementType::SaleOut ? $source?->amount : null,
                    'supported' => $source !== null && $header !== null && ($isIncoming ? ($movement->cost_amount_usd ?? $source?->amount) !== null : true),
                ];
            })
            ->filter(fn (array $event): bool => $to === null || $event['date'] <= $to)
            ->sortBy(fn (array $event): string => $event['movement']->valuationOrder())
            ->values();

        $streams = [];
        $revenue = '0.00';
        $cogs = '0.00';
        $unvaluedSales = 0;
        $unsupportedMovements = $movements->where('supported', false)->count();
        $saleDetails = [];

        foreach ($movements as $event) {
            $movement = $event['movement'];
            $key = $movement->warehouse_id.':'.$movement->product_id;
            $streams[$key] ??= [
                'warehouse' => $movement->warehouse,
                'product' => $movement->product,
                'quantity_kg' => '0.000',
                'value_usd_raw' => '0.000000000000',
                'supported' => true,
            ];
            $stream = &$streams[$key];

            if ($movement->movement_type->direction() === 1) {
                if (! $event['supported'] || $event['cost'] === null) {
                    $stream['supported'] = false;
                } else {
                    $stream['value_usd_raw'] = bcadd($stream['value_usd_raw'], $event['cost'], 12);
                }
                $stream['quantity_kg'] = bcadd($stream['quantity_kg'], $event['quantity'], 3);
                unset($stream);

                continue;
            }

            $inPeriod = ($from === null || $event['date'] >= $from) && ($to === null || $event['date'] <= $to);
            if ($inPeriod && $event['revenue'] !== null) {
                $revenue = bcadd($revenue, $event['revenue'], 2);
            }

            $canValue = $stream['supported'] && $event['supported'] && bccomp($stream['quantity_kg'], '0.000', 3) > 0 && bccomp($stream['quantity_kg'], $event['quantity'], 3) >= 0;
            if ($canValue) {
                $costPerKg = bcdiv($stream['value_usd_raw'], $stream['quantity_kg'], 12);
                $rawCogs = $event['cost'] !== null ? $event['cost'] : bcmul($costPerKg, $event['quantity'], 12);
                $roundedCogs = $this->roundMoney($rawCogs);
                $stream['value_usd_raw'] = bcsub($stream['value_usd_raw'], $rawCogs, 12);
                if ($inPeriod && $movement->movement_type !== InventoryMovementType::DealStorageOut) {
                    $cogs = bcadd($cogs, $roundedCogs, 2);
                    $saleDetails[] = ['movement' => $movement, 'cogs_usd' => $roundedCogs, 'cost_per_ton' => bcmul($costPerKg, '1000', 4), 'valued' => true];
                }
            } else {
                $stream['supported'] = false;
                if ($inPeriod) {
                    $unvaluedSales++;
                    $saleDetails[] = ['movement' => $movement, 'cogs_usd' => null, 'cost_per_ton' => null, 'valued' => false];
                }
            }
            $stream['quantity_kg'] = bcsub($stream['quantity_kg'], $event['quantity'], 3);
            unset($stream);
        }

        $expenses = FinancialTransaction::query()
            ->with('dealExpense.deal.latestConfirmedSettlement')
            ->where('status', FinancialTransactionStatus::Confirmed->value)
            ->where('transaction_type', FinancialTransactionType::Expense->value)
            ->when($from, fn (Builder $query) => $query->whereDate('transaction_date', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('transaction_date', '<=', $to))
            ->get()
            ->reject(function (FinancialTransaction $transaction): bool {
                $expense = $transaction->dealExpense;
                $effective = $expense?->deal?->latestConfirmedSettlement;

                return $expense && $effective && in_array($expense->id, $effective->snapshot['expense_ids'] ?? [], true);
            })
            ->reduce(fn (string $total, FinancialTransaction $transaction): string => bcadd($total, $transaction->amount_usd, 2), '0.00');

        $deals = Deal::query()->where('status', DealStatus::Completed->value)
            ->with(['destinations', 'latestConfirmedSettlement', 'expenses'])
            ->when($from, fn ($q) => $q->whereDate('deal_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('deal_date', '<=', $to))
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->when($warehouseId, fn ($q) => $q->where(fn ($q) => $q->whereHas('sources', fn ($s) => $s->where('warehouse_id', $warehouseId))->orWhereHas('destinations', fn ($d) => $d->where('warehouse_id', $warehouseId))))
            ->get();
        $unsettledDeals = 0;
        foreach ($deals as $deal) {
            $revenue = bcadd($revenue, $deal->total_destination_amount, 2);
            $settlement = $deal->latestConfirmedSettlement;
            if (! $settlement) {
                $unsettledDeals++;

                continue;
            }
            $customerDestinationIds = $deal->destinations->where('destination_type', DealDestinationType::Customer)->pluck('id')->all();
            foreach ($settlement->snapshot['allocations'] as $allocation) {
                if (in_array($allocation['destination_id'], $customerDestinationIds, true)) {
                    $cogs = bcadd($cogs, $allocation['cost_usd'], 2);
                }
            }
            $cogs = bcadd($cogs, $settlement->shortage_cost_usd, 2);
            $expenses = bcadd($expenses, $settlement->direct_expenses_usd, 2);

        }

        $streamRows = collect($streams)->map(function (array $stream): array {
            $averagePerTon = $stream['supported'] && bccomp($stream['quantity_kg'], '0.000', 3) > 0
                ? bcmul(bcdiv($stream['value_usd_raw'], $stream['quantity_kg'], 12), '1000', 4)
                : null;

            return $stream + [
                'value_usd' => $stream['supported'] ? $this->roundMoney($stream['value_usd_raw']) : null,
                'average_cost_per_ton' => $averagePerTon,
            ];
        })->values();

        $complete = $unvaluedSales === 0 && $unsupportedMovements === 0 && $unsettledDeals === 0;
        $grossProfit = $complete ? bcsub($revenue, $cogs, 2) : null;
        $inventoryValue = $streamRows->contains(fn (array $stream): bool => $stream['value_usd'] === null)
            ? null
            : $streamRows->reduce(fn (string $total, array $stream): string => bcadd($total, $stream['value_usd'], 2), '0.00');

        return [
            'revenue' => $revenue,
            'cogs' => $complete ? $cogs : null,
            'gross_profit' => $grossProfit,
            'expenses' => $expenses,
            'operating_result' => $complete ? bcsub($grossProfit, $expenses, 2) : null,
            'inventory_value' => $inventoryValue,
            'unvalued_sales_count' => $unvaluedSales,
            'unsettled_deals_count' => $unsettledDeals,
            'unsupported_movements_count' => $unsupportedMovements,
            'is_complete' => $complete,
            'streams' => $streamRows,
            'sales' => collect($saleDetails),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function roundMoney(string $value): string
    {
        return bcdiv(bcadd($value, '0.005', 3), '1', 2);
    }
}
