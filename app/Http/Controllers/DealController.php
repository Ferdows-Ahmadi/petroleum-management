<?php

namespace App\Http\Controllers;

use App\Enums\DealStatus;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Enums\MoneyMovementStatus;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Enums\WeighingMode;
use App\Enums\WeighingStatus;
use App\Http\Requests\CompleteWeighingRequest;
use App\Http\Requests\DealRequest;
use App\Models\BusinessPartner;
use App\Models\Deal;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WeighingRecord;
use App\Services\DealMoneySummaryService;
use App\Services\DealService;
use App\Services\DealSettlementService;
use App\Services\InlinePartnerService;
use App\Services\PrivateEvidenceService;
use App\Services\WeighingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DealController extends Controller
{
    public function index(Request $request): View
    {
        $canCreate = $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::SystemAdmin, UserRole::PurchaseSalesOfficer);
        $canViewPayments = $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::SystemAdmin, UserRole::Accountant);
        $deals = Deal::query()
            ->when($canViewPayments, fn (Builder $query) => $query->with([
                'moneyMovements' => fn ($movements) => $movements
                    ->select('id', 'deal_id', 'status', 'movement_type', 'amount_usd')
                    ->where('status', MoneyMovementStatus::Confirmed),
            ]))
            ->with(['product', 'sources.supplier', 'sources.warehouse', 'destinations.customer', 'destinations.warehouse', 'latestConfirmedSettlement'])
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim()->value();
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('reference_number', 'like', "%{$search}%")
                        ->orWhereHas('sources.supplier', fn (Builder $supplier) => $supplier->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('destinations.customer', fn (Builder $customer) => $customer->where('name', 'like', "%{$search}%"));
                    if (preg_match('/^DL-0*(\d+)$/i', $search, $id)) {
                        $query->orWhere(fn (Builder $fallback) => $fallback->whereKey((int) $id[1])
                            ->where(fn (Builder $ref) => $ref->whereNull('reference_number')->orWhere('reference_number', '')));
                    }
                });
            })
            ->when(in_array($request->input('status'), array_column(DealStatus::cases(), 'value'), true), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->when($request->integer('product_id'), fn (Builder $query, int $productId) => $query->where('product_id', $productId))
            ->latest('deal_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()->fragment('deal-ledger');

        $products = Product::query()->where('is_active', true)->orderBy('name')->get();
        $defaultProductId = $canCreate ? (Deal::query()->whereIn('product_id', $products->modelKeys())->select('product_id', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('product_id')->orderByDesc('aggregate')->value('product_id') ?? $products->first()?->id) : null;

        return view('deals.index', [
            'deals' => $deals,
            'canViewPayments' => $canViewPayments,
            'paymentStates' => $canViewPayments ? $deals->getCollection()->mapWithKeys(
                fn (Deal $deal) => [$deal->id => app(DealMoneySummaryService::class)->totals($deal)['settlement_state']]
            ) : [],
            'products' => $products,
            'warehouses' => $canCreate ? Warehouse::query()->where('is_active', true)->orderBy('name')->get() : collect(),
            'parties' => $canCreate ? BusinessPartner::query()->where('is_active', true)->with('roles')->orderBy('name')->get() : collect(),
            'defaultProductId' => $defaultProductId,
            'canViewCommercialValues' => $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::SystemAdmin, UserRole::Accountant, UserRole::PurchaseSalesOfficer),
            'canCreate' => $canCreate,
            'canWeigh' => $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::SystemAdmin),
        ]);
    }

    public function store(DealRequest $request, DealService $service, PrivateEvidenceService $evidence, InlinePartnerService $partners): RedirectResponse
    {
        $data = $request->validated();
        $hasWeight = ! empty($data['first_weight_kg']) || ! empty($data['net_weight_kg']);
        abort_unless(! $hasWeight || $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::SystemAdmin), 403);
        $path = $request->file('ticket_image') ? $evidence->storeWeighbridgeBill($request->file('ticket_image')) : null;

        try {
            if ($path) {
                $data['ticket_image_path'] = $path;
            }
            $deal = DB::transaction(function () use ($data, $request, $service, $partners): Deal {
                if (! empty($data['supplier_id']) || ! empty($data['supplier_name'])) {
                    $data['supplier_id'] = $partners->resolve(isset($data['supplier_id']) ? (int) $data['supplier_id'] : null, $data['supplier_name'] ?? null, null, PartnerRole::Supplier)->id;
                }
                if (! empty($data['customer_id']) || ! empty($data['customer_name'])) {
                    $data['customer_id'] = $partners->resolve(isset($data['customer_id']) ? (int) $data['customer_id'] : null, $data['customer_name'] ?? null, null, PartnerRole::Customer)->id;
                }

                return $service->create($data, $request->user()->id);
            });
        } catch (\Throwable $exception) {
            $evidence->delete($path);
            throw $exception;
        }

        return redirect()->route('deals.show', $deal)->with('success', $deal->status === DealStatus::Completed ? __('deals.completed') : __('deals.saved'));
    }

    public function addNote(Request $request, Deal $deal): RedirectResponse
    {
        $data = $request->validate([
            'note_body' => ['bail', 'required', 'string', 'max:3000', 'regex:/[^\p{Z}\p{C}\s]/u'],
            'operation_key' => ['required', 'uuid'],
        ], ['note_body.regex' => 'متن یادداشت را وارد کنید.']);
        $deal->additionalNotes()->create(['body' => $data['note_body'], 'created_by' => $request->user()->id]);

        return redirect(route('deals.show', $deal).'#deal-notes')->with('success', 'یادداشت ثبت شد.');
    }

    public function show(Request $request, Deal $deal, DealMoneySummaryService $moneySummary, DealSettlementService $settlements): View
    {
        $deal->load([
            'product', 'sources.supplier', 'sources.warehouse', 'destinations.customer', 'destinations.warehouse',
            'additionalNotes.author', 'destinations.weighingRecord.creator', 'destinations.weighingRecord.finalizer', 'creator', 'completer',
            'moneyMovements.counterparty', 'moneyMovements.saraf', 'moneyMovements.account',
            'allocations.source.supplier', 'allocations.source.warehouse', 'allocations.destination.customer', 'allocations.destination.warehouse',
            'quantityAdjustments.source', 'expenses.payee', 'settlements.creator', 'settlements.confirmer',
        ]);
        $canViewMoney = $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::SystemAdmin, UserRole::Accountant);
        $canRecordMoney = $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::Accountant);
        $canViewSettlement = $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::SystemAdmin, UserRole::Accountant, UserRole::PurchaseSalesOfficer);
        $canEditLines = $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::SystemAdmin, UserRole::PurchaseSalesOfficer)
            && in_array($deal->status, [DealStatus::Draft, DealStatus::InProgress], true);
        $canRecordCommercial = $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::Accountant, UserRole::PurchaseSalesOfficer);
        $canConfirmSettlement = $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::Accountant);
        $finance = $canViewMoney ? $moneySummary->forDeal($deal) : null;
        $settlement = $canViewSettlement ? $settlements->preview($deal) : null;
        $sarafs = $canRecordMoney ? BusinessPartner::query()->where('is_active', true)->withRole(PartnerRole::Saraf)->orderBy('name')->get() : collect();
        $accounts = $canRecordMoney ? FinancialAccount::query()->where('is_active', true)->orderBy('name')->get() : collect();
        $suppliers = $canEditLines ? BusinessPartner::query()->where('is_active', true)->withRole(PartnerRole::Supplier)->orderBy('name')->get() : collect();
        $customers = $canEditLines ? BusinessPartner::query()->where('is_active', true)->withRole(PartnerRole::Customer)->orderBy('name')->get() : collect();
        $warehouses = ($canEditLines || $canViewSettlement) ? Warehouse::query()->where('is_active', true)->orderBy('name')->get() : collect();
        $payees = $canRecordCommercial ? BusinessPartner::query()->where('is_active', true)->orderBy('name')->get() : collect();
        $partners = $canRecordMoney ? BusinessPartner::query()->where('is_active', true)->with('roles')->orderBy('name')->get() : collect();
        $defaultCustomerId = $defaultSupplierId = null;
        if ($canRecordMoney && $deal->status === DealStatus::Completed) {
            $activePartners = $partners->keyBy('id');
            $defaultCustomerId = $this->soleCounterpartyId($deal->destinations->pluck('customer_id'), $activePartners, PartnerRole::Customer);
            $defaultSupplierId = $this->soleCounterpartyId($deal->sources->pluck('supplier_id'), $activePartners, PartnerRole::Supplier);
        }
        $paidExpenses = $canRecordCommercial ? FinancialTransaction::query()
            ->where('transaction_type', FinancialTransactionType::Expense->value)
            ->where('status', FinancialTransactionStatus::Confirmed->value)
            ->whereDoesntHave('dealExpense')->latest('transaction_date')->limit(50)->get() : collect();

        return view('deals.show', compact(
            'deal', 'finance', 'settlement', 'canViewMoney', 'canRecordMoney', 'canViewSettlement', 'canEditLines',
            'canRecordCommercial', 'canConfirmSettlement', 'sarafs', 'accounts', 'suppliers', 'customers', 'warehouses', 'payees', 'paidExpenses', 'partners',
            'defaultCustomerId', 'defaultSupplierId',
        ));
    }

    private function soleCounterpartyId(Collection $linePartyIds, Collection $activePartners, PartnerRole $role): ?int
    {
        $ids = $linePartyIds->filter()->unique();
        if ($ids->count() !== 1) {
            return null;
        }

        $party = $activePartners->get($ids->first());

        return $party?->roles->contains('role', $role) ? $party->id : null;
    }

    public function edit(Request $request, Deal $deal): View
    {
        $deal->load(['sources.supplier', 'sources.warehouse', 'destinations.customer', 'destinations.warehouse', 'destinations.weighingRecord']);
        abort_unless(in_array($deal->status, [DealStatus::Draft, DealStatus::InProgress], true), 403);
        abort_unless($deal->sources->count() === 1 && $deal->destinations->count() === 1, 403);
        abort_if($deal->destinations->first()->weighingRecord?->status === WeighingStatus::Finalized, 403);

        return view('deals.edit', [
            'editingDeal' => $deal, 'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
            'parties' => BusinessPartner::query()->where('is_active', true)->with('roles')->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'defaultProductId' => $deal->product_id, 'canWeigh' => $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::SystemAdmin),
        ]);
    }

    public function update(DealRequest $request, Deal $deal, DealService $service, InlinePartnerService $partners, PrivateEvidenceService $evidence): RedirectResponse
    {
        $data = $request->validated();
        $hasWeight = ! empty($data['first_weight_kg']) || ! empty($data['net_weight_kg']);
        abort_unless(! $hasWeight || $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::SystemAdmin), 403);
        $path = $request->file('ticket_image') ? $evidence->storeWeighbridgeBill($request->file('ticket_image')) : null;
        $oldPath = $deal->destinations()->with('weighingRecord')->first()?->weighingRecord?->ticket_image_path;
        try {
            DB::transaction(function () use ($data, $path, $request, $deal, $service, $partners): void {
                foreach (['supplier' => PartnerRole::Supplier, 'customer' => PartnerRole::Customer] as $key => $role) {
                    if (! empty($data[$key.'_id']) || ! empty($data[$key.'_name'])) {
                        $data[$key.'_id'] = $partners->resolve(isset($data[$key.'_id']) ? (int) $data[$key.'_id'] : null, $data[$key.'_name'] ?? null, null, $role)->id;
                    }
                }
                if ($path) {
                    $data['ticket_image_path'] = $path;
                }
                $service->updateDraft($deal, $data, $request->user()->id);
            });
        } catch (\Throwable $exception) {
            $evidence->delete($path);
            throw $exception;
        }
        if ($path) {
            $evidence->delete($oldPath);
        }

        return redirect()->route('deals.show', $deal)->with('success', __('deals.saved'));
    }

    public function completeWeighing(CompleteWeighingRequest $request, Deal $deal, WeighingService $weighings, DealService $deals, PrivateEvidenceService $evidence): RedirectResponse
    {
        $record = $deal->destinations()->with('weighingRecord')->get()->pluck('weighingRecord')->filter()->first();
        abort_unless(in_array($deal->status, [DealStatus::Draft, DealStatus::InProgress], true)
            && $record instanceof WeighingRecord && $record->status === WeighingStatus::Draft
            && $record->weighing_mode === WeighingMode::TwoStage, 404);

        $oldPath = $record->ticket_image_path;
        $newPath = $request->file('ticket_image') ? $evidence->storeWeighbridgeBill($request->file('ticket_image')) : null;
        try {
            DB::transaction(function () use ($request, $deal, $record, $weighings, $deals, $newPath): void {
                if ($newPath) {
                    $weighings->replaceDraftTicketPath($record, $newPath);
                }
                $weighings->completeSecondWeight($record, $request->validated('second_weight_kg'));
                $weighings->finalize($record, $request->user()->id);
                $deals->complete($deal, $request->user()->id);
            });
        } catch (\Throwable $exception) {
            $evidence->delete($newPath);
            throw $exception;
        }
        if ($newPath) {
            $evidence->delete($oldPath);
        }

        return redirect()->route('deals.show', $deal)->with('success', __('deals.completed'));
    }
}
