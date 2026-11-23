<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Enums\WeighingMode;
use App\Enums\WeighingStatus;
use App\Http\Requests\CompleteWeighingRequest;
use App\Http\Requests\PendingWeighingRequest;
use App\Http\Requests\SimplePurchaseRequest;
use App\Http\Requests\SimpleSaleRequest;
use App\Models\BusinessPartner;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Models\WeighingRecord;
use App\Services\PartnerLedgerService;
use App\Services\PrivateEvidenceService;
use App\Services\SimpleOfficeTransactionService;
use App\Services\TradeConfirmationService;
use App\Services\WeighingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OfficeTransactionController extends Controller
{
    public function index(Request $request, PartnerLedgerService $ledger, DealController $deals): View
    {
        $user = $request->user();
        $canCreateTrade = $user->hasAnyRole(
            UserRole::GeneralManager->value,
            UserRole::SystemAdmin->value,
            UserRole::PurchaseSalesOfficer->value,
        );
        if ($canCreateTrade) {
            return $deals->index($request);
        }
        $canViewTrade = $user->hasAnyRole(
            UserRole::GeneralManager->value,
            UserRole::SystemAdmin->value,
            UserRole::Accountant->value,
            UserRole::PurchaseSalesOfficer->value,
            UserRole::WarehouseOfficer->value,
        );
        $canWeigh = $user->hasAnyRole(
            UserRole::GeneralManager->value,
            UserRole::SystemAdmin->value,
            UserRole::TransportOfficer->value,
        );
        $canViewPartnerAccounts = $user->hasAnyRole(
            UserRole::GeneralManager->value,
            UserRole::SystemAdmin->value,
            UserRole::Accountant->value,
            UserRole::PurchaseSalesOfficer->value,
        );
        $canRecordFinance = $user->hasAnyRole(UserRole::GeneralManager->value);
        $products = Product::query()->where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::query()->where('is_active', true)->withCount('inventoryMovements')->orderByDesc('inventory_movements_count')->orderBy('name')->get();
        $partners = BusinessPartner::query()->where('is_active', true)->with('roles')->orderBy('name')->get();
        $purchases = $canViewTrade ? Purchase::query()->with(['supplier', 'items.product', 'items.warehouse'])->latest('purchase_date')->latest('id')->limit(12)->get() : collect();
        $sales = Sale::query()->with(['customer', 'items.product', 'items.warehouse', 'items.weighingRecord'])->latest('sale_date')->latest('id')->limit(16)->get();
        $balancePartnerIds = $canViewPartnerAccounts ? $purchases->pluck('business_partner_id')->merge($sales->pluck('business_partner_id'))->unique() : collect();
        $partnerBalances = BusinessPartner::query()->whereKey($balancePartnerIds)->get()->mapWithKeys(fn (BusinessPartner $partner): array => [$partner->id => $ledger->balanceFor($partner)]);
        $requestedTab = in_array($request->input('tab'), ['purchase', 'sale'], true) ? $request->input('tab') : 'purchase';

        return view('office.transactions', [
            'tab' => $canCreateTrade ? $requestedTab : 'sale',
            'purchases' => $purchases,
            'sales' => $sales,
            'products' => $products,
            'warehouses' => $warehouses,
            'partners' => $partners,
            'partnerBalances' => $partnerBalances,
            'financialAccounts' => $canRecordFinance ? FinancialAccount::query()->where('is_active', true)->orderBy('name')->get() : collect(),
            'defaultProduct' => $products->first(fn (Product $product) => str_contains(mb_strtolower($product->name), 'دیزل') || str_contains(mb_strtolower($product->name), 'diesel')) ?? $products->first(),
            'defaultWarehouse' => $warehouses->first(),
            'canCreateTrade' => $canCreateTrade,
            'canViewTrade' => $canViewTrade,
            'canViewPartnerAccounts' => $canViewPartnerAccounts,
            'canWeigh' => $canWeigh,
            'canRecordFinance' => $canRecordFinance,
        ]);
    }

    public function storePurchase(SimplePurchaseRequest $request, SimpleOfficeTransactionService $service): RedirectResponse
    {
        $purchase = $service->createPurchase($request->validated(), $request->user()->id, $request->user()->hasAnyRole(UserRole::GeneralManager->value));

        return redirect()->route('office.transactions', ['tab' => 'purchase'])->with('success', $purchase->status === TransactionStatus::Confirmed ? __('office.purchase_confirmed') : __('office.purchase_saved'));
    }

    public function storeSale(SimpleSaleRequest $request, SimpleOfficeTransactionService $service): RedirectResponse
    {
        $user = $request->user();
        $sale = $service->createSale(
            $request->validated(),
            $user->id,
            $user->hasAnyRole(UserRole::GeneralManager->value, UserRole::SystemAdmin->value),
            $user->hasAnyRole(UserRole::GeneralManager->value),
        );

        return redirect()->route('office.transactions', ['tab' => 'sale'])->with('success', $sale->status === TransactionStatus::Confirmed ? __('office.sale_confirmed') : __('office.sale_saved'));
    }

    public function startWeighing(PendingWeighingRequest $request, Sale $sale, WeighingService $service, PrivateEvidenceService $evidence): RedirectResponse
    {
        abort_unless($sale->status === TransactionStatus::Draft, 404);
        $item = $sale->items()->whereDoesntHave('weighingRecord')->firstOrFail();
        $data = $request->validated();
        $path = $request->file('ticket_image') ? $evidence->storeWeighbridgeBill($request->file('ticket_image')) : null;
        try {
            $record = $service->create([
                'sale_item_id' => $item->id,
                ...$data,
                ...($path ? ['ticket_image_path' => $path] : []),
            ], $request->user()->id);
        } catch (\Throwable $exception) {
            $evidence->delete($path);
            throw $exception;
        }

        if ($record->weighing_mode === WeighingMode::DirectNet || $record->second_weight_kg !== null) {
            $service->finalize($record, $request->user()->id);
        }

        return redirect()->route('office.transactions', ['tab' => 'sale'])->with('success', $record->status === WeighingStatus::Finalized ? __('office.weighing_finalized') : __('office.first_weight_saved'));
    }

    public function completeWeighing(CompleteWeighingRequest $request, Sale $sale, WeighingService $weighings, TradeConfirmationService $confirmation, PrivateEvidenceService $evidence): RedirectResponse
    {
        $record = $sale->items()->with('weighingRecord')->get()->pluck('weighingRecord')->filter()->first();
        abort_unless($sale->status === TransactionStatus::Draft && $record instanceof WeighingRecord && $record->status === WeighingStatus::Draft, 404);

        abort_unless($record->weighing_mode === WeighingMode::TwoStage, 404);
        $oldPath = $record->ticket_image_path;
        $newPath = $request->file('ticket_image') ? $evidence->storeWeighbridgeBill($request->file('ticket_image')) : null;
        try {
            DB::transaction(function () use ($request, $sale, $record, $weighings, $confirmation, $newPath): void {
                if ($newPath) {
                    $weighings->replaceDraftTicketPath($record, $newPath);
                }
                $weighings->completeSecondWeight($record, $request->validated('second_weight_kg'));
                $weighings->finalize($record, $request->user()->id);
                if ($request->user()->hasAnyRole(UserRole::GeneralManager->value, UserRole::SystemAdmin->value)) {
                    $confirmation->confirmSale($sale, $request->user()->id);
                }
            });
        } catch (\Throwable $exception) {
            $evidence->delete($newPath);
            throw $exception;
        }
        if ($newPath) {
            $evidence->delete($oldPath);
        }

        return redirect()->route('office.transactions', ['tab' => 'sale'])->with('success', __('office.weighing_finalized'));
    }
}
