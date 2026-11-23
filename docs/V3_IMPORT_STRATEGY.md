# V3 Historical Import Strategy

## Scope

This document plans a future private import. No real office data is imported in this phase. The eventual import must run only against a local/private office database and must never place identities, balances, source rows, exports, logs, screenshots, or credentials in Git.

The import objective is to create each real event once, reconstruct traceable deal/party/storage histories, and prove the result against legacy ledgers and summaries without importing their duplication.

## Safety principles

1. Keep source workbooks read-only and retain cryptographic file hashes.
2. Import into staging before any domain table.
3. Separate extraction, normalization, matching, review, posting, and reconciliation.
4. Make every posted record traceable to staged source evidence without storing unnecessary private workbook content.
5. Use idempotent batch identifiers and deterministic fingerprints.
6. Never infer currency conversion, party identity, direction, or accounting classification when evidence is insufficient.
7. Quarantine ambiguous rows rather than forcing them into a convenient model.
8. Make rollback batch-based and test it before production import.

## Source priority by fact

| Fact | Preferred source | Corroboration |
|---|---|---|
| Deal grouping and final reconciliation | Settlement block | loading, storage, party ledgers, reports |
| Loading/delivery/vehicle/weighing | Loading and vehicle-loading detail | settlement, ticket evidence, storage |
| Storage physical movement | Storage detail or linked accepted deal event | settlement, loading, purchase/sale, stock totals |
| Saraf movement | Saraf detail ledger | party ledger, journal, settlement |
| Customer/supplier obligation | accepted deal source/destination | party ledger, settlement |
| Customer receipt/supplier payment | specific Saraf or detailed money event | party ledger, journal |
| Capital movement | capital/withdrawal detail | journal, Saraf/account ledger |
| Opening party position | earliest reliable party ledger | later running balance and summaries |
| Daily closing control | journal | financial/Saraf movements |
| Monthly/global control | monthly/general reports | all normalized aggregates |

Priority is per fact, not per workbook. A workbook's derived formulas never outrank a more specific real event.

## Proposed private staging area

Staging tables or equivalent private import structures should include:

### `import_batches`

- batch UUID, source-set hash, importer version;
- started/completed/rolled-back status;
- private operator and environment metadata;
- counts and reconciliation outcomes;
- no credentials.

### `import_files`

- batch, private path token, cryptographic hash, workbook family;
- sheet count and parser status;
- source year hints and quarantine state.

Store a non-identifying token or encrypted/private path reference, not a committed path/name.

### `import_rows`

- file/sheet/row/block coordinates;
- normalized cell types and controlled field extraction;
- source formula and cached result where needed for verification;
- row fingerprint;
- parse warnings;
- encrypted/private raw payload only if operationally necessary.

### `import_event_candidates`

- proposed event type and direction;
- normalized date, party tokens, product, quantity, rate basis, amount, currency;
- candidate deal/storage/logistics context;
- source row links and confidence;
- classification/review state.

### `import_party_candidates`

- normalized identity/contact tokens;
- observed roles and aliases;
- links to source occurrences;
- proposed Party match;
- merge confidence and reviewer decision.

### `import_match_groups`

- candidate occurrences believed to represent one real event;
- score components and selected authoritative occurrence;
- conflict details;
- reviewer/decision history.

### `import_reconciliations`

- source control type/period/currency/product/party token;
- legacy supported total;
- imported computed total;
- difference and status;
- explanation/approval.

All staging structures should live in the private database or encrypted private storage. They are not seed data.

## Pipeline

### Stage 0: freeze and fingerprint

1. Copy the source set through an approved private backup process if needed; leave the originals untouched.
2. Hash each workbook and record size/modified time privately.
3. Confirm the set has not changed during extraction.
4. Assign anonymized file/sheet aliases for review output.

### Stage 1: extract without interpretation

Extract:

- workbook/sheet coordinates;
- cell type, formula, cached result, and displayed number/date format;
- merged-cell and repeated-block structure;
- normalized text tokens;
- hidden-row/sheet flags and parse warnings.

Do not evaluate a formula as a source event. Preserve both input cells and derived formula evidence.

### Stage 2: normalize

Normalize:

- Persian/Arabic character variants and whitespace;
- Dari/Western digits;
- Solar Hijri dates to validated Gregorian canonical dates while preserving original text;
- kilograms and metric-ton rate basis;
- decimal strings without binary float;
- explicit currencies and rate basis;
- controlled event/direction vocabulary;
- phone/name tokens for private identity matching.

Reject impossible dates, negative quantities without a supported adjustment type, non-positive conversion rates, and amounts outside target precision.

### Stage 3: resolve parties

Party matching should combine:

- exact normalized phone when present;
- exact normalized name plus corroborating role/activity;
- known private aliases;
- address/contact evidence;
- ledger mirror relationships;
- reviewer-approved merges.

Never auto-merge solely because two names are similar. Preserve aliases and an audit trail. A single Party can receive multiple roles.

### Stage 4: form event candidates

Parse workbook-specific blocks into candidates:

- commercial source/destination;
- logistics/weighing;
- storage movement;
- money/Saraf transfer;
- expense;
- capital/partnership;
- opening position;
- summary/control total.

Each candidate retains links to every source occurrence and distinguishes entered input from formula-derived values.

### Stage 5: deduplicate and match

Use scored matching, not a single key.

Suggested score components:

- exact row fingerprint;
- date equality or supported date window;
- same normalized Party/role;
- same product and quantity;
- same native amount/currency;
- equivalent normalized rate basis;
- vehicle/driver tokens;
- narrative token overlap;
- source/destination/storage relationship;
- running-balance compatibility;
- mirror direction in another ledger.

Classify each group:

- exact duplicate;
- mirror posting of one transfer;
- summary/reference occurrence;
- probable same event requiring review;
- separate events;
- unresolved/quarantined.

The observed exact duplicate signatures across settlement, loading, journal, and unclassified ledgers make this step mandatory.

### Stage 6: reconstruct deals

Use settlement blocks as the primary grouping hypothesis, then attach:

- source and destination candidates;
- allocations;
- logistics/weighing candidates;
- storage movements;
- expenses;
- Saraf/customer/supplier money movements;
- partnership allocations.

Where no reliable deal grouping exists, import the valid standalone source event and leave the deal link unresolved rather than inventing one.

`NEEDS HUMAN CONFIRMATION`: define the acceptable date/quantity/amount tolerance for automatic deal matching.

### Stage 7: dry-run posting

Build a deterministic posting plan without writing domain tables. Validate:

- all foreign references resolve;
- source/destination allocations balance quantity or carry approved variance;
- no storage movement creates an unexplained negative historical balance;
- native currency and conversion fields are complete;
- party/Saraf mirror postings balance;
- settlement recomputation matches supported inputs;
- all private evidence paths are resolvable and authorized.

Produce counts and anonymized variances only.

### Stage 8: post in dependency order

Recommended order:

1. products, warehouses, and users/reference data;
2. unified Parties, roles, aliases, and reviewed merges;
3. protected opening events at an approved cutoff;
4. Deals and commercial source/destination lines;
5. logistics and weighing records;
6. allocations and storage movements;
7. expenses and money/Saraf movements;
8. partnership participation and result allocations;
9. settlement snapshots;
10. derived ledger/journal/report projections.

Use database transactions in bounded batches. Each domain record retains `import_batch_id` and a private source-provenance reference.

### Stage 9: reconcile

Recompute from posted events:

- quantity by product/warehouse/date;
- deal source, destination, storage, shortage, and result totals;
- customer, supplier, Saraf, and partner positions by currency;
- daily journal opening/movement/closing;
- capital balances;
- monthly and general supported totals.

Compare in layers:

1. row/event counts;
2. exact event values;
3. running ledger balances;
4. deal settlement totals;
5. daily/monthly/global control totals.

Every difference must be zero, explained, or explicitly approved and retained in `import_reconciliations`.

### Stage 10: accept and seal

After review:

- lock the batch;
- save signed/hashed reconciliation evidence privately;
- prohibit silent reruns;
- run application regression and privacy checks;
- retain source hashes and rollback metadata.

## Year handling

- The audited text evidence clearly supports 1405 and 1404, and a small 1401 presence in vehicle-loading.
- Workbook/folder labels are hints, not row dates.
- A sheet may contain carried openings or prior-period rows.
- Parse each date with strict Solar Hijri validation; preserve original text.
- Import chronologically so opening and running balances can be checked.
- If the earliest reliable transaction history is incomplete, choose an approved cutoff and import a protected opening event rather than fabricating earlier transactions.
- Quarantine isolated year-like strings until human review proves they are dates.

`NEEDS HUMAN CONFIRMATION`: approve historical cutoff dates by ledger and resolve whether any source set earlier than 1401 is complete enough for event import.

## Currency handling

- Record original amount/currency for every candidate.
- Do not infer currency only from a folder or workbook family when row/sheet context conflicts.
- Normalize an explicit rate to a declared basis such as `AFN per USD`.
- Preserve both original and converted amounts only when conversion is evidenced.
- Do not combine USD and AFN ledger controls into one total without recorded conversions.
- Quarantine conversions with missing, zero, or ambiguous rates.
- Use decimal arithmetic and documented rounding identical to the target application.

## Formula and balance handling

- Formulas are verification evidence unless they represent a clearly intended calculated field.
- Recalculate amount, running balance, settlement result, and storage claim from normalized inputs.
- Compare recalculated values to workbook cached/formula results.
- Record formula errors, overwritten formulas, and unexplained differences.
- Never import a displayed running balance as an editable balance.
- A reviewed opening balance is an event with date, currency, direction, source, and approval.

## Storage import rules

- Import source-linked movements, not stock totals.
- Derive stock chronologically using effective business dates.
- Use storage totals and claim/result cells only as controls.
- Keep cost provenance from supplier source or accepted pool valuation.
- Quarantine a sale/outflow that would make stock negative until its missing inflow/opening is resolved.
- Import shortage/excess only with a typed reason and approval.

## Saraf and transfer import rules

- Resolve each Saraf as a Party, not only as a FinancialAccount.
- Detect mirror entries across customer/supplier/Saraf/journal ledgers.
- Create one MoneyMovement with balanced postings and source-occurrence links.
- Keep partial payments separate by effective date.
- Allow one movement to allocate to multiple deals and one deal to receive multiple movements.
- Validate that recomputed Saraf running position matches the ledger at checkpoints.

## Partnership import rules

- Merge capital-partner identity into unified Party after review.
- Distinguish cash contribution/withdrawal from non-cash profit/loss allocation.
- Preserve observed scope and formula evidence.
- Do not infer a percentage from a one-time divisor unless the agreement is corroborated.
- Quarantine allocations whose deal, scope, or loss treatment is unclear.

## Rollback and recovery

- Every inserted domain row must be attributable to one import batch.
- Prefer posting into a disposable clone first.
- Before final posting, create and verify a private database backup.
- Rollback deletes/reverses only the selected batch in reverse dependency order and never touches later manual records.
- If posted records have acquired later manual references, rollback must stop and require a controlled reversal plan.
- Re-running the same source-set hash and importer version must be idempotent.
- Test both successful rollback and interrupted-batch recovery.

## Privacy controls

- Real source files remain ignored and outside packages.
- Import logs use aliases, counts, hashes, and masked values.
- No raw rows in test fixtures; generate fictional structurally equivalent cases.
- Screenshots and review exports use anonymized data unless kept in approved private storage.
- Credentials remain only in environment/secret storage.
- Access to evidence and staging review is authenticated, role-restricted, and audited.
- Temporary extracts are deleted through an approved private-data cleanup process after acceptance.

## Acceptance gates for the future import

- all source files hash-verified and parse outcome recorded;
- all Parties reviewed above the auto-match risk threshold;
- zero unexplained duplicate postings;
- storage never depends on editable totals;
- no silent currency conversion;
- all posted events have source provenance;
- deal and party/Saraf reconciliations pass or have approved variances;
- monthly/general controls agree within documented scope;
- rollback rehearsal passes;
- application tests, privacy scan, and role/evidence checks pass;
- Git contains no private workbook, raw extract, credentials, or real-data fixture.

## Remaining import decisions

- `NEEDS HUMAN CONFIRMATION`: date and numeric tolerances for automatic matching.
- `NEEDS HUMAN CONFIRMATION`: earliest trustworthy cutoff by ledger.
- `NEEDS HUMAN CONFIRMATION`: normalized legacy direction mapping per family.
- `NEEDS HUMAN CONFIRMATION`: review authority for party merges and reconciliation variances.
- `NEEDS HUMAN CONFIRMATION`: retention period and encryption approach for raw staging payloads.
