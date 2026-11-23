# Requirements

## Implemented in Milestone 1

- Laravel monolith with server-rendered Blade views.
- Dari default locale, RTL layout, right sidebar, responsive navigation, and localized validation/flash messages.
- Session login/logout with inactive-account rejection.
- User roles: General Manager, System Administrator, Accountant, Purchase/Sales Officer, Warehouse Officer, and Transport Officer.
- Route-level authorization for product, warehouse, and business-partner mutations.
- Petroleum product create, list, edit, validate, deactivate, and soft-delete workflows.
- Warehouse create, list, view, edit, validate, deactivate, and soft-delete workflows.
- Unified business-partner create, list, search, filter, view, edit, validate, and soft-delete workflows.
- One business partner may hold customer, supplier, or both roles.
- Dashboard counts only for implemented, persisted data.
- Fictional demonstration data.
- Reusable decimal-safe amount calculation: `quantity_kg / 1000 * rate_per_ton`.
- Automated authentication, authorization, validation, CRUD, partner-role, dashboard, and calculation tests.

## Deliberately not implemented

- Inventory transfers and adjustments.
- Trucks, drivers, loading, trips, and trip expenses.
- Settlement reconciliation.
- Payroll, bank integration, live exchange rates, notifications, external APIs, and report exports.

Some future operational areas remain visible only as disabled navigation context and expose no action routes.

## Implemented in Milestone 2

- Multi-item purchase drafts with supplier-role validation, precise quantities/rates, server-calculated amounts, editing, cancellation, deletion, detail pages, and atomic confirmation.
- Multi-item sale drafts with customer-role validation, optional requested quantity, manually entered transaction quantity, precise rates, server-calculated amounts, editing, cancellation, deletion, detail pages, and atomic confirmation.
- Unified partners continue to support customer, supplier, or both capabilities; no duplicate customer/supplier master tables were introduced.
- Confirmed purchases create one source-linked `PURCHASE_IN` movement per purchase item.
- Confirmed sales create one source-linked `SALE_OUT` movement per sale item.
- Repeated confirmation is idempotent and cannot duplicate inventory movements.
- Sale confirmation locks affected warehouses, validates grouped warehouse/product availability, and rejects the entire confirmation if any stock would become negative.
- Warehouse/product balances and warehouse totals are calculated from the inventory movement ledger and are never editable fields.
- Confirmed transactions and their source items cannot be edited, deleted, or cancelled in the current milestone.
- Draft transactions may be edited, explicitly cancelled, or deleted.
- Trade and inventory access is restricted by the existing role model.
- Database-backed Dari RTL list, form, detail, stock-balance, warehouse-stock, and movement-history views.
- Fictional demonstration purchase and sale data with source-linked inventory movements.

## Current implementation-stage limitations

- Finalized weighings are immutable. A controlled reopen/correction workflow is not yet implemented.
- Vehicle number, driver name, and station name are immutable transaction snapshots; normalized transport master data remains future work.
- Database dates remain Gregorian; important Dari forms and screens use the implemented Solar Hijri adapter at the application boundary.
- Confirmed trade and financial-transaction reversal is not implemented; confirmed history is deliberately immutable until a controlled reversal policy is approved.
- Payments affect the unified partner running account and are not allocated to individual purchases or sales.
- Currency conversion is limited to transaction-time USD equivalence for core finance; live rates, forex reconciliation, and exchange gains/losses are not implemented.

## Implemented in Milestone 3

- Sale items preserve optional `requested_quantity_kg` as planning context and nullable `final_quantity_kg` as the authoritative delivered quantity.
- One auditable weighing record may be attached to each sale item, with draft and finalized states.
- First and second truck weights are entered; the server requires first weight to exceed second weight and calculates `net_weight_kg = first_weight_kg - second_weight_kg` with decimal-safe arithmetic.
- Draft weighing records may be edited but do not change final sale quantity, amount, or inventory.
- Finalizing a weighing atomically copies its server-calculated net quantity to `sale_items.final_quantity_kg` and recalculates the item amount through the shared petroleum amount calculator.
- Every sale item must have a finalized weighing before the sale can be confirmed.
- Confirmed sales use final weighed quantities for grouped stock checks and source-linked `SALE_OUT` movements; confirmation remains atomic and idempotent.
- Optional JPG, JPEG, PNG, and WebP ticket images are stored on Laravel's private local disk and served only by an authenticated, role-restricted controller route.
- GENERAL_MANAGER, SYSTEM_ADMIN, and TRANSPORT_OFFICER may create, edit, and finalize weighings. Other operational roles may view weighing details, while private ticket access is further restricted.
- Dari-first RTL weighing list, create/edit, detail, sale-workflow, and responsive views follow the approved application shell.

## Implemented in Milestone 4

- Financial-account management for `CASH`, `EXCHANGE`, `BANK`, and `OTHER` accounts with one native currency per account, opening baseline configuration, active status, and derived current balances.
- Petroleum purchases and sales remain canonical USD trades; AFN conversion is confined to financial cash movements that record an explicit rate.
- One financial money-movement source supports partner receipts, partner payments, and operational expenses with `DRAFT` and `CONFIRMED` states.
- Draft financial transactions may be edited or deleted and have no authoritative effect; confirmed transactions are auditable, immutable, and applied exactly once.
- Partner opening balances store a positive USD amount plus an explicit `RECEIVABLE` or `PAYABLE` type.
- A unified chronological partner statement is derived from opening balance, confirmed sales, confirmed purchases, confirmed receipts, and confirmed payments. It does not duplicate trade records into a second ledger table.
- Derived receivables and payables pages translate the internal signed balance into human-readable Dari status and show payables as positive display amounts.
- Operational expenses use controlled categories and the same confirmed account-outflow source; ordinary expenses do not affect partner balances.
- USD and AFN financial transactions are supported. AFN requires an explicit historical rate stated as AFN per 1 USD, while the transaction's native currency must match its financial account.
- A derived daily journal shows opening balance, receipts, payments, expenses, chronological entries, and closing balance for one account and date.
- Finance mutation access is limited to General Manager and Accountant. System Administrator has finance viewing access, Purchase/Sales Officer has partner-status viewing access, and other operational roles cannot modify finance records.
- Dari-first RTL financial accounts, payments, expenses, partner statements, receivables, payables, and daily-journal pages follow the approved application shell.
## Implemented in Milestone 5

- Separate active/inactive capital partners that are not authentication users or customer/supplier records.
- Draft and confirmed capital transactions for initial capital, contribution, withdrawal, profit allocation, loss allocation, and directed adjustment.
- Derived capital balances; only confirmed records affect them. Cash capital types link to an active USD financial account without creating a duplicate financial transaction.
- Independent purchase, sale, and general contracts with partner, dates, optional agreed amount/currency, terms, and controlled status.
- A report center linking the required existing operational reports plus focused purchase, sales, expense, weighing, capital, and profit/loss reports.
- Per-warehouse/per-product perpetual moving weighted-average inventory valuation and period COGS using decimal-safe arithmetic and half-up cent rounding.
- Explicit incomplete profit/loss state when inventory movement source cost cannot be supported.
- Central Solar Hijri adapter and server-side input conversion; database dates remain Gregorian.
- Dashboard metrics derived from inventory movements, confirmed trades, partner ledgers, daily financial transactions, draft sales, and weighing state.
- Modest fictional seeded workflows for every implemented module, including the exact flagship customer and supplier balances.
- Final installation, source-learning, demonstration, and defense-preparation documentation.

## Final known limitations

- No warehouse transfers, stock adjustments, or opening-inventory valuation.
- No settlement allocation, reversal journal, or double-entry general ledger.
- Profit/loss includes confirmed sales revenue, derived COGS, and confirmed operational expenses only; it is not an audited statement.
- Contracts do not generate legal documents and are intentionally not required by purchases or sales.
- Jalali input is a reliable text convention, not a custom calendar widget.
