# V3 Phase 3 Implementation Checklist

Phase 3 adds the confirmed office money workflow without changing commercial Deal completion or beginning final settlement.

## Checkpoint and scope

- [x] Start `v3-phase3-money-journal` from `v3-phase2-deal-foundation-ready`.
- [x] Verify the protected Phase 2 target and clean baseline.
- [x] Read the V3 planning set and Phase 2 handoff.
- [x] Inspect Party roles, Deal, legacy finance, account balances, expenses, journal, navigation, and authorization.
- [x] Confirm the `216 tests / 849 assertions` baseline.
- [x] Keep all protected tags unchanged and do not merge to `main`.
- [x] Keep final settlement, payment reallocation/reversal, partnership allocation, advanced storage, and import outside this phase.

## Authoritative money event

- [x] Add one `MoneyMovement` authority for new customer receipts and supplier payments.
- [x] Keep legacy `FinancialTransaction` records compatible; do not duplicate a new movement into that table.
- [x] Support draft and confirmed states with immutable confirmed records.
- [x] Store explicit type, date, amount, currency, optional explicit exchange rate, counterparty, optional Saraf, optional Deal, optional internal account, reference, notes, and audit users/times.
- [x] Require exactly one money route: Saraf or internal financial account.
- [x] Validate Party roles, active records, Deal completion, and selected Party/Deal relationship.
- [x] Confirm atomically with database row locks.
- [x] Allow signed Party balances to represent overpayments and prepayments.

## Derived projections

- [x] Project customer receipt effects into the customer ledger and selected Saraf/account position.
- [x] Project supplier payment effects in the opposite Saraf direction.
- [x] Support partial payments and different Sarafs across installments.
- [x] Add MoneyMovement entries to Party ledgers without duplicate rows.
- [x] Add direct MoneyMovement effects to internal financial-account balances.
- [x] Derive Saraf running statements from movements where that Party is the Saraf.
- [x] Derive Daily Journal entries from MoneyMovement plus supported legacy finance, expenses, and capital.
- [x] Add Deal obligation, paid/received, remaining, settlement indicator, and related-movement summary.
- [x] Verify Deal completion itself never creates a money event.

## UI and authorization

- [x] Add contextual receive/pay actions to Accounts and Deal views for General Manager and Accountant only.
- [x] Adapt one Party profile for customer, supplier, multi-role, and Saraf views.
- [x] Show Saraf identity, office position, chronology, related commercial Party, and Deal.
- [x] Keep Journal date-filtered, concise, tabular, and derived.
- [x] Move navigation toward Dashboard, Deals, Accounts, Daily Journal, Storage, Reports, Settings.
- [x] Preserve finance read access for General Manager, Accountant, and read-only System Admin.
- [x] Keep Purchase/Sales Officer from unrestricted money control and Saraf detail.
- [x] Keep Warehouse and Transport roles out of financial details.
- [x] Preserve Dari RTL, responsive layout, and the approved visual language.

## Tests and release gates

- [x] Cover Saraf role/multi-role/profile/security.
- [x] Cover canonical customer receipt, supplier payment, partial, multi-Saraf, direct account, USD, AFN, and explicit conversion.
- [x] Cover no fabricated Deal/source/destination/purchase/sale/inventory rows.
- [x] Cover journal uniqueness/totals/running balances and Deal money summary.
- [x] Cover immutability and all six roles.
- [x] Run full PHPUnit, Pint, Blade compilation, Vite production build, Composer strict validation, and PHP syntax.
- [x] Run MySQL 8.4 upgrade, migration status, Phase 3 rollback/reapply, and clean disposable migration/seed.
- [x] Run desktop `1366x768` and mobile `390x844` browser workflows with no console warnings/errors.
- [x] Run privacy, credential, prohibited-wording, private-Excel-ignore, and Git-diff checks.
- [x] Commit final documentation and evidence with a clean worktree.
- [x] Create annotated `v3-phase3-money-journal-ready` only after every required gate passes.
