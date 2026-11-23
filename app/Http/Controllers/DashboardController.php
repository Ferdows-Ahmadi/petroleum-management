<?php

namespace App\Http\Controllers;

use App\Enums\DealStatus;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Enums\MoneyMovementStatus;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\DealSettlement;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\InventoryMovement;
use App\Models\MoneyMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Warehouse;
use App\Services\FinancialAccountBalanceService;
use App\Services\InventoryBalanceService;
use App\Services\InventoryValuationService;
use App\Services\PartnerLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        PartnerLedgerService $ledgers,
        InventoryValuationService $valuation,
        InventoryBalanceService $inventoryBalances,
        FinancialAccountBalanceService $accountBalances,
    ): View {
        $canViewTrade = $request->user()->hasAnyRole(
            UserRole::GeneralManager->value,
            UserRole::SystemAdmin->value,
            UserRole::Accountant->value,
            UserRole::PurchaseSalesOfficer->value,
            UserRole::WarehouseOfficer->value,
        );
        $canViewMoney = $request->user()->hasAnyRole(UserRole::GeneralManager->value, UserRole::Accountant->value);
        $inventoryKg = InventoryMovement::query()->get()->reduce(
            fn (string $total, InventoryMovement $movement): string => $movement->movement_type->direction() === 1
                ? bcadd($total, $movement->quantity_kg, 3)
                : bcsub($total, $movement->quantity_kg, 3),
            '0.000',
        );
        $receivables = $ledgers->positions('receivable')->reduce(fn (string $total, array $row): string => bcadd($total, $row['amount'], 2), '0.00');
        $payables = $ledgers->positions('payable')->reduce(fn (string $total, array $row): string => bcadd($total, $row['amount'], 2), '0.00');

        $todayFinance = FinancialTransaction::query()
            ->where('status', FinancialTransactionStatus::Confirmed->value)
            ->whereDate('transaction_date', now()->toDateString())
            ->get();
        $todayPurchases = Purchase::query()->with('items')->where('status', TransactionStatus::Confirmed->value)->whereDate('purchase_date', now()->toDateString())->get();
        $todaySales = Sale::query()->with('items')->where('status', TransactionStatus::Confirmed->value)->whereDate('sale_date', now()->toDateString())->get();
        $todayDeals = Deal::query()->with(['sources', 'destinations'])->where('status', DealStatus::Completed->value)->whereDate('deal_date', now()->toDateString())->get();
        $todayMoney = MoneyMovement::query()->where('status', MoneyMovementStatus::Confirmed->value)->whereDate('movement_date', now()->toDateString())->get();
        $valuationReport = $valuation->report();
        $accountRows = FinancialAccount::query()->where('is_active', true)->get()->map(fn ($account): array => [
            'account' => $account,
            'balance' => $accountBalances->balanceFor($account),
        ]);
        $warehouseRows = Warehouse::query()->where('is_active', true)->orderBy('name')->get()->map(function (Warehouse $warehouse) use ($inventoryBalances, $valuation): array {
            $quantity = Product::query()->where('is_active', true)->get()->reduce(
                fn (string $total, $product): string => bcadd($total, $inventoryBalances->balanceFor($warehouse->id, $product->id), 3),
                '0.000',
            );

            return ['warehouse' => $warehouse, 'quantity' => $quantity, 'value' => $valuation->report(null, null, null, $warehouse->id)['inventory_value']];
        });

        $recentDeals = Deal::query()
            ->with(['product', 'sources.supplier', 'sources.warehouse', 'destinations.customer', 'destinations.warehouse', 'latestConfirmedSettlement'])
            ->latest('deal_date')->latest('id')->limit(8)->get();
        $importantAccounts = $canViewMoney
            ? $ledgers->positions('receivable')->concat($ledgers->positions('payable'))->sortByDesc('amount')->take(8)->values()
            : collect();
        $todaySettlementResult = DealSettlement::query()
            ->whereIn('id', Deal::query()->with('latestConfirmedSettlement')->get()->pluck('latestConfirmedSettlement.id')->filter())
            ->whereDate('confirmed_at', now()->toDateString())
            ->get()->reduce(fn (string $total, DealSettlement $settlement): string => bcadd($total, $settlement->final_result_usd, 2), '0.00');

        return view('dashboard.index', [
            'metrics' => [
                'inventory_kg' => $inventoryKg,
                'inventory_value' => $valuationReport['inventory_value'],
                'today_purchases' => $todayDeals->reduce(fn ($total, $deal) => bcadd($total, $deal->total_source_amount, 2), $this->sumTradeAmounts($todayPurchases)),
                'today_sales' => $todayDeals->reduce(fn ($total, $deal) => bcadd($total, $deal->total_destination_amount, 2), $this->sumTradeAmounts($todaySales)),
                'receivables' => $receivables,
                'payables' => $payables,
                'available_usd' => $this->sumAccountCurrency($accountRows, 'USD'),
                'available_afn' => $this->sumAccountCurrency($accountRows, 'AFN'),
                'today_received' => $todayMoney->filter(fn ($m) => $m->movement_type->accountDirection() === 1)->reduce(fn ($total, $m) => bcadd($total, $m->amount_usd, 2), $this->sumType($todayFinance, FinancialTransactionType::PaymentReceived)),
                'today_paid' => $todayMoney->filter(fn ($m) => $m->movement_type->accountDirection() === -1)->reduce(fn ($total, $m) => bcadd($total, $m->amount_usd, 2), $this->sumType($todayFinance, FinancialTransactionType::PaymentPaid)),
                'today_expenses' => $this->sumType($todayFinance, FinancialTransactionType::Expense),
                'net_profit' => $valuationReport['operating_result'],
                'draft_sales' => Sale::query()->where('status', TransactionStatus::Draft->value)->count(),
                'awaiting_weighing' => SaleItem::query()->whereHas('sale', fn ($query) => $query->where('status', TransactionStatus::Draft->value))->where(function ($query): void {
                    $query->whereDoesntHave('weighingRecord')->orWhereHas('weighingRecord', fn ($weighing) => $weighing->where('status', 'DRAFT'));
                })->count(),
            ],
            'warehouseRows' => $warehouseRows,
            'recentActivity' => $this->recentActivity($canViewTrade),
            'recentDeals' => $recentDeals,
            'importantAccounts' => $importantAccounts,
            'canViewMoney' => $canViewMoney,
            'todaySettlementResult' => $todaySettlementResult,
            'openDeals' => Deal::query()->whereIn('status', [DealStatus::Draft->value, DealStatus::InProgress->value])->count(),
        ]);
    }

    private function sumType($transactions, FinancialTransactionType $type): string
    {
        return $transactions->where('transaction_type', $type)->reduce(
            fn (string $total, FinancialTransaction $transaction): string => bcadd($total, $transaction->amount_usd, 2),
            '0.00',
        );
    }

    private function sumTradeAmounts(Collection $transactions): string
    {
        return $transactions->reduce(fn (string $total, $transaction): string => bcadd($total, $transaction->total_amount, 2), '0.00');
    }

    private function sumAccountCurrency(Collection $rows, string $currency): string
    {
        return $rows->filter(fn (array $row): bool => $row['account']->currency->value === $currency)
            ->reduce(fn (string $total, array $row): string => bcadd($total, $row['balance'], 2), '0.00');
    }

    private function recentActivity(bool $canViewTrade): Collection
    {
        $purchases = $canViewTrade ? Purchase::query()->with(['supplier', 'items'])->latest('purchase_date')->latest('id')->limit(5)->get()->map(fn (Purchase $purchase): array => [
            'date' => $purchase->purchase_date,
            'sort' => $purchase->purchase_date->toDateString().'-P-'.str_pad((string) $purchase->id, 10, '0', STR_PAD_LEFT),
            'type' => __('navigation.purchases'),
            'party' => $purchase->supplier->name,
            'amount' => $purchase->total_amount,
            'status' => $purchase->status->label(),
            'url' => route('purchases.show', $purchase),
        ]) : collect();
        $sales = Sale::query()->with(['customer', 'items'])->latest('sale_date')->latest('id')->limit(5)->get()->map(fn (Sale $sale): array => [
            'date' => $sale->sale_date,
            'sort' => $sale->sale_date->toDateString().'-S-'.str_pad((string) $sale->id, 10, '0', STR_PAD_LEFT),
            'type' => __('navigation.sales'),
            'party' => $sale->customer->name,
            'amount' => $sale->total_amount,
            'status' => $sale->status->label(),
            'url' => $canViewTrade ? route('sales.show', $sale) : null,
        ]);

        return $purchases->concat($sales)->sortByDesc('sort')->take(8)->values();
    }
}
