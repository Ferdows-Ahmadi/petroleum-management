<?php

use App\Enums\UserRole;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BusinessPartnerController;
use App\Http\Controllers\CapitalPartnerController;
use App\Http\Controllers\CapitalTransactionController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DailyJournalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\DealSettlementController;
use App\Http\Controllers\FinancialAccountController;
use App\Http\Controllers\FinancialTransactionController;
use App\Http\Controllers\InlinePartyController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MoneyMovementController;
use App\Http\Controllers\OfficeAccountController;
use App\Http\Controllers\OfficeConfigurationController;
use App\Http\Controllers\OfficeFinanceController;
use App\Http\Controllers\OfficeTransactionController;
use App\Http\Controllers\PartnerFinanceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RelatedDealController;
use App\Http\Controllers\AccountReportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WeighingRecordController;
use Illuminate\Support\Facades\Route;

Route::pattern('warehouse', '[0-9]+');
Route::pattern('businessPartner', '[0-9]+');
Route::pattern('product', '[0-9]+');
Route::pattern('purchase', '[0-9]+');
Route::pattern('sale', '[0-9]+');
Route::pattern('weighingRecord', '[0-9]+');
Route::pattern('financialAccount', '[0-9]+');
Route::pattern('financialTransaction', '[0-9]+');
Route::pattern('capitalPartner', '[0-9]+');
Route::pattern('capitalTransaction', '[0-9]+');
Route::pattern('contract', '[0-9]+');
Route::pattern('deal', '[0-9]+');
Route::pattern('moneyMovement', '[0-9]+');
Route::pattern('dealAllocation', '[0-9]+');
Route::pattern('dealQuantityAdjustment', '[0-9]+');
Route::pattern('dealExpense', '[0-9]+');
Route::pattern('user', '[0-9]+');
Route::pattern('dealSource', '[0-9]+');
Route::pattern('dealDestination', '[0-9]+');

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'auth.session', 'active'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/transactions', [OfficeTransactionController::class, 'index'])->name('office.transactions');

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::Accountant->value,
        UserRole::PurchaseSalesOfficer->value,
        UserRole::WarehouseOfficer->value,
        UserRole::TransportOfficer->value,
    ]))->group(function (): void {
        Route::get('/deals', [DealController::class, 'index'])->name('deals.index');
        Route::get('/deals/{deal}', [DealController::class, 'show'])->name('deals.show');
    });

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/partners', [BusinessPartnerController::class, 'index'])->name('partners.index');
    Route::get('/partners/{businessPartner}', [BusinessPartnerController::class, 'show'])->name('partners.show');

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::Accountant->value,
        UserRole::PurchaseSalesOfficer->value,
        UserRole::WarehouseOfficer->value,
    ]))->group(function (): void {
        Route::get('/storage', [InventoryController::class, 'workspace'])->name('office.storage');
        Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('/inventory', [InventoryController::class, 'balances'])->name('inventory.balances');
        Route::get('/inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
        Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::Accountant->value,
        UserRole::PurchaseSalesOfficer->value,
        UserRole::WarehouseOfficer->value,
        UserRole::TransportOfficer->value,
    ]))->group(function (): void {
        Route::get('/weighings', [WeighingRecordController::class, 'index'])->name('weighings.index');
        Route::get('/weighings/{weighingRecord}', [WeighingRecordController::class, 'show'])->name('weighings.show');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::TransportOfficer->value,
    ]))->group(function (): void {
        Route::get('/weighings/create', [WeighingRecordController::class, 'create'])->name('weighings.create');
        Route::post('/weighings', [WeighingRecordController::class, 'store'])->name('weighings.store');
        Route::get('/weighings/{weighingRecord}/edit', [WeighingRecordController::class, 'edit'])->name('weighings.edit');
        Route::put('/weighings/{weighingRecord}', [WeighingRecordController::class, 'update'])->name('weighings.update');
        Route::post('/weighings/{weighingRecord}/finalize', [WeighingRecordController::class, 'finalize'])->name('weighings.finalize');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::PurchaseSalesOfficer->value,
        UserRole::TransportOfficer->value,
    ]))->get('/weighings/{weighingRecord}/ticket', [WeighingRecordController::class, 'ticket'])->name('weighings.ticket');

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::Accountant->value,
    ]))->group(function (): void {
        Route::get('/finance', [OfficeFinanceController::class, 'index'])->name('office.finance');
        Route::get('/financial-accounts', [FinancialAccountController::class, 'index'])->name('financial-accounts.index');
        Route::get('/financial-accounts/{financialAccount}', [FinancialAccountController::class, 'show'])->name('financial-accounts.show');
        Route::get('/payments', [FinancialTransactionController::class, 'payments'])->name('payments.index');
        Route::get('/payments/{financialTransaction}', [FinancialTransactionController::class, 'show'])->name('payments.show');
        Route::get('/expenses', [FinancialTransactionController::class, 'expenses'])->name('expenses.index');
        Route::get('/expenses/{financialTransaction}', [FinancialTransactionController::class, 'show'])->name('expenses.show');
        Route::get('/daily-journal', DailyJournalController::class)->name('finance.journal');
        Route::get('/money-movements/{moneyMovement}', [MoneyMovementController::class, 'show'])->name('money-movements.show');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::Accountant->value,
        UserRole::PurchaseSalesOfficer->value,
    ]))->group(function (): void {
        Route::post('/inline/parties', [InlinePartyController::class, 'store'])->name('inline-parties.store');
        Route::get('/reports/accounts', [AccountReportController::class, 'partners'])->name('reports.accounts');
        Route::get('/accounts', [OfficeAccountController::class, 'index'])->name('office.accounts');
        Route::get('/purchases/{purchase}/ticket', [PurchaseController::class, 'ticket'])->name('purchases.ticket');
        Route::get('/partner-statements', [PartnerFinanceController::class, 'index'])->name('partners.statements');
        Route::get('/partners/{businessPartner}/statement', [PartnerFinanceController::class, 'show'])->name('partners.statement');
        Route::get('/receivables', [PartnerFinanceController::class, 'receivables'])->name('finance.receivables');
        Route::get('/payables', [PartnerFinanceController::class, 'payables'])->name('finance.payables');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::Accountant->value,
    ]))->group(function (): void {
        Route::post('/finance/expenses', [OfficeFinanceController::class, 'storeExpense'])->name('office.finance.expenses.store')->middleware('once');
        Route::get('/money-movement-deals', RelatedDealController::class)->name('money-movements.deals');
        Route::post('/money-movements', [MoneyMovementController::class, 'store'])->name('money-movements.store')->middleware('once');
        Route::post('/accounts/money', [MoneyMovementController::class, 'store'])->name('office.accounts.money.store')->middleware('once');
        Route::get('/money-movements/{moneyMovement}/edit', [MoneyMovementController::class, 'edit'])->name('money-movements.edit');
        Route::put('/money-movements/{moneyMovement}', [MoneyMovementController::class, 'update'])->name('money-movements.update');
        Route::post('/money-movements/{moneyMovement}/confirm', [MoneyMovementController::class, 'confirm'])->name('money-movements.confirm');
        Route::delete('/money-movements/{moneyMovement}', [MoneyMovementController::class, 'destroy'])->name('money-movements.destroy');
        Route::get('/financial-accounts/create', [FinancialAccountController::class, 'create'])->name('financial-accounts.create');
        Route::post('/financial-accounts', [FinancialAccountController::class, 'store'])->name('financial-accounts.store');
        Route::get('/financial-accounts/{financialAccount}/edit', [FinancialAccountController::class, 'edit'])->name('financial-accounts.edit');
        Route::put('/financial-accounts/{financialAccount}', [FinancialAccountController::class, 'update'])->name('financial-accounts.update');
        Route::delete('/financial-accounts/{financialAccount}', [FinancialAccountController::class, 'destroy'])->name('financial-accounts.destroy');

        Route::get('/payments/create', [MoneyMovementController::class, 'legacyCreate'])->name('payments.create');
        Route::post('/payments', [MoneyMovementController::class, 'legacyStore'])->name('payments.store');
        Route::get('/payments/{financialTransaction}/edit', [FinancialTransactionController::class, 'editPayment'])->name('payments.edit');
        Route::put('/payments/{financialTransaction}', [FinancialTransactionController::class, 'updatePayment'])->name('payments.update');
        Route::delete('/payments/{financialTransaction}', [FinancialTransactionController::class, 'destroy'])->name('payments.destroy');
        Route::post('/payments/{financialTransaction}/confirm', [FinancialTransactionController::class, 'confirm'])->name('payments.confirm');

        Route::get('/expenses/create', [FinancialTransactionController::class, 'createExpense'])->name('expenses.create');
        Route::post('/expenses', [FinancialTransactionController::class, 'storeExpense'])->name('expenses.store')->middleware('once');
        Route::get('/expenses/{financialTransaction}/edit', [FinancialTransactionController::class, 'editExpense'])->name('expenses.edit');
        Route::put('/expenses/{financialTransaction}', [FinancialTransactionController::class, 'updateExpense'])->name('expenses.update');
        Route::delete('/expenses/{financialTransaction}', [FinancialTransactionController::class, 'destroy'])->name('expenses.destroy');
        Route::post('/expenses/{financialTransaction}/confirm', [FinancialTransactionController::class, 'confirm'])->name('expenses.confirm');

        Route::get('/partners/{businessPartner}/opening-balance', [PartnerFinanceController::class, 'openingForm'])->name('partners.opening-balance.edit');
        Route::put('/partners/{businessPartner}/opening-balance', [PartnerFinanceController::class, 'saveOpening'])->name('partners.opening-balance.update');
        Route::delete('/partners/{businessPartner}/opening-balance', [PartnerFinanceController::class, 'deleteOpening'])->name('partners.opening-balance.destroy');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::PurchaseSalesOfficer->value,
    ]))->group(function (): void {
        Route::post('/deals', [DealController::class, 'store'])->name('deals.store')->middleware('once');
        Route::get('/deals/{deal}/edit', [DealController::class, 'edit'])->name('deals.edit');
        Route::put('/deals/{deal}', [DealController::class, 'update'])->name('deals.update');
        Route::post('/deals/{deal}/sources', [DealSettlementController::class, 'storeSource'])->name('deals.sources.store');
        Route::post('/deals/{deal}/destinations', [DealSettlementController::class, 'storeDestination'])->name('deals.destinations.store');
        Route::delete('/deals/{deal}/sources/{dealSource}', [DealSettlementController::class, 'destroySource'])->name('deals.sources.destroy');
        Route::delete('/deals/{deal}/destinations/{dealDestination}', [DealSettlementController::class, 'destroyDestination'])->name('deals.destinations.destroy');
        Route::post('/deals/{deal}/complete', [DealSettlementController::class, 'complete'])->name('deals.complete');
        Route::post('/transactions/purchases', [OfficeTransactionController::class, 'storePurchase'])->name('office.transactions.purchases.store');
        Route::post('/transactions/sales', [OfficeTransactionController::class, 'storeSale'])->name('office.transactions.sales.store');
        Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('/purchases/{purchase}/edit', [PurchaseController::class, 'edit'])->name('purchases.edit');
        Route::put('/purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');
        Route::delete('/purchases/{purchase}', [PurchaseController::class, 'destroy'])->name('purchases.destroy');
        Route::post('/purchases/{purchase}/confirm', [PurchaseController::class, 'confirm'])->name('purchases.confirm');
        Route::post('/purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');

        Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
        Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
        Route::get('/sales/{sale}/edit', [SaleController::class, 'edit'])->name('sales.edit');
        Route::put('/sales/{sale}', [SaleController::class, 'update'])->name('sales.update');
        Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');
        Route::post('/sales/{sale}/confirm', [SaleController::class, 'confirm'])->name('sales.confirm');
        Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::Accountant->value,
        UserRole::PurchaseSalesOfficer->value,
    ]))->group(function (): void {
        Route::post('/deals/{deal}/allocations', [DealSettlementController::class, 'storeAllocation'])->name('deals.allocations.store');
        Route::delete('/deals/{deal}/allocations/{dealAllocation}', [DealSettlementController::class, 'destroyAllocation'])->name('deals.allocations.destroy');
        Route::post('/deals/{deal}/adjustments', [DealSettlementController::class, 'storeAdjustment'])->name('deals.adjustments.store');
        Route::post('/deals/{deal}/expenses', [DealSettlementController::class, 'storeExpense'])->name('deals.expenses.store');
        Route::delete('/deals/{deal}/adjustments/{dealQuantityAdjustment}', [DealSettlementController::class, 'destroyAdjustment'])->name('deals.adjustments.destroy');
        Route::delete('/deals/{deal}/expenses/{dealExpense}', [DealSettlementController::class, 'destroyExpense'])->name('deals.expenses.destroy');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::Accountant->value,
    ]))->group(function (): void {
        Route::post('/deals/{deal}/adjustments/{dealQuantityAdjustment}/confirm', [DealSettlementController::class, 'confirmAdjustment'])->name('deals.adjustments.confirm');
        Route::post('/deals/{deal}/expenses/{dealExpense}/confirm', [DealSettlementController::class, 'confirmExpense'])->name('deals.expenses.confirm');
        Route::post('/deals/{deal}/settlement/prepare', [DealSettlementController::class, 'prepare'])->name('deals.settlement.prepare');
        Route::post('/deals/{deal}/settlement/confirm', [DealSettlementController::class, 'confirm'])->name('deals.settlement.confirm');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
    ]))->group(function (): void {
        Route::get('/configuration', OfficeConfigurationController::class)->name('office.configuration');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::TransportOfficer->value,
    ]))->group(function (): void {
        Route::post('/deals/{deal}/weighing/complete', [DealController::class, 'completeWeighing'])->name('deals.weighing.complete');
        Route::post('/transactions/sales/{sale}/weighing', [OfficeTransactionController::class, 'startWeighing'])->name('office.transactions.sales.weighing.store');
        Route::post('/transactions/sales/{sale}/weighing/complete', [OfficeTransactionController::class, 'completeWeighing'])->name('office.transactions.sales.weighing.complete');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::PurchaseSalesOfficer->value,
    ]))->group(function (): void {
        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        Route::get('/partners/create', [BusinessPartnerController::class, 'create'])->name('partners.create');
        Route::post('/partners', [BusinessPartnerController::class, 'store'])->name('partners.store');
        Route::get('/partners/{businessPartner}/edit', [BusinessPartnerController::class, 'edit'])->name('partners.edit');
        Route::put('/partners/{businessPartner}', [BusinessPartnerController::class, 'update'])->name('partners.update');
        Route::delete('/partners/{businessPartner}', [BusinessPartnerController::class, 'destroy'])->name('partners.destroy');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::WarehouseOfficer->value,
    ]))->group(function (): void {
        Route::get('/warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create');
        Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::get('/warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('warehouses.edit');
        Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
        Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::Accountant->value,
    ]))->group(function (): void {
        Route::get('/capital-partners', [CapitalPartnerController::class, 'index'])->name('capital-partners.index');
        Route::get('/capital-partners/{capitalPartner}', [CapitalPartnerController::class, 'show'])->name('capital-partners.show');
        Route::get('/capital-transactions', [CapitalTransactionController::class, 'index'])->name('capital-transactions.index');
        Route::get('/capital-transactions/{capitalTransaction}', [CapitalTransactionController::class, 'show'])->name('capital-transactions.show');

        Route::get('/reports/accounts/journal', [AccountReportController::class, 'journal'])->name('reports.accounts.journal');
        Route::get('/reports/expenses', [ReportController::class, 'expenses'])->name('reports.expenses');
        Route::get('/reports/capital', [ReportController::class, 'capital'])->name('reports.capital');
        Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::Accountant->value,
        UserRole::PurchaseSalesOfficer->value,
    ]))->get('/reports/settlements', [ReportController::class, 'settlements'])->name('reports.settlements');

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::Accountant->value,
        UserRole::PurchaseSalesOfficer->value,
    ]))->group(function (): void {
        Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
        Route::get('/contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::Accountant->value,
        UserRole::PurchaseSalesOfficer->value,
        UserRole::WarehouseOfficer->value,
    ]))->group(function (): void {
        Route::get('/reports/purchases', [ReportController::class, 'purchases'])->name('reports.purchases');
        Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
        Route::get('/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::Accountant->value,
        UserRole::PurchaseSalesOfficer->value,
        UserRole::WarehouseOfficer->value,
        UserRole::TransportOfficer->value,
    ]))->group(function (): void {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/weighings', [ReportController::class, 'weighings'])->name('reports.weighings');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::Accountant->value,
    ]))->group(function (): void {
        Route::get('/capital-partners/create', [CapitalPartnerController::class, 'create'])->name('capital-partners.create');
        Route::post('/capital-partners', [CapitalPartnerController::class, 'store'])->name('capital-partners.store');
        Route::get('/capital-partners/{capitalPartner}/edit', [CapitalPartnerController::class, 'edit'])->name('capital-partners.edit');
        Route::put('/capital-partners/{capitalPartner}', [CapitalPartnerController::class, 'update'])->name('capital-partners.update');
        Route::delete('/capital-partners/{capitalPartner}', [CapitalPartnerController::class, 'destroy'])->name('capital-partners.destroy');
        Route::get('/capital-transactions/create', [CapitalTransactionController::class, 'create'])->name('capital-transactions.create');
        Route::post('/capital-transactions', [CapitalTransactionController::class, 'store'])->name('capital-transactions.store');
        Route::get('/capital-transactions/{capitalTransaction}/edit', [CapitalTransactionController::class, 'edit'])->name('capital-transactions.edit');
        Route::put('/capital-transactions/{capitalTransaction}', [CapitalTransactionController::class, 'update'])->name('capital-transactions.update');
        Route::delete('/capital-transactions/{capitalTransaction}', [CapitalTransactionController::class, 'destroy'])->name('capital-transactions.destroy');
        Route::post('/capital-transactions/{capitalTransaction}/confirm', [CapitalTransactionController::class, 'confirm'])->name('capital-transactions.confirm');
    });

    Route::middleware('role:'.implode(',', [
        UserRole::GeneralManager->value,
        UserRole::SystemAdmin->value,
        UserRole::PurchaseSalesOfficer->value,
    ]))->group(function (): void {
        Route::get('/contracts/create', [ContractController::class, 'create'])->name('contracts.create');
        Route::post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
        Route::get('/contracts/{contract}/edit', [ContractController::class, 'edit'])->name('contracts.edit');
        Route::put('/contracts/{contract}', [ContractController::class, 'update'])->name('contracts.update');
        Route::delete('/contracts/{contract}', [ContractController::class, 'destroy'])->name('contracts.destroy');
    });
});
