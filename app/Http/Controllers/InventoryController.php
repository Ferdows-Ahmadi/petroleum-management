<?php

namespace App\Http\Controllers;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryBalanceService;
use App\Services\InventoryValuationService;
use App\Services\StorageClaimService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function workspace(Request $request, InventoryBalanceService $balances, InventoryValuationService $valuation, StorageClaimService $claims): View
    {
        $warehouses = Warehouse::query()->where('is_active', true)->withCount('inventoryMovements')->orderByDesc('inventory_movements_count')->orderBy('name')->get();
        $products = Product::query()->where('is_active', true)->orderBy('name')->get();
        $warehouse = $request->integer('warehouse_id') ? $warehouses->firstWhere('id', $request->integer('warehouse_id')) : $warehouses->first();
        $productId = $request->integer('product_id') ?: null;
        $rows = collect();

        if ($warehouse) {
            foreach ($products as $product) {
                if ($productId && $productId !== $product->id) {
                    continue;
                }
                $report = $valuation->report(null, null, $product->id, $warehouse->id);
                $rows->push([
                    'product' => $product,
                    'balance' => $balances->balanceFor($warehouse->id, $product->id),
                    'value' => $report['inventory_value'],
                    'claim' => $claims->position($warehouse->id, $product->id),
                    'last_movement_at' => InventoryMovement::query()->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->latest('occurred_at')->value('occurred_at'),
                ]);
            }
        }

        $movements = $warehouse ? InventoryMovement::query()
            ->with(['warehouse', 'product', 'purchaseItem.purchase', 'saleItem.sale', 'dealSource.deal', 'dealDestination.deal'])
            ->where('warehouse_id', $warehouse->id)
            ->when($productId, fn (Builder $query, int $id) => $query->where('product_id', $id))
            ->orderBy('occurred_at')->orderBy('id')->get() : collect();
        $runningByProduct = [];
        $incoming = '0.000';
        $outgoing = '0.000';
        $ledger = $movements->map(function (InventoryMovement $movement) use (&$runningByProduct, &$incoming, &$outgoing): array {
            $key = $movement->product_id;
            $runningByProduct[$key] ??= '0.000';
            if ($movement->movement_type->direction() === 1) {
                $incoming = bcadd($incoming, $movement->quantity_kg, 3);
                $runningByProduct[$key] = bcadd($runningByProduct[$key], $movement->quantity_kg, 3);
            } else {
                $outgoing = bcadd($outgoing, $movement->quantity_kg, 3);
                $runningByProduct[$key] = bcsub($runningByProduct[$key], $movement->quantity_kg, 3);
            }

            return ['movement' => $movement, 'running_balance' => $runningByProduct[$key]];
        })->reverse()->take(100)->values();

        $storageClaim = $rows->reduce(function (array $total, array $row): array {
            $total['source_value_usd'] = bcadd($total['source_value_usd'], $row['claim']['source_value_usd'], 2);
            $total['issue_value_usd'] = bcadd($total['issue_value_usd'], $row['claim']['issue_value_usd'], 2);
            $total['claim_usd'] = bcadd($total['claim_usd'], $row['claim']['claim_usd'], 2);

            return $total;
        }, ['source_value_usd' => '0.00', 'issue_value_usd' => '0.00', 'claim_usd' => '0.00']);

        return view('office.storage', compact('warehouses', 'products', 'warehouse', 'productId', 'rows', 'ledger', 'incoming', 'outgoing', 'storageClaim'));
    }

    public function balances(Request $request, InventoryBalanceService $balances): View
    {
        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name')->get();
        $products = Product::query()->where('is_active', true)->orderBy('name')->get();
        $rows = collect();

        foreach ($warehouses as $warehouse) {
            foreach ($products as $product) {
                if ($request->filled('warehouse_id') && (int) $request->input('warehouse_id') !== $warehouse->id) {
                    continue;
                }
                if ($request->filled('product_id') && (int) $request->input('product_id') !== $product->id) {
                    continue;
                }

                $rows->push((object) [
                    'warehouse' => $warehouse,
                    'product' => $product,
                    'balance' => $balances->balanceFor($warehouse->id, $product->id),
                ]);
            }
        }

        return view('inventory.balances', compact('rows', 'warehouses', 'products'));
    }

    public function movements(Request $request): View
    {
        $movements = InventoryMovement::query()
            ->with(['warehouse', 'product', 'purchaseItem.purchase', 'saleItem.sale', 'dealSource.deal', 'dealDestination.deal'])
            ->when($request->filled('warehouse_id'), fn (Builder $query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('product_id'), fn (Builder $query) => $query->where('product_id', $request->integer('product_id')))
            ->when(in_array($request->input('movement_type'), array_column(InventoryMovementType::cases(), 'value'), true), fn (Builder $query) => $query->where('movement_type', $request->input('movement_type')))
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('inventory.movements', [
            'movements' => $movements,
            'warehouses' => Warehouse::query()->orderBy('name')->get(),
            'products' => Product::query()->orderBy('name')->get(),
            'movementTypes' => InventoryMovementType::cases(),
        ]);
    }
}
