<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\PartnerRole;
use App\Enums\TransactionStatus;
use App\Http\Requests\SaleRequest;
use App\Models\BusinessPartner;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\TradeConfirmationService;
use App\Services\TradeDraftService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $sales = Sale::query()
            ->with(['customer', 'items.product'])
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim()->value();
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('reference_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $partner) => $partner->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($request->input('status'), array_column(TransactionStatus::cases(), 'value'), true), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->latest('sale_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('sales.index', compact('sales'));
    }

    public function create(): View
    {
        return view('sales.form', [
            'sale' => new Sale(['sale_date' => now()->toDateString(), 'currency' => Currency::Usd]),
            ...$this->formOptions(),
        ]);
    }

    public function store(SaleRequest $request, TradeDraftService $drafts): RedirectResponse
    {
        $sale = $drafts->createSale($request->validated(), $request->user()->id);

        return redirect()->route('sales.show', $sale)->with('success', __('trades.sale.created'));
    }

    public function show(Sale $sale): View
    {
        $sale->load(['customer', 'creator', 'confirmer', 'items.product', 'items.warehouse', 'items.inventoryMovement', 'items.weighingRecord']);

        return view('sales.show', compact('sale'));
    }

    public function edit(Sale $sale): View|RedirectResponse
    {
        if ($sale->status !== TransactionStatus::Draft) {
            return redirect()->route('sales.show', $sale)->with('error', __('trades.errors.draft_only'));
        }

        if ($sale->items()->whereHas('weighingRecord')->exists()) {
            return redirect()->route('sales.show', $sale)->with('error', __('weighings.errors.sale_edit_locked'));
        }

        $sale->load('items');

        return view('sales.form', ['sale' => $sale, ...$this->formOptions()]);
    }

    public function update(SaleRequest $request, Sale $sale, TradeDraftService $drafts): RedirectResponse
    {
        $drafts->updateSale($sale, $request->validated());

        return redirect()->route('sales.show', $sale)->with('success', __('trades.sale.updated'));
    }

    public function destroy(Sale $sale, TradeDraftService $drafts): RedirectResponse
    {
        $drafts->deleteSale($sale);

        return redirect()->route('sales.index')->with('success', __('trades.sale.deleted'));
    }

    public function confirm(Request $request, Sale $sale, TradeConfirmationService $confirmation): RedirectResponse
    {
        $changed = $confirmation->confirmSale($sale, $request->user()->id);

        return redirect()->route('sales.show', $sale)->with('success', $changed ? __('trades.sale.confirmed') : __('trades.already_confirmed'));
    }

    public function cancel(Sale $sale, TradeConfirmationService $confirmation): RedirectResponse
    {
        $confirmation->cancelSale($sale);

        return redirect()->route('sales.show', $sale)->with('success', __('trades.sale.cancelled'));
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'partners' => BusinessPartner::query()->where('is_active', true)->whereHas('roles', fn (Builder $query) => $query->where('role', PartnerRole::Customer->value))->orderBy('name')->get(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'currencies' => [Currency::Usd],
        ];
    }
}
