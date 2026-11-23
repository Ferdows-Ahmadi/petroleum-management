# V3 Domain Model Proposal

## Design objective

V3 should model one commercial deal across petroleum sourcing, delivery, optional storage, logistics, money settlement, partnership, and reconciliation. It must preserve one-entry propagation and the reliable V2.1 controls while removing the assumption that every purchase first enters a warehouse and every sale consumes that warehouse independently.

This is a conceptual model. It does not authorize migrations or implementation.

## Aggregate map

```text
Party ──< PartyRole
  │
  ├──< DealSourceLine >── Deal ──< DealDestinationLine >── Party
  │                          │               │
  │                          ├──< DealAllocation >─────────┘
  │                          ├──< LogisticsMovement ──< WeighingRecord
  │                          ├──< DealExpense
  │                          ├──< DealParticipation >── Party
  │                          ├──< Settlement ──< SettlementAdjustment
  │                          └──< MoneyAllocation >── MoneyMovement
  │                                                   │
  └──< PartyLedgerEntry (derived) <───────────────────┤
                                                      ├── from Party
                                                      ├── to Party
                                                      └── optional Saraf Party

Warehouse ──< InventoryMovement >── Product
                    │
                    ├── DealSourceLine (storage out)
                    └── DealDestinationLine (storage in)

JournalEntry (derived projection of confirmed money/capital events)
```

## Core entities

### Party

One identity for a person or organization that may act in several capacities.

Suggested facts:

- display name and normalized matching name;
- optional phone/address/contact notes;
- active state;
- privacy-safe external/import identifiers;
- duplicate/merge status;
- audit timestamps.

Do not store an editable current balance. Balances are derived by currency from posted source events.

### PartyRole

Many roles per Party. Initial roles:

- `CUSTOMER`;
- `SUPPLIER`;
- `SARAF`;
- `PARTNER`.

Future operational roles can be added without creating duplicate identities. Driver/carrier identity should remain an optional Party role only when the office truly maintains the relationship; otherwise a logistics snapshot is sufficient.

### Deal

The commercial aggregate root.

Suggested facts:

- reference;
- request/agreement date and optional desired delivery date;
- product or product constraint;
- workflow status;
- primary coordinator/creator;
- commercial notes;
- version/locking metadata;
- approved settlement id;
- timestamps for activation, delivery, settlement, and closure.

A Deal owns many source lines, destination lines, allocations, expenses, logistics movements, money allocations, participations, and settlement revisions. It does not own Party balances or warehouse totals.

### DealSourceLine

A quantity/cost source. Suggested source types:

- `SUPPLIER`;
- `STORAGE`.

Suggested facts:

- deal, product, and source type;
- supplier Party or Warehouse, mutually exclusive by type;
- expected and accepted kilograms;
- rate basis, rate, currency, and calculated amount;
- commercial/effective date;
- evidence/reference;
- source status;
- notes.

The current V2.1 `Purchase`/`PurchaseItem` facts map naturally into supplier source lines, but source lines need deal membership and must not force a warehouse destination.

### DealDestinationLine

A quantity/revenue destination. Suggested destination types:

- `CUSTOMER`;
- `STORAGE`.

Suggested facts:

- deal, product, and destination type;
- customer Party or Warehouse, mutually exclusive by type;
- requested and accepted/final kilograms;
- sale rate basis, rate, currency, and calculated amount where customer-facing;
- delivery/effective date;
- status, reference, evidence, and notes.

A storage destination creates no revenue or customer receivable.

### DealAllocation

The many-to-many bridge between source and destination lines.

Suggested facts:

- source line;
- destination line;
- allocated kilograms;
- allocated source cost;
- allocation method and version;
- effective date;
- optional logistics movement;
- explanation for manual override.

Constraints:

- source and destination belong to the same deal and product;
- allocated kilograms cannot exceed accepted source/destination quantities;
- posted allocations are immutable;
- rounding residuals are explicit, not silently discarded.

`NEEDS HUMAN CONFIRMATION`: choose the default cost allocation method: physical trace, weighted pool within a deal, FIFO, or controlled manual allocation.

### LogisticsMovement

One physical trip/loading/delivery event. It may serve one or more allocations.

Suggested facts:

- deal and effective dates/times;
- origin and destination snapshots;
- carrier/vehicle/driver/contact snapshots;
- loading and unloading quantities;
- status;
- ticket/reference and protected evidence;
- notes and exception reason.

Logistics costs should normally be separate `DealExpense` rows linked to the movement. This keeps physical facts separate from money facts.

### WeighingRecord

Preserve V2.1 modes and controls:

- mode `DIRECT_NET` or `TWO_STAGE`;
- first, second, and authoritative net kilograms;
- station, ticket, vehicle, driver, time, private evidence;
- draft/finalized state and finalizer.

V3 should attach weighing to a logistics movement or destination delivery rather than only to one sale item. A movement may need more than one weighing when loading and unloading are both evidenced.

`NEEDS HUMAN CONFIRMATION`: decide whether both loading and unloading weighings are normally required or whether one accepted weighing remains sufficient unless a shortage dispute occurs.

### DealExpense

Typed operational cost linked to the deal and optionally to a source, destination, allocation, or logistics movement.

Suggested categories:

- freight;
- road;
- driver;
- weighbridge;
- handling/loading;
- shortage/loss charge;
- other, with required explanation.

Suggested money facts:

- payee Party where known;
- native amount and currency;
- actual exchange rate and converted amount when conversion occurs;
- effective date;
- settlement and payment status;
- evidence/reference.

An expense can affect deal profit before it is paid. Payment is a separate money event.

### MoneyMovement

An atomic real-world movement of money or value between parties/accounts.

Suggested types:

- customer receipt;
- supplier payment;
- Saraf transfer;
- office deposit/withdrawal with a Saraf;
- expense payment;
- capital contribution/withdrawal;
- explicit adjustment/reversal.

Suggested facts:

- from Party/account;
- to Party/account;
- optional intermediary Saraf Party;
- effective date;
- original amount/currency;
- explicit conversion rate and converted amount when applicable;
- reference, description, evidence;
- draft/posted/reversed state;
- creator/poster/audit metadata.

For a broker-mediated movement, a single business command should create balanced postings for customer/supplier and Saraf positions. Users must not enter mirror rows manually.

### MoneyAllocation

Links one MoneyMovement to one or more obligations or deals.

Suggested facts:

- movement;
- deal and optional source/destination/expense;
- allocated native amount;
- allocated settlement-currency amount where explicit conversion exists;
- allocation date and status.

This supports partial payments, one payment covering several deals, and one deal using several Sarafs.

### PartyLedgerEntry

A read model, not an ordinary editable source table. It projects posted facts into a party/currency statement:

- opening event;
- sale/customer obligation;
- purchase/supplier obligation;
- receipt/payment/transfer;
- capital/result allocation;
- explicit adjustment/reversal.

Recommended office sign:

- positive: party owes office;
- negative: office owes party.

Store provenance (`source_type`, `source_id`, posting revision) if a materialized projection is used. Never permit direct balance editing.

### DealParticipation

Represents a Party's commercial participation without assuming it covers the whole deal.

Suggested scope types:

- whole deal;
- one or more source lines;
- one or more destination lines;
- selected allocations;
- explicit quantity tranche.

Suggested contribution bases:

- contributed capital amount;
- funded source cost;
- fixed quantity;
- documented non-cash participation;
- none for profit-only agreement.

Suggested result bases:

- percentage of scoped result;
- fixed amount;
- fraction/share count;
- manual approved allocation with reason.

Suggested terms also state whether loss participates, expense basis, cap/floor, effective dates, and approval.

This flexible structure covers the observed divisor-based split and scoped settlement labels without claiming that all partnerships use one formula.

### Settlement

An immutable reconciliation snapshot/version for a Deal.

Suggested facts:

- revision and status;
- source quantity/value totals;
- delivered quantity/revenue totals;
- storage-in/storage-out totals;
- approved shortage/excess;
- petroleum cost allocation;
- expenses and monetary adjustments;
- gross margin;
- partner profit/loss allocations;
- office result;
- outstanding customer/supplier/expense money;
- prepared/approved metadata and explanation.

Only normalized source events are editable before posting. Settlement totals are calculated and saved as an audit snapshot at approval. Reopening creates a new revision; it does not overwrite the approved snapshot.

### SettlementAdjustment

An explicit non-source correction included in settlement, with type, amount/quantity, currency, reason, evidence, approver, and reversal link. This is the controlled replacement for free-text manual formula overrides.

### InventoryMovement

Keep stock movement-derived. Extend source types to cover:

- supplier purchase into storage;
- deal remainder into storage;
- storage sale/outflow to a deal;
- return;
- paired transfer in/out;
- approved shortage/excess/adjustment.

Every movement has warehouse, product, signed kilograms, effective time, source link, status, actor/approval, and cost provenance where relevant. Direct supplier-to-customer flow creates no warehouse movement.

### Product and Warehouse

Keep normalized reference data. Warehouse means a real office-controlled storage location. It must never serve as a placeholder merely to satisfy a purchase or sale schema.

### Currency and exchange rate

Continue with explicit currency codes (`USD`, `AFN`) and decimal-safe values. The actual transaction records:

- original amount;
- original currency;
- conversion rate basis;
- rate;
- converted amount/currency;
- conversion timestamp/effective date;
- rate source description if required.

There is no silent default conversion and no global historical rate lookup that rewrites posted events.

### JournalEntry

The journal is a derived projection of posted MoneyMovement, DealExpense payment, capital, and adjustment events. It may be materialized for audit/performance, but every row retains source provenance. A standalone journal source event is allowed only when no more specific domain event applies.

## Posting effects

| Posted event | Party effect | Storage effect | Deal effect | Journal effect |
|---|---|---|---|---|
| Supplier source accepted | Supplier payable | Only if destination is storage | Source cost/quantity | No cash yet |
| Customer delivery accepted | Customer receivable | Only if source is storage | Revenue/quantity | No cash yet |
| Leftover allocated to storage | None | Movement in | Reconciles quantity/cost | None |
| Customer receipt through Saraf | Customer debt decreases; Saraf position increases | None | Money allocation | Derived movement |
| Saraf payment to supplier | Supplier payable decreases; Saraf position decreases | None | Money allocation | Derived movement |
| Deal expense recognized | Payable/payee effect if applicable | None | Reduces result | Cash row only when paid |
| Partner result allocation | Partner position/capital effect | None | Reduces office result | Derived if monetary |
| Approved shortage | Optional responsible-party effect | Adjustment out | Reconciles quantity/result | Only if monetary |

## Aggregate and transaction boundaries

- Posting a destination delivery, finalized weighing, allocation, and related inventory movement must be atomic.
- Posting a broker-mediated MoneyMovement and all party/Saraf ledger effects must be atomic.
- Approving Settlement locks the exact source revision set used to calculate it.
- Party merge must preserve all source references and an audit alias trail.
- Private evidence storage and database changes must preserve V2.1's store/commit/cleanup safety.

## Current-to-target mapping

| V2.1 concept | V3 target |
|---|---|
| `BusinessPartner` + two roles | Party + extensible PartyRole |
| `Purchase`/`PurchaseItem` | Supplier DealSourceLine, with migration compatibility |
| `Sale`/`SaleItem` | Customer DealDestinationLine, with migration compatibility |
| mandatory item Warehouse | optional storage endpoint only |
| `WeighingRecord` on sale item | weighing on logistics/delivery context |
| `FinancialAccount` type `EXCHANGE` | internal account remains; real Saraf becomes Party |
| `FinancialTransaction` | MoneyMovement plus allocations/postings |
| derived `PartnerLedgerService` | generalized party/currency projection |
| separate `CapitalPartner` | Party with `PARTNER` role |
| `CapitalTransaction` | typed capital/result MoneyMovement or participation event |
| `InventoryMovement` | retained and extended with deal provenance |
| profit report over warehouse streams | deal settlement plus supported inventory costing |

## Open design decisions

- `NEEDS HUMAN CONFIRMATION`: cost allocation for many-to-many source/destination flows.
- `NEEDS HUMAN CONFIRMATION`: standardized shortage/excess sign and approval thresholds.
- `NEEDS HUMAN CONFIRMATION`: partnership loss, capital-return, and shared-expense rules.
- `NEEDS HUMAN CONFIRMATION`: exact Saraf positive/negative display wording.
- `NEEDS HUMAN CONFIRMATION`: whether payment allocations can be changed after posting or require reversal/repost.
- `NEEDS HUMAN CONFIRMATION`: whether one deal may span products; the safer initial rule is one product per deal with no hard daily limit.

## Phase 2 implementation status (2026-08-30)

Phase 2 implements the smallest central Deal aggregate: `Deal` owns one nullable Product while a draft is incomplete, and has normalized `DealSource` and `DealDestination` children. The tables permit multiple source and destination rows for later phases, while the current office workflow intentionally creates one of each. `DRAFT`, `IN_PROGRESS`, `COMPLETED`, and `CANCELLED` are the only current states.

The direct supplier-to-customer path is authoritative in these Deal tables. It does not create compatibility Purchase/Sale rows, warehouse selections, inventory movements, or payments. Completion records server-calculated source and destination amounts and projects the two commercial obligations into the existing Party ledger. The current Phase 2 completion rule requires source and destination kilograms to balance in application validation only; the schema does not impose this future-limiting equality.

`WeighingRecord` now belongs to either a legacy `SaleItem` or a `DealDestination`. Direct-net, two-stage, pending second weight, private evidence, finalization locks, and calculation behavior remain shared. Existing Purchase/Sale records stay unchanged as historical V2.1 compatibility data.

## Phase 3 implementation status (2026-08-30)

`money_movements` now stores the authoritative movement date, explicit type, commercial counterparty, optional Saraf Party, optional Deal, optional internal financial account, native amount/currency, explicit conversion rate, USD audit amount, references, notes, lifecycle, and creator/confirmer audit fields. Application validation requires one and only one route (`saraf_id` XOR `financial_account_id`).

The confirmed sign projections are explicit methods on `MoneyMovementType`: customer receipt is `-amount_usd` to the customer and `+amount_usd` to Saraf; supplier payment is `+amount_usd` to the supplier and `-amount_usd` to Saraf. Direct-account direction follows receipt in/payment out using the native amount. No balance, allocation, or journal rows are materialized.

The optional `deal_id` is the Phase 3 allocation boundary. It is immutable after confirmation and requires a completed Deal with the counterparty on the correct commercial side. Arbitrary FIFO, reallocation, reversal, FX gain/loss, and final settlement objects remain deferred.

## Phase 4 implementation status (2026-08-31)

`DealSource` and `DealDestination` now carry explicit supplier/storage and customer/storage endpoint types. Nullable Warehouse links are mutually exclusive with the Party endpoint by application validation. Existing Phase 2 rows migrate with supplier/customer defaults.

`DealAllocation` is the normalized many-to-many bridge. It stores kilograms, the exact source-specific USD cost, and automatic/manual method. Automatic allocation is limited to unambiguous one-source or one-destination cases. Confirmed Settlement history locks allocation changes until an authorized new draft revision is opened.

`DealQuantityAdjustment` stores explicit shortage/excess type, quantity, source-cost amount, reason, draft/confirmed state, and audit users. `DealExpense` stores operational category, native currency, explicit conversion, USD audit value, optional payee, and optional unique link to a confirmed paid expense.

`DealSettlement` stores numbered draft/confirmed snapshots containing quantity, source value, revenue, customer/storage cost allocation, expense, shortage, final-result totals, and source-row identifiers. Confirmed versions are immutable; recalculation uses a later revision.

`InventoryMovement` retains one-event authority and now accepts a unique Deal source or destination provenance plus cost amount. `DEAL_STORAGE_IN` and `DEAL_STORAGE_OUT` extend the existing directional enum without changing legacy purchase/sale movement behavior.
