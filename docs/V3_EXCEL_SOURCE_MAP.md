# V3 Excel Source Map

## Audit method and privacy

The private legacy directory was parsed read-only with native `.xlsx` support. All 223 workbooks opened successfully. The audit covered 245 worksheets and inspected dimensions, non-empty cells, formulas, controlled terminology, repeated row signatures, and representative formula topology. No workbook was modified, and no real identity, filename, account detail, or transaction value is reproduced here.

The family labels below are audit classifications, not proposed application modules. A workbook can contain both primary events and derived summaries; import decisions must be made at row/block level.

## Inventory summary

| Audit family | Workbooks | Worksheets | Formula cells | Initial source assessment |
|---|---:|---:|---:|---|
| Loading/logistics | 41 | 44 | 1,298 | Mixed primary operational events and copied commercial rows |
| Vehicle-loading | 1 | 2 | 219 | Mixed operational source and reconciliation |
| Capital/partnership | 3 | 6 | 85 | Primary capital events plus derived balances/allocations |
| Settlement | 31 | 43 | 7,541 | Strong commercial reconciliation source; mixed primary and derived data |
| Storage | 17 | 18 | 354 | Primary physical movements plus derived pool totals |
| General report | 1 | 1 | 283 | Derived/verification |
| Daily journal | 1 | 1 | 894 | Mixed primary money events and derived daily reconciliation |
| Saraf | 3 | 5 | 1,263 | Primary party money movements plus running balances |
| Party ledgers | 116 | 116 | 1,572 | Verification and possible source for unmatched events/openings |
| Saraf-or-money ledgers | 4 | 4 | 193 | Mixed; classify after party resolution |
| Monthly reports | 4 | 4 | 84 | Primarily derived/verification despite ledger-like rows |
| Other small template | 1 | 1 | 17 | Quarantine until manually classified |

The explicit text evidence strongly supports Solar Hijri years 1405 and 1404, with a smaller explicit 1401 occurrence in the vehicle-loading family. A few isolated four-digit strings resemble older years but occur too sparsely and ambiguously to accept as dates. They must remain quarantined until row-level date parsing confirms them.

## Formula evidence shared across families

Repeated patterns include:

- running balance: `current = previous + direction A - direction B`;
- first-row balance: `direction A - direction B`;
- amount: `quantity × rate`, or `quantity kg × rate per ton ÷ 1,000`;
- commercial result: `sum(sale values) - sum(purchase/cost values)`;
- quantity difference: subtraction of aggregated outgoing and incoming quantities;
- last position: lookup of the last numeric balance;
- journal reconciliation: day totals plus carried opening amount;
- storage remainder: purchase/source totals minus sale/issue totals.

At least 250 exact dense-row signatures repeat across two or more source classifications in the audit pair counts. The strongest overlaps are within settlement, between settlement and unclassified ledgers, within journal blocks, and between vehicle-loading and unclassified files. This proves that identical rows can appear in several workbooks and makes occurrence-based import unsafe.

## Family maps

### Settlement (`SET-*`)

**Purpose:** Reconcile commercial source and destination lines, quantity, expenses, storage effects, partnership result, and profit/loss.

**Main columns/blocks:** purchase and sale dates/descriptions, quantity, rate, calculated amount, incoming/outgoing totals, quantity difference, profit, storage references, freight/loading/shortage notes, and partnership summaries.

**Event types:** source commitment/purchase, customer delivery/sale, typed deal expense, quantity adjustment, storage allocation, settlement approval, partnership allocation.

**Primary versus derived:** Settlement is the best source for grouping real events into a deal and recovering the office's intended commercial reconciliation. Its amount/profit/total cells are derived. Individual purchase, delivery, payment, and storage events must be matched to corroborating sources rather than imported twice.

**Duplicate relationships:** Overlaps with loading, storage, party ledgers, journal, and summaries. Repeated blocks contain many copied descriptions and values without stable identifiers.

**Import usefulness:** High for deal reconstruction and allocations; medium for individual transaction authority; high for verification of final deal result.

**Data quality risks:** free-text party references; repeated block layouts; blank preformatted rows; mixed rate bases; expenses embedded as purchase-side rows; inconsistent quantity-difference sign; formulas copied farther than populated data; possible manual overrides.

**Handling:** stage every populated block; separate inputs from formulas; assign a provisional deal fingerprint; match its legs to logistics/storage/party events; import derived totals only as comparison evidence.

### Loading/logistics (`LOAD-*`) and vehicle-loading (`TRUCK-*`)

**Purpose:** Reconcile truck/loading activity and operational cost/quantity differences.

**Main columns:** date, description, vehicle/driver snapshot, contact where present, source/destination narrative, incoming/outgoing quantity, rate, amount, freight, weighbridge, shortage, and notes.

**Event types:** loading, weighing, unloading/delivery, freight or driver payment, road/weighbridge cost, shortage/excess observation.

**Primary versus derived:** Vehicle, driver, time, weight, ticket, and operational-cost observations are candidate primary events. Purchase/sale values and balance/profit cells are generally copied or derived.

**Duplicate relationships:** Strong overlap with settlement blocks, party ledgers, storage movement descriptions, and journal payments.

**Import usefulness:** High for logistics and evidence reconstruction; medium for linking quantity; low for authoritative commercial totals.

**Data quality risks:** several meanings combined in one narrative; contact/vehicle text not normalized; loading and delivery may share a row; quantity formulas use both per-ton and per-kilogram rate styles; no universal trip identifier.

**Handling:** create staged logistics candidates and match on date window, product, quantity, vehicle/driver tokens, and related source/destination parties. Do not create a separate trade from a copied row.

### Storage (`STORE-*`)

**Purpose:** Track petroleum entering and leaving office storage and reconcile remaining quantity and unrecovered source value.

**Main columns:** date, description, purchase/source quantity, source rate/value, sale/issue quantity, issue rate/value, running balance, remaining quantity, and storage claim/result.

**Event types:** purchase into storage, remainder from a deal, truck receipt, sale/issue from storage, return, shortage/excess, and adjustment.

**Primary versus derived:** Dated physical in/out rows can be primary when corroborated and uniquely identified. Totals, stock, claim, and profit/loss are derived verification values.

**Duplicate relationships:** storage rows reappear in settlement, loading, purchase/sale records, individual ledgers, and reports.

**Import usefulness:** High for reconstructing opening and historical physical movements; high for verifying movement-derived stock; medium for valuation until cost scope is confirmed.

**Data quality risks:** storage context may be implicit; adjustment reasons are free text; source deal may be absent; one template calculates values using a different rate scale; zero-stock closing treatment is not explicit.

**Handling:** stage movement direction, quantity, product, warehouse, date, description, and candidate source. Rebuild stock from accepted movements. Store legacy claim/total only in reconciliation evidence.

### Saraf (`SARAF-*`) and Saraf-or-money ledgers (`MONEY-*`)

**Purpose:** Maintain a running position with an exchange dealer/broker or money counterparty.

**Main columns:** date, description, two directional amount columns, native currency clues, optional rate/quantity details, and running balance.

**Event types:** customer-to-Saraf receipt, Saraf-to-supplier payment, office deposit/withdrawal, party-to-party transfer, expense, exchange conversion, opening position.

**Primary versus derived:** Dated directional rows are candidate primary money events. Running balance and last-balance lookup are derived. A row that merely repeats a journal or party entry is corroboration, not another event.

**Duplicate relationships:** journal, customer/supplier ledgers, settlement notes, expense rows, and account summaries.

**Import usefulness:** Very high for Saraf party ledgers and transfer chains; high for verification of party/account positions.

**Data quality risks:** legacy headings vary; party and account concepts are mixed; currency may be inferred from sheet context; exchange rate and fee may be absent; one transfer may be described differently in two ledgers.

**Handling:** resolve the Saraf as a Party with role `SARAF`; stage each movement with a raw direction and balance; match mirror entries before normalization; preserve native currency; quarantine conversions without an explicit rate.

### Individual party ledgers (`PARTY-*`)

**Purpose:** Show a simple chronological position for customers, suppliers, and other people/businesses.

**Main columns:** date, narrative, optional petroleum quantity/rate/amount, two money directions, and running balance.

**Event types:** sale, purchase, receipt, payment, transfer, opening balance, manual adjustment, and sometimes expense/capital activity.

**Primary versus derived:** Usually a derived statement of events recorded elsewhere. It becomes a recovery source for historical opening balances or unmatched dated events only after cross-workbook matching.

**Duplicate relationships:** purchase/sale, settlement, Saraf, journal, storage, loading, and monthly/general reports.

**Import usefulness:** Highest for party identity resolution, balance verification, and locating missing events; lower priority for creating an event already found in a stronger source.

**Data quality risks:** duplicate spellings and identities; role not explicit; mixed roles in one ledger; preformatted 4,000-row templates; ambiguous direction labels; missing currency; manual balance cells; sparse entries.

**Handling:** deduplicate parties first; calculate expected balance from matched events; import a protected opening position or explicit adjustment only for an explained residual.

### Daily journal (`JOURNAL-*`)

**Purpose:** Reconcile daily money inflow/outflow, carried balance, expenses, transfers, and operational payments.

**Main blocks:** opening/carried position, paired incoming/outgoing lists, descriptions, daily totals, closing/difference cells, and repeated day sections.

**Event types:** receipts, payments, expenses, Saraf transfers, capital cash movements, and rare standalone office movements.

**Primary versus derived:** A row is primary only when no more specific source exists. Daily totals, differences, and carried balances are derived. The repeated formulas show that the journal is largely a reconciliation view.

**Duplicate relationships:** almost every Saraf, party, expense, capital, loading-payment, and trade-payment source.

**Import usefulness:** High for completeness checks and effective dates; medium as fallback event source.

**Data quality risks:** repeated day blocks, carried balances, combined descriptions, missing source links, mixed currencies, manual summaries, and possible duplicates within the same day.

**Handling:** match against staged money events first. Create a standalone staged journal event only when unmatched, then require human classification before posting.

### Capital/partnership (`PARTNER-*`)

**Purpose:** Track contributions, withdrawals, savings, expenses, and allocated commercial results by participant.

**Main columns:** date, narrative, increase, decrease, running balance; some settlement summaries contain result labels, totals, and divisor-based allocations.

**Event types:** capital contribution, withdrawal, reimbursement/expense, profit allocation, loss allocation, savings/deposit, and adjustment.

**Primary versus derived:** Cash/capital rows can be primary. Running balances and computed result shares are derived. Settlement allocation rows may be authoritative evidence of an agreed share but require scope reconstruction.

**Duplicate relationships:** settlement, journal, financial/Saraf ledgers, and general reports.

**Import usefulness:** High for historical capital position and observed result allocations; medium for defining participation terms.

**Data quality risks:** separate identity store from customer/supplier parties; free-text classification; unclear participation scope; divisor does not prove percentage terms; cash and non-cash effects mixed.

**Handling:** resolve the participant to unified Party; import typed events; link result allocation to a settlement only when the deal/scope is supported; quarantine unexplained adjustments.

### Monthly reports (`MONTH-*`)

**Purpose:** Period aggregation and, in some files, ledger-like extracts.

**Main columns:** period/date, descriptions, purchase/sale or directional amounts, balances, expenses, and totals.

**Primary versus derived:** Derived/verification by default. A ledger-looking row is not promoted to primary until no stronger source exists and the row can be proven unique.

**Duplicate relationships:** all source families.

**Import usefulness:** Reconciliation by month and detection of missing staged events.

**Data quality risks:** mixed summary/detail, copied balances, period boundaries not consistently explicit, and formulas linked to other sheets.

**Handling:** never post summary totals; compare staged event aggregates against the report and record variances.

### General report (`GENERAL-*`)

**Purpose:** Cross-workbook management summary.

**Main columns:** period totals, stock/party/Saraf positions, currency indicators, and report formulas.

**Primary versus derived:** Derived/verification only.

**Duplicate relationships:** all operational, financial, and ledger families.

**Import usefulness:** High as a final control total; none as a direct transaction source.

**Data quality risks:** cross-sheet references, manual overrides, ambiguous period, and formulas depending on workbook state.

**Handling:** capture only anonymized reconciliation outcomes during analysis; compare post-import reports to its supported totals.

### Other/quarantined template (`OTHER-*`)

**Purpose:** Not proven from structure alone.

**Handling:** quarantine. Do not infer a module or import behavior from a filename or a few formulas.

## Source hierarchy

The recommended precedence is event-specific:

1. private evidence plus a detailed operational row for weighing/logistics facts;
2. settlement block for deal membership and commercial reconciliation intent;
3. storage ledger for otherwise unique physical storage movements;
4. Saraf ledger for otherwise unique broker movements;
5. capital ledger for capital events;
6. individual party ledger for identity, opening position, and unmatched event recovery;
7. daily journal for completeness and unmatched standalone money events;
8. monthly/general reports for verification only.

This is not a blanket workbook priority. A settlement profit formula cannot override a more specific payment event, and a journal summary cannot create transactions already represented by Saraf/party rows.

## Cross-workbook matching candidates

Candidate matches should use a scored combination of:

- normalized Solar Hijri date and a configurable date window;
- event type and direction;
- product;
- quantity and normalized rate basis;
- native amount/currency and explicit exchange rate;
- normalized party identities and roles;
- vehicle/driver tokens for logistics;
- narrative tokens;
- source/destination/storage context;
- balance-before and balance-after consistency;
- exact row signature as strong evidence, never as the only key.

No single amount/date/name tuple is safe as a global unique key.

## Questions retained for import design

- `NEEDS HUMAN CONFIRMATION`: identify the few ambiguous year-like strings before claiming history earlier than 1401.
- `NEEDS HUMAN CONFIRMATION`: approve the normalized meaning of each legacy directional column family.
- `NEEDS HUMAN CONFIRMATION`: determine whether storage source-side totals consistently include logistics/shortage costs.
- `NEEDS HUMAN CONFIRMATION`: define cost allocation when multiple sources feed multiple destinations.
- `NEEDS HUMAN CONFIRMATION`: classify unmatched journal rows and the quarantined workbook before posting.
