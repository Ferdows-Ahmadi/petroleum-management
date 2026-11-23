# Code Walkthrough

Start with `routes/web.php`. It shows every page, controller action, numeric binding constraint, and role group. Then read `app/Enums/UserRole.php` and `app/Http/Middleware/EnsureUserHasRole.php` to understand access control. The shared UI starts at `resources/views/layouts/app.blade.php`, `partials/sidebar.blade.php`, and `partials/topbar.blade.php`.

## Authentication and dashboard

Read in this order:

1. `routes/web.php` login/dashboard routes
2. `app/Http/Controllers/Auth/AuthenticatedSessionController.php` for credential validation and throttling
3. `app/Http/Middleware/EnsureUserIsActive.php` and `EnsureUserHasRole.php`
4. `app/Models/User.php`
5. `app/Http/Controllers/DashboardController.php`
6. `resources/views/auth/login.blade.php` and `dashboard/index.blade.php`
7. `tests/Feature/AuthenticationTest.php`, `RoleAuthorizationTest.php`, `NavigationTest.php`, and `DashboardTest.php`

The dashboard does not maintain analytics. Its controller derives inventory, confirmed trade counts, partner positions, today's finance, drafts, and missing weighing state on each request.

## Products, warehouses, and partners

Follow `ProductController`, `WarehouseController`, and `BusinessPartnerController`; their Form Requests; then `Product`, `Warehouse`, `BusinessPartner`, and `BusinessPartnerRole`. The partner model relations explain customer/supplier reuse. Corresponding Blade folders and management tests show validation, soft deletion, filtering, and in-use restrictions.

## Purchases

Read:

1. Purchase routes in `routes/web.php`
2. `PurchaseController`
3. `PurchaseRequest` and `ConvertsJalaliDates`
4. `TradeDraftService::createPurchase` / `updatePurchase`
5. `TradeConfirmationService::confirmPurchase`
6. `PetroleumAmountCalculator`
7. `Purchase`, `PurchaseItem`, and `InventoryMovement`
8. `resources/views/purchases/*`
9. `PurchaseWorkflowTest` and `TradeAuthorizationTest`

The crucial transition is confirmation: it writes the status and one `PURCHASE_IN` source movement per item in one database transaction.

## Sales and sale confirmation

Use the same path through `SaleController`, `SaleRequest`, `TradeDraftService`, and the sale branch of `TradeConfirmationService`. Then read `Sale`, `SaleItem`, and `SaleWorkflowTest`. Pay attention to grouped warehouse/product stock checks, warehouse locking, final-quantity validation, source uniqueness, and idempotent confirmation.

## Weighing

Read:

1. Weighing routes and role groups
2. `WeighingRecordController`
3. `WeighingRecordRequest`
4. `WeighingService::create`, `update`, and `finalize`
5. `WeighingRecord` and `SaleItem::weighingRecord`
6. `resources/views/weighings/*` plus the weighing sections in sale views
7. `WeighingWorkflowTest`

`finalize` is the important method: it calculates net on the server, locks relevant rows, writes final sale quantity/amount, and makes weighing immutable. `ticket` streams authorized private evidence.

## Inventory

Read `InventoryController`, `InventoryBalanceService`, `InventoryMovement`, the inventory views, and `InventoryLedgerTest`. `balanceFor` is deliberately simple: chronological decimal-safe signed movement accumulation. Trace a movement through its item to the source header rather than trusting a copied reference.

## Financial accounts and transactions

Read:

1. Finance routes and role groups
2. `FinancialAccountController` / `FinancialTransactionController`
3. `FinancialAccountRequest`, `PaymentRequest`, and `ExpenseRequest`
4. `FinancialTransactionService`
5. `FinancialAccountBalanceService`
6. `FinancialAccount` and `FinancialTransaction`
7. finance Blade folders
8. `FinancialAccountWorkflowTest`, `FinanceAuthorizationTest`, and `DailyJournalTest`

The transaction service normalizes USD/AFN amounts, locks account/transaction rows, confirms idempotently, and prevents editing/deleting confirmed history. The account balance service also includes linked confirmed capital cash movements.

## Partner balances

Read `PartnerFinanceController`, `PartnerOpeningBalanceRequest`, and `PartnerLedgerService`. `statementFor` merges opening, trade, and payment sources in deterministic business-date order. `hasConfirmedActivity` protects opening-balance history. `positions` includes active and inactive partners, then turns only non-zero signed ending balances into receivable/payable lists. See `partner-finance/*` and `PartnerFinanceTest`.

## Daily journal

Read `DailyJournalController`, `DailyJournalService`, and `finance/journal.blade.php`. The service derives opening balance before the selected date, merges ordinary finance and capital cash movements, sorts them, and accumulates receipt/payment/expense/running totals.

## Capital

Read:

1. Capital routes (view and mutation roles are separate)
2. `CapitalPartnerController` and `CapitalTransactionController`
3. `CapitalPartnerRequest` and `CapitalTransactionRequest`
4. `CapitalTransactionService`
5. `CapitalBalanceService`
6. capital enums and models
7. `resources/views/capital/*`
8. `CapitalWorkflowTest`

`CapitalTransactionType` owns direction rules. `CapitalTransactionService` enforces USD account requirements and immutable/idempotent confirmation. `CapitalBalanceService` derives the statement and never reads an editable balance.

## Contracts

Read `ContractController`, `ContractRequest`, `Contract`, contract enums, `resources/views/contracts/*`, and `ContractWorkflowTest`. The absence of a purchase/sale contract foreign key is intentional: contracts are useful records but never prerequisites.

## Reports and profit/loss

Read `ReportController` first for filter queries and totals, then the existing inventory/partner/finance services that the report center links. For profit/loss, read `InventoryValuationService::report` carefully:

1. Load movements with purchase/sale sources.
2. Use source business dates; process purchases before sales on the same date and record-ID order within each type; process history through the period end.
3. Keep one quantity/value stream per warehouse/product.
4. Add purchase acquisition value.
5. Value each sale at the pre-sale moving average.
6. Include period revenue/COGS and confirmed period expenses.
7. Return explicit completeness and unsupported counts.

Then inspect `resources/views/reports/*` and `ManagementReportTest` for filter, totals, exclusion, rounding, and incomplete-state examples.

## Jalali boundary

Read `app/Support/JalaliDate.php`, then `ConvertsJalaliDates`, the date-aware Form Requests, and the Blade directives in `AppServiceProvider`. Controllers/services/models still receive Gregorian values. `JalaliDateTest` and the capital feature test prove both conversion directions, digit normalization, invalid input, and rendered output.

## Database and demo data

Read migrations chronologically under `database/migrations`; finish with `2026_08_10_180000_create_final_management_tables.php`. Then read `DatabaseSeeder.php` from top to bottom. It uses natural demo markers plus `firstOrCreate`/`updateOrCreate` and idempotent confirmation services so a second run does not duplicate authoritative transactions.

## Suggested debugging path

For a failed form: route → role middleware → Form Request → controller → service → model/database constraint → session error in Blade. For a wrong balance: inspect confirmed source rows, then the relevant derived service. For a wrong sale amount: verify finalized weighing, final quantity, calculator inputs, and source movement. Never patch a derived display by storing a manual total.
