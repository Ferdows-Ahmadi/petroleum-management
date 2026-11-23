# Defense Guide

This guide explains the implemented system and the reasoning behind it. Use it to understand the code; do not memorize claims you cannot demonstrate.

## 1. Project problem

Petroleum operations combine supplier purchases, warehouse stock, customer sales, weighbridge evidence, cash movement, partner balances, capital, and management reporting. A spreadsheet workflow can hold these facts, but duplicated totals and manual cross-file updates make traceability, role control, and consistent calculations difficult.

## 2. Existing Excel workflow

The legacy mapping shows separate operational workbooks for purchases, sales, warehouse registers, weighing/loading, partner accounts, daily journal, settlement, exchange activity, and capital. The application preserves the important concepts without importing private identities or treating spreadsheet totals as database design.

## 3. Objectives

- Keep one traceable source for each transaction.
- Derive inventory, partner, financial-account, capital, and report balances.
- Make final weighbridge quantity authoritative for a sale.
- Use exact decimal arithmetic for kilograms, rates, amounts, conversion, and valuation.
- Present a Dari RTL interface with Solar Hijri dates.
- Enforce server-side roles, validation, confirmation, and private evidence access.

## 4. Technology choices

- Laravel 13 provides routing, middleware, Form Requests, Eloquent, migrations, transactions, Blade, sessions, and testing.
- PHP 8.5 and BCMath support typed code and decimal-safe calculations.
- MySQL 8.4 stores normalized operational records using `DECIMAL`, `DATE`, foreign keys, and unique constraints.
- Blade, Bootstrap utilities/icons, application CSS, and small JavaScript helpers keep the interface server-rendered and auditable.
- Vite builds the browser assets.
- `morilog/jalali` supplies established Solar Hijri conversion; the application isolates it behind `JalaliDate`.

## 5. Laravel architecture

Routes define access boundaries. Controllers coordinate HTTP input/output. Form Requests authorize the shape of input and convert Jalali dates. Services contain calculations and database transactions. Eloquent models define data relationships and casts. Blade views render the Dari UI. Feature tests exercise real routes and the database; unit tests isolate conversion/calculation behavior.

## 6. Database architecture

Header/item tables represent purchases and sales. `inventory_movements` is the append-only stock source. Financial transactions are separate from partner opening balances and capital movements. Foreign keys and unique source links prevent orphaned or duplicated history. Current stock, receivables, payables, cash, capital, journals, and profit are derived rather than editable columns.

See `DATABASE_DESIGN.md` for fields and the simplified ER diagram.

## 7. Main modules

Authentication and roles; products; warehouses; business partners; purchases; sales; weighing; inventory; financial accounts; payments; expenses; partner ledgers; capital; contracts; management reports; dashboard; and fictional demonstration data.

## 8. BusinessPartner design

One `BusinessPartner` can have `CUSTOMER`, `SUPPLIER`, or both role rows. Purchase validation requires supplier capability; sale validation requires customer capability. This avoids duplicate party masters and supports a partner who buys and supplies. Capital partners remain separate because ownership is a different relationship.

## 9. Purchase workflow

A purchase begins as a draft with one or more items. The server calculates `quantity kg / 1000 × USD rate per ton`. A draft has no stock effect. Confirmation locks relevant records, changes status, and creates one unique `PURCHASE_IN` movement per item atomically. Repeated confirmation does not duplicate movements.

## 10. Inventory movement design

Movements store positive kilograms. `PURCHASE_IN` supplies `+1`; `SALE_OUT` supplies `-1`. Each movement links to its purchase or sale item, which links to the header, partner, warehouse, product, date, and amount. Warehouse/product stock is the decimal-safe sum of signed movements; warehouses have no editable stock field.

## 11. Sale workflow

A draft sale stores requested kilograms as planning context and rate. It cannot be confirmed until every item has finalized weighing evidence. Confirmation uses final kilograms, checks grouped warehouse/product availability under locks, recalculates amounts, creates unique `SALE_OUT` rows, and confirms atomically. Confirmed sales are immutable.

## 12. Weighing workflow

The user enters first and second truck weight. The service requires first greater than second and calculates `net = first - second`; no hidden submitted net is trusted. Finalization copies net kilograms to the sale item and recalculates its amount in one transaction. A ticket image is optional and stored on a private disk; an authorized controller streams it without exposing the storage path.

## 13. Financial workflow

Financial accounts have one native currency and a fixed opening baseline after confirmed activity. A financial transaction is a draft receipt, partner payment, or expense. Only confirmation affects balances. USD uses its amount directly; AFN requires an explicit historical AFN-per-USD rate. Receipts add to accounts; payments and expenses subtract. Ordinary expenses do not affect partner ledgers.

## 14. Partner ledger

One chronological derived statement combines opening balance, confirmed sales, confirmed purchases, receipts, and payments. Positive balance means the partner owes the company; negative means the company owes the partner. Receivables filter positive rows, while payables show the absolute amount of negative rows. Inactive partners with non-zero historical balances remain visible and are marked inactive, while new trade/payment forms still require an active partner. An opening balance becomes immutable once confirmed ledger activity exists. Payments are not allocated to specific invoices.

## 15. Capital management

A capital partner is not a user. Only confirmed capital transactions affect capital:

`initial + contributions + profit allocations - withdrawals - loss allocations ± directed adjustments`

Initial capital, contributions, and withdrawals require an active USD account and affect its derived balance directly. The system does not also create a financial transaction, preventing double counting. Profit/loss allocations and adjustments are non-cash. Confirmed rows are immutable.

## 16. Reports

Reports query existing sources and never store editable totals. The center links purchase, sales, inventory, movement, partner, receivable, payable, financial-account, journal, expense, weighing, capital, and profit/loss views. Filters use Solar Hijri input while retaining Gregorian database dates. Unfiltered purchase/sale headline totals use confirmed records and weighing headlines use finalized records; an explicit status filter changes the headlines to that status. The capital report supports a date range. Cards and tables show real totals only; there are no fabricated graphs.

## 17. Authentication

Laravel session authentication protects application routes and failed attempts are rate limited. Passwords are hashed. An active-user middleware rechecks the database on every authenticated application request; if the account becomes inactive, the session is invalidated and redirected to login. Logout also invalidates the session.

## 18. Authorization

The `role` middleware checks the authenticated user's backed-enum role on the server. Viewing and mutation are separate where needed: for example, System Administrator can view finance/capital but cannot create capital records, while General Manager and Accountant can manage them. Direct unauthorized URLs return HTTP 403.

## 19. Validation

Form Requests enforce required relationships, active records, enums, decimal shapes, positive amounts, date order, unique references, and conditional fields. Services repeat high-risk invariants inside database transactions. Browser previews help users but never replace server authority.

## 20. Security

`.env` is ignored, credentials are not documented, private reference material is ignored, and seed identities are fictional. CSRF protects form mutations. Eloquent/Query Builder parameterization avoids hand-built SQL. Uploaded tickets are MIME/size validated and private. Foreign keys, role middleware, immutable confirmation rules, and constrained deletion protect history.

## 21. Testing

The suite uses SQLite for isolated migrations and feature behavior, while final verification also runs migration/seeding against MySQL 8.4. Tests cover authentication, role denial, validation, atomic rollback, idempotency, decimal calculations, stock, weighing privacy, finance, partner balances, capital, contracts, reports, weighted-average COGS, Jalali conversion, and the exact flagship scenario.

## 22. Important formulas

- Petroleum amount: `kg / 1000 × USD per ton`.
- Weighbridge net: `first kg - second kg`.
- AFN USD equivalent: `AFN amount / AFN per USD`, rounded half-up to cents.
- Partner signed balance: opening effect + sales - purchases - receipts + supplier payments.
- Account balance: opening + receipts - payments - expenses + cash capital effects.
- Moving average cost: `(existing value + purchase value) / (existing kg + purchased kg)` for one warehouse/product stream. Events use effective business date, purchases before sales on the same date, then stable record-ID order within each type; backdated entries are recalculated in that sequence.
- COGS: final sold kilograms × current average cost per kilogram, rounded half-up to cents per sale movement.
- Operating result: confirmed sales revenue - COGS - confirmed operational expenses.

## 23. Important design decisions

- Store business dates as Gregorian database types and convert only at the application boundary.
- Keep requested and final sale quantity separate.
- Treat movement/source links as audit evidence.
- Derive balances and reports instead of synchronizing duplicate totals.
- Use one direct capital cash effect rather than a second financial record.
- Make unsupported valuation visible instead of assigning a fake zero cost.
- Keep contracts independent so they cannot destabilize verified trade workflows.

## 24. Limitations

There are no transfers, stock adjustments, opening-inventory valuation, settlement allocation, reversal workflow, double-entry general ledger, exchange gains/losses, payroll, transport profitability, document signatures, exports, notifications, or external APIs. Profit/loss is a basic operational result, not audited financial accounting. A finalized weighing or confirmed transaction cannot currently be reopened.

## 25. Future work

Future work should begin only after business rules and audit controls are approved. Candidates include controlled reversal, valued opening inventory, transfer/adjustment workflows, settlement allocation, normalized transport masters, formal accounting journals, and deployment operations. These are not hidden partial features in the current code.

## 26. Likely questions and concise answers

**Why not store current stock?** Because it can disagree with history. The movement ledger derives it and preserves traceability.

**Why is requested sale quantity not final?** Petroleum delivery is determined by accepted weighbridge net weight; requested quantity is only order context.

**How do you prevent duplicate confirmation?** Confirmation services lock rows, check status, and use unique movement source keys. Repeated confirmation becomes a no-op.

**Why use BCMath?** Binary floating point cannot reliably represent financial decimals. BCMath operates on decimal strings at explicit precision.

**Why is sales minus purchases not profit?** Some purchases remain inventory. COGS must assign acquisition cost only to quantity sold.

**How does weighted average work?** Every purchase adds quantity and value to its warehouse/product stream. A sale consumes quantity at the average value immediately before that sale.

**Can deactivating a partner hide an old debt?** No. A non-zero historical receivable or payable remains in financial positions and dashboard totals, marked inactive; deactivation still prevents selecting that partner for a new transaction.

**Can an opening balance be changed later?** Only before confirmed purchases, sales, receipts, or payments exist for that partner. Confirmed activity locks editing and deletion to protect history.

**What happens when cost evidence is missing?** The report is marked incomplete and shows unsupported/unvalued counts; zero cost is never assumed.

**Does a capital contribution count as income?** No. It increases owner capital and cash, but profit reporting excludes it.

**Why are capital partners separate from users and suppliers?** Ownership, application access, and trade relationships are different concepts with different rules.

**Why are dates Gregorian in MySQL?** Native date types preserve ordering, validation, indexing, and database compatibility. Solar Hijri is a presentation/input concern.

**How are private tickets protected?** They are stored outside the public web root and returned only through an authenticated, role-checked controller.

**Is the application production-ready?** No such claim is made. It is a verified local operational system with documented limitations; deployment still needs environment-specific operations, backup, monitoring, and acceptance.
