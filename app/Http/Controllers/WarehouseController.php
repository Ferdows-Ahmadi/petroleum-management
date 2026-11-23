<?php

namespace App\Http\Controllers;

use App\Http\Requests\WarehouseRequest;
use App\Models\DealDestination;
use App\Models\DealSource;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\SaleItem;
use App\Models\Warehouse;
use App\Services\InventoryBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(InventoryBalanceService $balances): View
    {
        $warehouses = Warehouse::orderBy('name')->paginate(15);
        $warehouses->through(function (Warehouse $warehouse) use ($balances): Warehouse {
            $warehouse->calculated_stock_kg = $balances->balancesForWarehouse($warehouse->id)
                ->reduce(fn (string $total, string $balance): string => bcadd($total, $balance, 3), '0.000');

            return $warehouse;
        });

        return view('warehouses.index', compact('warehouses'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('warehouses.form', ['warehouse' => new Warehouse]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(WarehouseRequest $request): RedirectResponse
    {
        Warehouse::create($request->validated());

        return redirect()->route('warehouses.index')->with('success', __('warehouses.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Warehouse $warehouse, InventoryBalanceService $balances): View
    {
        $products = Product::query()->where('is_active', true)->orderBy('name')->get()
            ->map(function (Product $product) use ($warehouse, $balances): Product {
                $product->calculated_stock_kg = $balances->balanceFor($warehouse->id, $product->id);

                return $product;
            });
        $movements = $warehouse->inventoryMovements()
            ->with(['product', 'purchaseItem.purchase', 'saleItem.sale', 'dealSource.deal', 'dealDestination.deal'])
            ->latest('occurred_at')
            ->limit(20)
            ->get();

        return view('warehouses.show', compact('warehouse', 'products', 'movements'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Warehouse $warehouse): View
    {
        return view('warehouses.form', compact('warehouse'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());

        return redirect()->route('warehouses.show', $warehouse)->with('success', __('warehouses.updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if ($warehouse->inventoryMovements()->exists() || PurchaseItem::where('warehouse_id', $warehouse->id)->exists() || SaleItem::where('warehouse_id', $warehouse->id)->exists()
            || DealSource::query()->where('warehouse_id', $warehouse->id)->exists()
            || DealDestination::query()->where('warehouse_id', $warehouse->id)->exists()) {
            return back()->with('error', __('trades.errors.master_in_use'));
        }

        $warehouse->delete();

        return redirect()->route('warehouses.index')->with('success', __('warehouses.deleted'));
    }
}
