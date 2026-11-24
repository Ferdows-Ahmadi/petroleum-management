<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\PartnerRole;
use App\Enums\TransactionStatus;
use App\Http\Requests\PurchaseRequest;
use App\Models\BusinessPartner;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Warehouse;
use App\Services\PrivateEvidenceService;
use App\Services\TradeConfirmationService;
use App\Services\TradeEntryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $purchases = Purchase::query()
            ->with(['supplier', 'items.product'])
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim()->value();
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('reference_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn (Builder $partner) => $partner->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($request->input('status'), array_column(TransactionStatus::purchaseStatuses(), 'value'), true), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->latest('purchase_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('purchases.index', compact('purchases'));
    }

    public function create(): View
    {
        return view('purchases.form', [
            'purchase' => new Purchase(['purchase_date' => now()->toDateString(), 'currency' => Currency::Usd]),
            ...$this->formOptions(),
        ]);
    }

    public function store(PurchaseRequest $request, TradeEntryService $entries, PrivateEvidenceService $evidence): RedirectResponse
    {
        $data = $request->validated();
        $path = $request->file('ticket_image') ? $evidence->storePurchaseBill($request->file('ticket_image')) : null;
        try {
            $purchase = $entries->createPurchase([...$data, 'ticket_image_path' => $path], $request->user()->id);
        } catch (\Throwable $exception) {
            $evidence->delete($path);
            throw $exception;
        }

        return redirect()->route('purchases.show', $purchase)->with('success', __('trades.purchase.created'));
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load(['supplier', 'creator', 'confirmer', 'items.product', 'items.warehouse', 'items.inventoryMovement']);

        return view('purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase): View|RedirectResponse
    {
        if ($purchase->status !== TransactionStatus::Draft) {
            return redirect()->route('purchases.show', $purchase)->with('error', __('trades.errors.draft_only'));
        }

        $purchase->load('items');

        return view('purchases.form', ['purchase' => $purchase, ...$this->formOptions()]);
    }

    public function update(PurchaseRequest $request, Purchase $purchase, TradeEntryService $entries, PrivateEvidenceService $evidence): RedirectResponse
    {
        $oldPath = $purchase->ticket_image_path;
        $newPath = $request->file('ticket_image') ? $evidence->storePurchaseBill($request->file('ticket_image')) : null;
        try {
            $entries->updatePurchase($purchase, [...$request->validated(), ...($newPath ? ['ticket_image_path' => $newPath] : [])]);
        } catch (\Throwable $exception) {
            $evidence->delete($newPath);
            throw $exception;
        }
        if ($newPath) {
            $evidence->delete($oldPath);
        }

        return redirect()->route('purchases.show', $purchase)->with('success', __('trades.purchase.updated'));
    }

    public function destroy(Purchase $purchase, TradeEntryService $entries, PrivateEvidenceService $evidence): RedirectResponse
    {
        $path = $purchase->ticket_image_path;
        $entries->deletePurchase($purchase);
        $evidence->delete($path);

        return redirect()->route('purchases.index')->with('success', __('trades.purchase.deleted'));
    }

    public function ticket(Purchase $purchase, PrivateEvidenceService $evidence): StreamedResponse
    {
        abort_unless($purchase->ticket_image_path, 404);

        return $evidence->response(
            $purchase->ticket_image_path,
            'purchase-bill-'.$purchase->id.'.'.pathinfo($purchase->ticket_image_path, PATHINFO_EXTENSION),
        );
    }

    public function confirm(Request $request, Purchase $purchase, TradeConfirmationService $confirmation): RedirectResponse
    {
        $changed = $confirmation->confirmPurchase($purchase, $request->user()->id);

        return redirect()->route('purchases.show', $purchase)->with('success', $changed ? __('trades.purchase.confirmed') : __('trades.already_confirmed'));
    }

    public function cancel(Purchase $purchase, TradeConfirmationService $confirmation): RedirectResponse
    {
        $confirmation->cancelPurchase($purchase);

        return redirect()->route('purchases.show', $purchase)->with('success', __('trades.purchase.cancelled'));
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'partners' => BusinessPartner::query()->where('is_active', true)->whereHas('roles', fn (Builder $query) => $query->where('role', PartnerRole::Supplier->value))->orderBy('name')->get(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'currencies' => [Currency::Usd],
        ];
    }
}
