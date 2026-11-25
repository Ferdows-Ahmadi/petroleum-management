<?php

namespace App\Http\Controllers;

use App\Enums\CommercialRecordStatus;
use App\Enums\ExpenseCategory;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Enums\TransactionStatus;
use App\Enums\WeighingStatus;
use App\Http\Requests\ReportFilterRequest;
use App\Models\BusinessPartner;
use App\Models\CapitalPartner;
use App\Models\Deal;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Models\WeighingRecord;
use App\Services\CapitalBalanceService;
use App\Services\DealTradeReportService;
use App\Services\InventoryValuationService;
use App\Support\ReportNavigation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route(ReportNavigation::defaultRoute($request->user()), $request->query());
    }

    public function purchases(ReportFilterRequest $request): View
    {
        $filters = $request->validated();
        $purchases = Purchase::query()
            ->with(['supplier', 'items' => fn ($query) => $query->with(['product', 'warehouse'])
                ->when($filters['product_id'] ?? null, fn ($query, $id) => $query->where('product_id', $id))
                ->when($filters['warehouse_id'] ?? null, fn ($query, $id) => $query->where('warehouse_id', $id))])
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('purchase_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('purchase_date', '<=', $date))
            ->when($filters['partner_id'] ?? null, fn (Builder $query, int $id) => $query->where('business_partner_id', $id))
            ->when(! empty($filters['product_id']) || ! empty($filters['warehouse_id']), fn (Builder $query) => $query->whereHas('items', fn (Builder $items) => $items
                ->when($filters['product_id'] ?? null, fn ($items, $id) => $items->where('product_id', $id))
                ->when($filters['warehouse_id'] ?? null, fn ($items, $id) => $items->where('warehouse_id', $id))))
            ->when(in_array($filters['status'] ?? null, array_column(TransactionStatus::cases(), 'value'), true), fn (Builder $query) => $query->where('status', $filters['status']))
            ->latest('purchase_date')->latest('id')->get();

        return view('reports.trades', $this->tradeViewData($purchases, 'purchases', $filters, $request->jalaliDateInputs()));
    }

    public function sales(ReportFilterRequest $request): View
    {
        $filters = $request->validated();
        $sales = Sale::query()
            ->with(['customer', 'items' => fn ($query) => $query->with(['product', 'warehouse'])
                ->when($filters['product_id'] ?? null, fn ($query, $id) => $query->where('product_id', $id))
                ->when($filters['warehouse_id'] ?? null, fn ($query, $id) => $query->where('warehouse_id', $id))])
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('sale_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('sale_date', '<=', $date))
            ->when($filters['partner_id'] ?? null, fn (Builder $query, int $id) => $query->where('business_partner_id', $id))
            ->when(! empty($filters['product_id']) || ! empty($filters['warehouse_id']), fn (Builder $query) => $query->whereHas('items', fn (Builder $items) => $items
                ->when($filters['product_id'] ?? null, fn ($items, $id) => $items->where('product_id', $id))
                ->when($filters['warehouse_id'] ?? null, fn ($items, $id) => $items->where('warehouse_id', $id))))
            ->when(in_array($filters['status'] ?? null, array_column(TransactionStatus::cases(), 'value'), true), fn (Builder $query) => $query->where('status', $filters['status']))
            ->latest('sale_date')->latest('id')->get();

        return view('reports.trades', $this->tradeViewData($sales, 'sales', $filters, $request->jalaliDateInputs()));
    }

    public function expenses(ReportFilterRequest $request): View
    {
        $filters = $request->validated();
        $expenses = FinancialTransaction::query()->with('account')
            ->where('transaction_type', FinancialTransactionType::Expense->value)
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '<=', $date))
            ->when($filters['financial_account_id'] ?? null, fn (Builder $query, int $id) => $query->where('financial_account_id', $id))
            ->when(in_array($filters['status'] ?? null, array_column(FinancialTransactionStatus::cases(), 'value'), true), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(in_array($filters['category'] ?? null, array_column(ExpenseCategory::cases(), 'value'), true), fn (Builder $query) => $query->where('expense_category', $filters['category']))
            ->latest('transaction_date')->latest('id')->get();
        $confirmedTotal = $expenses->where('status', FinancialTransactionStatus::Confirmed)
            ->reduce(fn (string $total, FinancialTransaction $transaction): string => bcadd($total, $transaction->amount_usd, 2), '0.00');

        return view('reports.expenses', [
            'expenses' => $expenses,
            'confirmedTotal' => $confirmedTotal,
            'filters' => $filters,
            'accounts' => FinancialAccount::query()->where('is_active', true)->orderBy('name')->get(),
            'categories' => ExpenseCategory::cases(),
            'statuses' => FinancialTransactionStatus::cases(),
            'dateInputs' => $request->jalaliDateInputs(),
        ]);
    }

    public function weighings(ReportFilterRequest $request): View
    {
        $filters = $request->validated();
        $weighings = WeighingRecord::query()->with(['saleItem.sale.customer', 'saleItem.product', 'saleItem.warehouse', 'dealDestination.customer', 'dealDestination.warehouse', 'dealDestination.deal.product'])
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('weighing_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('weighing_at', '<=', $date))
            ->when($filters['partner_id'] ?? null, fn (Builder $query, int $id) => $query->where(fn ($q) => $q->whereHas('saleItem.sale', fn ($sale) => $sale->where('business_partner_id', $id))->orWhereHas('dealDestination', fn ($d) => $d->where('customer_id', $id))))
            ->when($filters['product_id'] ?? null, fn (Builder $query, int $id) => $query->where(fn ($q) => $q->whereHas('saleItem', fn ($item) => $item->where('product_id', $id))->orWhereHas('dealDestination.deal', fn ($d) => $d->where('product_id', $id))))
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, int $id) => $query->where(fn ($q) => $q->whereHas('saleItem', fn ($item) => $item->where('warehouse_id', $id))->orWhereHas('dealDestination', fn ($d) => $d->where('warehouse_id', $id))))
            ->when(in_array($filters['status'] ?? null, array_column(WeighingStatus::cases(), 'value'), true), fn (Builder $query) => $query->where('status', $filters['status']))
            ->latest('weighing_at')->latest('id')->get();

        return view('reports.weighings', [
            'weighings' => $weighings,
            'totalNet' => $this->weighingTotalsRecords($weighings, $filters)
                ->reduce(fn (string $total, WeighingRecord $record): string => bcadd($total, $record->net_weight_kg, 3), '0.000'),
            'totalRecords' => $this->weighingTotalsRecords($weighings, $filters)->count(),
            'totalsConfirmedOnly' => ! in_array($filters['status'] ?? null, array_column(WeighingStatus::cases(), 'value'), true),
            'filters' => $filters,
            'partners' => BusinessPartner::query()->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => WeighingStatus::cases(),
            'dateInputs' => $request->jalaliDateInputs(),
        ]);
    }

    public function capital(ReportFilterRequest $request, CapitalBalanceService $balances): View
    {
        $filters = $request->validated();
        $partners = CapitalPartner::query()
            ->when($filters['capital_partner_id'] ?? null, fn (Builder $query, int $id) => $query->whereKey($id))
            ->orderBy('name')->get();
        $summaries = $partners->map(function (CapitalPartner $partner) use ($filters, $balances): array {
            $from = $filters['date_from'] ?? null;
            $to = $filters['date_to'] ?? null;
            $period = $balances->summaryFor($partner, $from, $to);
            $closing = $from ? $balances->summaryFor($partner, null, $to)['balance'] : $period['balance'];

            return ['partner' => $partner, 'closing' => $closing] + $period;
        });

        return view('reports.capital', [
            'rows' => $summaries,
            'filters' => $filters,
            'partners' => CapitalPartner::query()->where('is_active', true)->orderBy('name')->get(),
            'dateInputs' => $request->jalaliDateInputs(),
        ]);
    }

    public function inventory(ReportFilterRequest $request, InventoryValuationService $valuation): View
    {
        $report = $valuation->report(
            to: $request->validated('date_to'),
            productId: $request->integer('product_id') ?: null,
            warehouseId: $request->integer('warehouse_id') ?: null,
        );
        $quantity = $report['streams']->reduce(fn (string $sum, array $stream): string => bcadd($sum, $stream['quantity_kg'], 3), '0.000');
        $showValue = $request->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT', 'PURCHASE_SALES_OFFICER');

        return view('reports.inventory', [
            'streams' => $report['streams'],
            'value' => $report['inventory_value'],
            'products' => Product::query()->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->orderBy('name')->get(),
            'dateInputs' => $request->jalaliDateInputs(),
            'quantity' => $quantity,
            'showValue' => $showValue,
        ]);
    }

    public function profitLoss(ReportFilterRequest $request, InventoryValuationService $valuation): View
    {
        $filters = $request->validated();
        $report = $valuation->report(
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            isset($filters['product_id']) ? (int) $filters['product_id'] : null,
            isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null,
        );

        return view('reports.profit-loss', ['report' => $report, 'filters' => $filters, 'dateInputs' => $request->jalaliDateInputs()]);
    }

    public function settlements(ReportFilterRequest $request): View
    {
        $filters = $request->validated();
        $deals = Deal::query()->with(['product', 'latestSettlement', 'latestConfirmedSettlement', 'quantityAdjustments', 'expenses'])
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('deal_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('deal_date', '<=', $date))
            ->when($filters['product_id'] ?? null, fn (Builder $query, int $id) => $query->where('product_id', $id))
            ->latest('deal_date')->latest('id')->get()
            ->filter(function (Deal $deal) use ($filters): bool {
                return match ($filters['status'] ?? null) {
                    'CONFIRMED' => $deal->latestConfirmedSettlement !== null,
                    'DRAFT' => $deal->latestSettlement?->status === CommercialRecordStatus::Draft,
                    'UNRESOLVED' => $deal->latestSettlement === null,
                    default => true,
                };
            })->values();
        $confirmed = $deals->filter(fn (Deal $deal): bool => $deal->latestConfirmedSettlement !== null);

        return view('reports.settlements', [
            'deals' => $deals,
            'confirmedCount' => $confirmed->count(),
            'unresolvedCount' => $deals->count() - $confirmed->count(),
            'finalResult' => $confirmed->reduce(fn (string $sum, Deal $deal): string => bcadd($sum, $deal->latestConfirmedSettlement->final_result_usd, 2), '0.00'),
            'shortageQuantity' => $confirmed->reduce(fn (string $sum, Deal $deal): string => bcadd($sum, $deal->latestConfirmedSettlement->shortage_quantity_kg, 3), '0.000'),
            'storageQuantity' => $confirmed->reduce(fn (string $sum, Deal $deal): string => bcadd($sum, $deal->latestConfirmedSettlement->storage_in_quantity_kg, 3), '0.000'),
            'expenseTotal' => $confirmed->reduce(fn (string $sum, Deal $deal): string => bcadd($sum, $deal->latestConfirmedSettlement->direct_expenses_usd, 2), '0.00'),
            'filters' => $filters,
            'dateInputs' => $request->jalaliDateInputs(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /** @param Collection<int, Purchase|Sale> $records
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function tradeViewData(Collection $records, string $mode, array $filters, array $dateInputs): array
    {
        $totalsRecords = $this->tradeTotalsRecords($records, $filters);
        $dealRows = app(DealTradeReportService::class)->rows($mode, $filters);
        $dealTotals = in_array($filters['status'] ?? null, array_column(TransactionStatus::cases(), 'value'), true) ? $dealRows : $dealRows->where('confirmed', true);
        $quantity = $totalsRecords->reduce(fn (string $total, $record): string => bcadd($total, $mode === 'purchases' ? $record->total_quantity_kg : $record->total_final_quantity_kg, 3), '0.000');
        $quantity = $dealTotals->reduce(fn ($total, $row) => bcadd($total, $row['quantity'] ?? '0', 3), $quantity);
        $amounts = [];
        foreach ($totalsRecords as $record) {
            $currency = $record->currency->value;
            $amounts[$currency] = bcadd($amounts[$currency] ?? '0', $record->total_amount, 2);
        }
        foreach ($dealTotals as $row) {
            if ($row['amount'] !== null) {
                $currency = $row['currency'];
                $amounts[$currency] = bcadd($amounts[$currency] ?? '0', $row['amount'], 2);
            }
        }

        return [
            'records' => $records,
            'dealRows' => $dealRows,
            'mode' => $mode,
            'totalQuantity' => $quantity,
            'totalAmount' => $amounts['USD'] ?? '0.00',
            'totalAmounts' => $amounts,
            'currencies' => $records->pluck('currency.value')->concat($dealRows->pluck('currency'))->unique()->implode(' / '),
            'totalRecords' => $totalsRecords->count() + $dealTotals->pluck('deal.id')->unique()->count(),
            'totalsConfirmedOnly' => ! in_array($filters['status'] ?? null, array_column(TransactionStatus::cases(), 'value'), true),
            'filters' => $filters,
            'partners' => BusinessPartner::query()->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => $mode === 'purchases' ? TransactionStatus::purchaseStatuses() : TransactionStatus::cases(),
            'dateInputs' => $dateInputs,
        ];
    }

    /** @param Collection<int, Purchase|Sale> $records */
    private function tradeTotalsRecords(Collection $records, array $filters): Collection
    {
        return in_array($filters['status'] ?? null, array_column(TransactionStatus::cases(), 'value'), true)
            ? $records
            : $records->where('status', TransactionStatus::Confirmed);
    }

    /** @param Collection<int, WeighingRecord> $records */
    private function weighingTotalsRecords(Collection $records, array $filters): Collection
    {
        return in_array($filters['status'] ?? null, array_column(WeighingStatus::cases(), 'value'), true)
            ? $records
            : $records->where('status', WeighingStatus::Finalized);
    }
}
