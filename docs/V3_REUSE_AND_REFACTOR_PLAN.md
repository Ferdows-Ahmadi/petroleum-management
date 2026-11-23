# V3 Reuse and Refactor Plan

## Scope

This plan classifies V2.1 components against the confirmed deal-centered office workflow. It is an implementation guide for a later phase. No code is changed here.

Classification meanings:

- **KEEP:** retain architecture and behavior; adapt naming/integration only where necessary.
- **REFACTOR:** preserve useful logic but change boundaries, relationships, or API.
- **MERGE:** combine duplicated identities/workflows into a unified concept.
- **REPLACE:** introduce a new domain abstraction; migrate compatibility behavior deliberately.
- **DEPRECATE:** stop presenting or extending the component as a primary workflow, then remove only after migration and verification.

## System classification

| Current V2.1 subsystem | Decision | Reason and target direction |
|---|---|---|
| Authentication/session controls | KEEP | Proven login/logout and protected-route behavior remains foundational. |
| User roles and route middleware | KEEP | Server-side RBAC and active-user enforcement remain authoritative; add V3 permissions around Deal/settlement/import later. |
| Active-user middleware | KEEP | Prevents inactive sessions from continuing access. |
| Private evidence service and protected serving | KEEP | Safe private storage, failure cleanup, immutable finalized evidence, and role checks directly support Deal bills/tickets. Generalize source attachment carefully. |
| Jalali date adapter/request conversion | KEEP | Matches office usage while retaining canonical database dates. Extend to every new effective-date field. |
| Decimal-safe amount calculator and BCMath use | KEEP | Correct petroleum and money precision is essential. Reuse in Deal line and settlement calculators. |
| Number formatting and Dari translations | KEEP | Reuse the established RTL/Dari presentation conventions and clear business wording. |
| V2.1 visual design system, logo, layout primitives | KEEP | Preserve approved visual coherence; future information architecture changes should reuse the same language rather than redesigning the brand. |
| Products | KEEP | Product reference data remains valid. Consider one-product-per-deal initially, subject to confirmation. |
| Warehouses | KEEP | Real storage locations remain valid, but they cease being mandatory trade intermediaries. |
| Inventory movement-derived stock | KEEP + REFACTOR | Keep immutable source-linked movements and negative-stock controls. Add deal/storage allocation provenance, effective event time, transfers, and approved adjustments. |
| Inventory balance service | KEEP + REFACTOR | Aggregation principle is correct; extend movement types and historical effective-date behavior. |
| Weighted-average valuation | KEEP + REFACTOR | Useful for supported storage pools. It must coexist with direct-flow Deal cost allocation and explicitly report unsupported provenance. |
| Direct-net and two-stage weighing logic | KEEP + REFACTOR | Modes, net authority, finalization, and decimal checks are proven. Relink weighing from only `SaleItem` to Deal logistics/delivery context and allow multiple events where required. |
| Weighbridge/private bill workflow | KEEP | Preserve private evidence and immutability. Add source/destination/logistics context without weakening access control. |
| Unified `BusinessPartner` identity | REFACTOR | Good foundation, but rename/generalize conceptually to Party and extend roles to `SARAF` and `PARTNER`. Add aliases/merge history and currency-separated statements. |
| `BusinessPartnerRole` | REFACTOR | Keep normalized many-role structure; expand role enum and permission-neutral identity behavior. |
| Inline partner resolution | KEEP + REFACTOR | Fast creation/reuse is valuable. Extend role support and introduce safer duplicate detection/merge review. |
| Separate `CapitalPartner` identity | MERGE + DEPRECATE | A partner may also be customer/supplier/Saraf. Migrate into Party with `PARTNER` role, preserve historical references, then retire duplicate identity management. |
| `CapitalTransaction` and balance service | REFACTOR + MERGE | Preserve typed contribution/withdrawal/result events and derived balance. Integrate with unified Party, MoneyMovement, DealParticipation, and settlement allocations. |
| `Purchase` and `PurchaseItem` as independent aggregate | REPLACE as primary model | Facts remain useful, but mandatory warehouse and isolation from customer delivery cannot represent direct/many-to-many deals. Migrate to DealSourceLine; retain compatibility readers during transition. |
| `Sale` and `SaleItem` as independent aggregate | REPLACE as primary model | Facts remain useful, but independent sale cannot express source allocations and complete reconciliation. Migrate to DealDestinationLine with weighing/logistics links. |
| `TradeDraftService` | REFACTOR | Reuse validation/atomic-draft patterns inside a Deal workflow service. It should create/edit source and destination lines, not unrelated headers. |
| `TradeConfirmationService` | REFACTOR | Preserve locks, idempotency, status checks, amount recalculation, and stock protection. Split posting by direct delivery versus storage endpoint and Deal state. |
| `SimpleOfficeTransactionService` | REPLACE orchestration | The one-purchase/one-sale form orchestration becomes DealWorkflow orchestration with a fast simple path and optional advanced sections. |
| Separate purchase/sale controllers and primary forms | DEPRECATE as daily workflow | Keep temporarily for compatibility/audit. New work should move to the Deal workspace after migration gates pass. |
| Partner ledger service | REFACTOR | Current signed derived ledger is a strong base. Generalize source types, currencies, Saraf transfers, Deal obligations, capital/result allocations, and provenance. |
| Partner opening balance | KEEP + REFACTOR | Preserve as protected event, not editable balance. Extend by currency and import cutoff with approval/audit. |
| Financial accounts for internal cash/bank | KEEP | Internal office custody accounts remain distinct from external parties. Preserve native currency and derived balances. |
| `FinancialAccountType::EXCHANGE` representing a Saraf | REPLACE + DEPRECATE | A real Saraf becomes Party with a ledger. Keep an internal account only when it represents office-held cash/bank custody, not the Saraf identity. |
| Financial transaction service | REFACTOR | Preserve drafts, confirmation immutability, account locking, explicit conversion, and decimal safety. Expand to balanced MoneyMovement and allocations across parties/deals. |
| Payment received/paid types | REFACTOR | Human intents remain, but postings need from/to/intermediary Party, allocation, and multi-deal/multi-Saraf support. |
| Expense infrastructure | KEEP + REFACTOR | Preserve typed expense and payment behavior. Add recognition versus payment, Deal/logistics scope, payee Party, and allocation. |
| Currency conversion service | KEEP + REFACTOR | Explicit positive rate and no silent conversion are correct. Make rate basis and both currencies explicit; do not retroactively revalue. |
| Financial account balance service | KEEP + REFACTOR | Continue source-derived balances for internal accounts; separate Party/Saraf position projection. |
| Daily journal service/view | KEEP + REFACTOR | Derived journal is correct. Project new money/capital events and allow only genuinely standalone source events. Promote Journal to top-level navigation. |
| Current `Finance` tab workspace | MERGE + REPLACE navigation | Distribute human work into Party Accounts, Journal, Deal Money, and Settings for internal accounts; avoid one technical finance bucket. |
| Storage workspace | KEEP + REFACTOR | Preserve source-linked movements and no manual stock. Add optional Deal source/destination context and storage claim/result explanation. |
| Profit/loss report over inventory streams | REFACTOR | Keep supported weighted-average reporting; add Deal settlement results, direct-flow cost, expenses, partnership, and currency scope. |
| Dashboard | REFACTOR | Shift from separate purchase/sale/account summaries to active Deal exceptions, party/Saraf positions, storage, and required actions. Preserve role-aware privacy. |
| Reports center | KEEP + REFACTOR | Retain one report entry, filters, source-backed totals, and incomplete-valuation warnings. Reorganize by business questions and add Deal/settlement/logistics/import reconciliation. |
| Contracts module | DEPRECATE from primary V3 workflow | The audited workbook evidence does not establish contracts as a core daily step. Preserve existing data/read access until the owner confirms its future use. |
| Detailed CRUD/audit routes | KEEP as compatibility, DEPRECATE as entry points | Useful during migration and support. Ordinary users should work from Deal, Party, Journal, and Storage contexts. |
| Controllers' inline role checks plus route middleware | REFACTOR | Preserve route middleware; centralize V3 policies/abilities to avoid scattered workflow decisions. |
| Current migrations/data | KEEP for migration source | Never rewrite protected history. Add forward migrations in the implementation phase and migrate in controlled steps. |
| Feature/unit test infrastructure | KEEP | Existing role, trade, weighing, finance, inventory, report, privacy, and browser-found regression coverage is valuable. Add V3 tests before retiring compatibility paths. |
| Fictional deterministic seed | KEEP + REFACTOR | Continue fictional-only data. Expand with a Deal scenario; never use legacy identities or values. |
| Build/release/privacy gates | KEEP | Retain clean migrations, tests, build, browser workflows, private-path checks, prohibited-wording scan, and tag immutability verification. |

## Reusable invariants

The later implementation should carry these V2.1 invariants forward unchanged in spirit:

- active authenticated user required;
- role authorization enforced on the server;
- exact decimal calculations;
- final weight is server-authoritative;
- direct-net never fabricates first/second weights;
- two-stage net is derived only when complete;
- private evidence is never a public asset path;
- finalized/confirmed records are immutable;
- idempotent confirmation and row locking;
- no storage outflow beyond available stock;
- inventory and ledger balances derive from events;
- no silent currency conversion;
- filters and reports make incomplete evidence visible;
- tests and seed data remain fictional.

## Target service boundaries

Suggested later service responsibilities:

| Service concept | Responsibility |
|---|---|
| `DealWorkflowService` | Create/update Deal and simple-path source/destination data; coordinate states. |
| `DealPostingService` | Atomically post accepted commercial/delivery events and their obligations. |
| `AllocationService` | Validate source-to-destination quantity/cost allocation and rounding. |
| `LogisticsService` | Manage movement, weight, evidence, shortage/excess observation. |
| `SettlementService` | Calculate, validate, version, and approve Deal settlement. |
| `MoneyMovementService` | Post balanced customer/supplier/Saraf/internal-account effects. |
| `MoneyAllocationService` | Allocate installments across deals/obligations. |
| `PartyLedgerService` | Project Party positions by currency and source. |
| `InventoryService` | Post and report source-linked warehouse movements. |
| `PartnershipService` | Validate scoped terms and calculate approved allocations. |

Do not create one oversized service that combines all of these transactions without explicit boundaries.

## Compatibility and migration approach

1. Add the new model forward; do not rewrite V2.1 migrations or tags.
2. Introduce unified Party roles/aliases while preserving existing partner ids through mapping.
3. Build Deal aggregate and a compatibility adapter for existing purchase/sale records.
4. Implement the direct one-source/one-customer path using preserved weighing/security invariants.
5. Add allocations and optional storage endpoints.
6. Add money/Saraf balanced posting and generalized Party ledger.
7. Add expenses, partnership, and versioned settlement.
8. Move navigation/workflows only after backend equivalence and browser evidence pass.
9. Migrate historical/private data through the staged strategy.
10. Retire old creation paths only after reconciliation and rollback acceptance.

## Recommended Phase 2 implementation sequence

Phase 2 should itself be split into bounded, reviewable checkpoints:

1. **Foundation:** Party role expansion/alias design, Deal schema, source/destination lines, statuses, policies, and tests.
2. **Simple direct deal:** one supplier, one customer, no storage, direct/two-stage weighing, calculated amounts, and private evidence.
3. **Many-to-many and storage:** allocations, multiple sources/destinations, optional storage movements, and quantity reconciliation.
4. **Money and Saraf:** balanced MoneyMovement, installments, multi-Saraf, Deal allocations, Party ledger, and derived Journal.
5. **Expenses and settlement:** typed Deal expenses, settlement calculator/revisions, shortage/excess, and approval.
6. **Partnership:** scoped participation and result allocation after business rules are confirmed.
7. **UI transition and reports:** Deal-centered workspace/navigation and reporting with compatibility paths retained.
8. **Private import tooling:** staging, matching, reconciliation, rollback, and local acceptance; no real data in Git.
9. **Retirement:** deprecate old creation flows only after parity, browser, migration, and reconciliation gates.

Do not begin partnership calculation or historical import until their `NEEDS HUMAN CONFIRMATION` items are resolved.

## Decisions required before implementation reaches those areas

- `NEEDS HUMAN CONFIRMATION`: many-to-many petroleum cost allocation method.
- `NEEDS HUMAN CONFIRMATION`: shortage/excess sign, responsibility, and approval threshold.
- `NEEDS HUMAN CONFIRMATION`: partnership loss/capital/expense-ordering rules.
- `NEEDS HUMAN CONFIRMATION`: Saraf position wording and any fee/exchange spread treatment.
- `NEEDS HUMAN CONFIRMATION`: payment reallocation versus reversal behavior.
- `NEEDS HUMAN CONFIRMATION`: contracts module retention.
- `NEEDS HUMAN CONFIRMATION`: one product per Deal versus multi-product Deal.

## Phase 2 implementation status (2026-08-30)

- Reused `BusinessPartner` and its normalized role-assignment table; added `SARAF` and `PARTNER`, with a defensive literal-`BOTH` normalization migration that preserves Party IDs.
- Added Deal-owned source/destination authority rather than duplicating new values into Purchase/Sale. The legacy trade and inventory services remain intact for V2.1 history and storage workflows.
- Refactored `WeighingService` only at the association boundary so its proven calculation, locking, pending, immutability, and validation rules work for either legacy Sale items or Deal destinations.
- Reused private local-disk evidence storage and authorized streaming. New Deal evidence never receives a public asset path.
- Extended `PartnerLedgerService` with completed Deal source/destination projections. No payment or settlement event is inferred from a commercial obligation.
- Deferred multi-source UI, multi-destination UI, cost allocation, optional storage endpoints, shortage/excess, Saraf money movement, partnership, settlement, import, and legacy-creation retirement.

## Phase 3 implementation status (2026-08-30)

- Added `MoneyMovement` for all new office receipt/payment work rather than duplicating it into legacy `FinancialTransaction`. Existing confirmed finance rows remain readable for compatibility; expenses continue to use the proven legacy service.
- Reused Party identity and normalized roles for Saraf. No Saraf identity or generic exchange-account alias table was created.
- Extended `PartnerLedgerService`, `FinancialAccountBalanceService`, and `DailyJournalService` as projections. Added focused `SarafLedgerService` and `DealMoneySummaryService`; none writes duplicate ledger or journal data.
- Reused decimal conversion and audit conventions, adding explicit role, Deal-side, route, currency, lifecycle, and lock validation in `MoneyMovementService`.
- Kept legacy Purchase/Sale, inventory, capital, expenses, and account-management flows intact. Final settlement, many-to-many allocation, storage reconciliation, partnership, reversal/reallocation, and import remain outside Phase 3.

## Phase 4 implementation status (2026-08-31)

- Added normalized Deal allocation and Settlement authorities without replacing or duplicating Phase 3 MoneyMovement.
- Extended source/destination endpoints and inventory provenance forward; legacy Purchase/Sale and their storage movements remain readable and continue through their tested services.
- Reused the petroleum calculator, explicit conversion service, movement-derived balance, weighted-average costing, Party obligations, immutable confirmation, row locking, and Dari RTL presentation patterns.
- Added focused `DealAllocationService`, `DealCommercialRecordService`, `DealSettlementService`, `StorageCostService`, and `StorageClaimService` boundaries rather than expanding the existing Deal or finance services into one orchestration object.
- Partnership result sharing, historical workbook import, payment reversal/reallocation, and unrelated modules remain deferred.

## Phase 5 UX reuse and consolidation (2026-08-31)

- Reused `InlinePartnerService` as the sole inline Party identity/role boundary and added a small JSON adapter plus one keyboard-accessible Blade/JavaScript combobox. Existing Party IDs and ledgers are preserved; exact names reuse the existing identity and missing roles are added atomically.
- Reused `DealService`, `MoneyMovementService`, `FinancialTransactionService`, `PartnerLedgerService`, `SarafLedgerService`, `DailyJournalService`, inventory services, and settlement services without introducing a parallel write model.
- Kept legacy Finance, payment, expense, and financial-account routes for bookmark compatibility. Finance tabs now redirect to Daily Journal, Settings/financial accounts, or capital records rather than maintaining duplicate top-level navigation.
- Replaced page-specific visual choices with one CSS token and component layer while retaining server-rendered Blade and small progressive JavaScript. No frontend framework, database migration, or new business module was introduced.
- Partnership expansion, historical import, payment reversal/reallocation, fleet work, contracts redesign, and new accounting/reporting domains remain deferred.
