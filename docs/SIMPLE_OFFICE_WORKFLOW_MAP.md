# Simple Office Workflow Map

## Purpose and privacy boundary

This document maps the working habits found in representative, Git-ignored legacy workbooks to the simplified office interface. The workbooks were inspected read-only. No workbook, identifying filename, person, phone number, account detail, balance, or transaction row is reproduced here.

The interface should preserve the speed and mental model of the sheets, while the Laravel application keeps one authoritative source for each business event. Repeated Excel totals and ledgers become derived views, not duplicate data-entry screens.

## Shared office pattern

Across the workbooks, employees generally work in the same rhythm:

1. Open the relevant sheet.
2. Enter a date, short narrative, quantity or money, and sometimes a rate.
3. Let formulas calculate an amount or running balance.
4. Read the latest position directly below or beside the entry.

The simplified application therefore uses **form at top + recent records below**, Jalali dates, Western digits, familiar directional labels, automatic totals, and contextual actions. Database model names, movement rows, and accounting implementation details remain behind the interface.

## Workbook-to-application map

### Daily journal

- **What the human records:** date, short description, money received, or money paid.
- **Columns that matter:** date, description, incoming amount, outgoing amount, account, and running balance.
- **Derived information:** day opening, received total, paid total, expense total, and day closing.
- **Repeated elsewhere:** receipts/payments also appear in partner ledgers, exchange sheets, settlement notes, expense sheets, and general reports.
- **Authoritative source:** `FinancialTransaction` or a confirmed cash-affecting `CapitalTransaction`; the daily journal remains a derived chronological view.
- **Simplified replacement:** `مالی → روزنامچه`, with an entry form above the account/date ledger. Partner receipt/payment and ordinary expense actions use the existing transaction services.

### Warehouse/store ledger

- **What the human records:** petroleum received or issued, date, narrative, weight, rate, and amount.
- **Columns that matter:** date, product/storage context, description, kilograms, rate, incoming, outgoing, and balance.
- **Derived information:** amount, total incoming, total outgoing, current stock, approximate value, and movement totals.
- **Repeated elsewhere:** the same purchase or sale is repeated in trade, loading, settlement, partner, monthly, and general sheets.
- **Authoritative source:** confirmed `PurchaseItem` or `SaleItem` creates one traceable `InventoryMovement`; `InventoryBalanceService` and `InventoryValuationService` derive stock and value.
- **Simplified replacement:** `ذخیره`, with warehouse selection, balance summary, and recent movement ledger on one page. Stock is never manually editable.

### Exchange and financial-account ledgers

- **What the human records:** date, description, money received or paid, and the running position for an account or exchange counterparty.
- **Columns that matter:** date, description, received, paid, currency, explicit exchange rate when applicable, and balance.
- **Derived information:** running account balance and USD equivalent for explicitly rated AFN entries.
- **Repeated elsewhere:** entries also occur in the daily journal, partner ledgers, expenses, capital activity, and summary reports.
- **Authoritative source:** `FinancialTransaction` against one `FinancialAccount`, preserving the native currency and explicit historical rate.
- **Simplified replacement:** `مالی → صرافی`, showing account balances and movements without asking the user to manage accounting objects during ordinary entry.

### Customer and supplier ledgers

- **What the human records:** date, narrative, petroleum weight/rate where relevant, money in/out, and a continuing balance.
- **Columns that matter:** date, description, quantity, rate, debit, credit, and running balance.
- **Derived information:** current receivable/payable position and chronological balance after every source event.
- **Repeated elsewhere:** purchases, sales, receipts, payments, and opening positions are copied into separate journals and reports.
- **Authoritative source:** the unified `BusinessPartner` plus confirmed purchases, sales, financial transactions, and protected opening balance; `PartnerLedgerService` derives the ledger.
- **Simplified replacement:** `حساب‌ها`, with partner search, clear human wording for who owes whom, ledger rows, and direct `دریافت پول` / `پرداخت پول` actions.

### Purchase records

- **What the human records:** date, supplier, petroleum, destination storage, received kilograms, rate, optional payment, and optional weighbridge bill.
- **Columns that matter:** date, supplier, product, warehouse, quantity, rate per metric ton, calculated amount, evidence, and status.
- **Derived information:** amount, supplier payable, `PURCHASE_IN`, stock balance, account effects, and reports.
- **Repeated elsewhere:** storage, supplier ledger, loading, settlement, daily journal, monthly report, and general report.
- **Authoritative source:** one `Purchase` with items; confirmation remains the atomic trigger for inventory and partner effects.
- **Simplified replacement:** `معاملات → خرید`, with the form immediately visible and recent purchases underneath. A new supplier can be created inline without leaving the page; its optional phone appears only in that context. The purchase quantity remains the single authoritative weight, and optional bill evidence is stored privately.

### Sale and weighing records

- **What the human records:** date, customer, petroleum, source storage, optional approximate quantity, rate, either direct net weight or first/later second weight, optional bill evidence, payment context, and notes.
- **Columns that matter:** weighing mode, optional requested kilograms, first/second kilograms when two-stage, net kilograms, rate, final amount, received amount, evidence, and status.
- **Derived information:** net weight, final sale amount, `SALE_OUT`, customer receivable, inventory position, COGS, and profit/loss.
- **Repeated elsewhere:** customer ledger, storage, weighing/loading, settlement, journal, monthly, and general reports.
- **Authoritative source:** one draft `Sale` and linked `WeighingRecord` with explicit `DIRECT_NET` or `TWO_STAGE` mode; finalized weighing supplies final quantity, and confirmed sale atomically creates inventory and partner effects.
- **Simplified replacement:** `معاملات → فروش`, with one coherent screen. Direct net accepts the final kilograms without fabricated first/second values. Two-stage weighing may remain clearly visible as `منتظر وزن دوم` and be finalized later. Lower-frequency vehicle, driver, station, time, reference, and notes remain under contextual details.

### Loading and trip records

- **What the human records:** date, narrative, vehicle/driver snapshots, incoming/outgoing weights, rate, operational cost, and destination context.
- **Columns that matter:** date, description, vehicle/driver, weight, direction, related purchase/sale, and notes.
- **Derived information:** net/difference values and operational summaries.
- **Repeated elsewhere:** purchase/sale, settlement, storage, expenses, and reports.
- **Authoritative source:** currently proven trade and weighing records remain authoritative; vehicle, driver, and station stay audit snapshots on weighing records.
- **Simplified replacement:** contextual sale/weighing details and reporting. No fleet, route, trip, or loading subsystem is added in this phase.

### Settlement records

- **What the human records:** paired purchase/sale quantities, rates, amounts, differences, expenses, and explanatory notes.
- **Columns that matter:** dates, descriptions, incoming/outgoing quantities, rates, amounts, and reconciliation difference.
- **Derived information:** totals, differences, and proposed settlement position.
- **Repeated elsewhere:** trade, loading, partner, expense, storage, journal, and summary sheets.
- **Authoritative source:** confirmed trades, weighings, payments, and expenses. No editable duplicate settlement row is introduced without an approved allocation rule.
- **Simplified replacement:** derived account, inventory, finance, and report views. Complex allocation remains outside this phase.

### Capital, withdrawals, and savings

- **What the human records:** date, description, contribution/receipt, withdrawal/allocation, and running owner balance.
- **Columns that matter:** date, partner, transaction type, increase, decrease, and balance.
- **Derived information:** current capital by partner and cash effect when the transaction type requires an account.
- **Repeated elsewhere:** financial accounts, journal, expenses, and general/monthly reports.
- **Authoritative source:** confirmed `CapitalTransaction`; `CapitalBalanceService` and finance services derive balances and cash effects.
- **Simplified replacement:** `مالی → سرمایه`, combining capital position and activity while retaining existing controlled transaction types.

### Monthly and general reports

- **What the human records:** mostly copied source rows, formulas, cross-sheet links, and summary labels.
- **Columns that matter:** date range, trade quantities/amounts, stock, partner positions, cash/account movement, expenses, and operating result.
- **Derived information:** nearly the entire workbook is a report or reconciliation result.
- **Repeated elsewhere:** all operational and ledger workbooks feed these summaries, often through manual copying or cell links.
- **Authoritative source:** confirmed/finalized application transactions and derived services; reports never become editable source tables.
- **Simplified replacement:** `گزارش‌ها` opens Purchases for the general manager, system administrator, accountant, purchase/sales officer and warehouse officer. Transport officers open Weighings. The tabs switch between authorized reports, and bookmarked report URLs keep their Jalali filters. Headline totals continue to exclude inappropriate drafts.

## One-entry propagation rules

### Purchase

`purchase form` + optional private bill → `Purchase` / `PurchaseItem` → atomic confirmation → `PURCHASE_IN` → supplier ledger/payable → stock and valuation → journal/report derivations.

### Sale

`sale form` → draft `Sale` / `SaleItem` → linked direct-net or two-stage weighing + optional private bill → finalized net kilograms and amount → atomic sale confirmation → `SALE_OUT` → customer ledger/receivable → valuation and profit/loss.

### Money movement

`receive/pay/expense form` → draft `FinancialTransaction` → atomic confirmation → account balance → partner ledger when applicable → daily journal and reports.

No employee should re-enter a transaction into a second module to obtain these effects.

## Terminology and interaction decisions

- Keep `معاملات`, `حساب‌ها`, `ذخیره`, `مالی`, and `گزارش‌ها` as the five operational work areas beside the dashboard.
- Use `ورودی`, `خروجی`, `دریافت`, `پرداخت`, `باقی`, `وزن`, `نرخ`, and `تفصیل` where they match the office sheets.
- Explain partner positions as `طلب دفتر` or `طلب طرف معامله`; do not expose a signed accounting number as an instruction.
- Keep reference-data maintenance under a small authorized configuration entry, not the everyday operational menu.
- Keep contracts and detailed audit pages contextual. Preserve their backend routes and rules without presenting them as routine data-entry destinations.

## Explicit non-goals

This mapping does not authorize fleet management, driver management, route planning, settlement allocation, transfers, manual adjustments, shortage accounting, reversals, importing the private workbooks, exports, payroll, a mobile application, or new APIs. Those require separate business rules and review.
