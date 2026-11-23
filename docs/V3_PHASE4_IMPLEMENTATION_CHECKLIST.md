# V3 Phase 4 Implementation Checklist

Phase 4 adds commercial settlement and storage reconciliation without changing Phase 3 money authority or beginning partnership/import work.

## Checkpoint and boundaries

- [x] Start `v3-phase4-settlement-storage` from `v3-phase3-money-journal-ready`.
- [x] Verify the protected Phase 3 target, clean baseline, PHP 8.5, MySQL 8.4, Node/npm, Git, and the `229 tests / 912 assertions` baseline.
- [x] Read the V3 planning set and Phase 2/3 implementation handoffs without repeating the private workbook audit.
- [x] Keep protected tags unchanged and do not merge to `main`.
- [x] Keep partnership, historical import, payment reversal/reallocation, fleet, and contract redesign outside Phase 4.

## Commercial reconciliation authority

- [x] Add explicit supplier/storage source and customer/storage destination types.
- [x] Add source-to-destination quantity allocations with source-specific allocated cost.
- [x] Auto-allocate one-source or one-destination cases and require explicit allocation for many-to-many ambiguity.
- [x] Add explicit shortage/excess records with draft/confirmed lifecycle and source-cost valuation.
- [x] Add Deal expenses with native currency, explicit conversion, payment link, and no fabricated money event.
- [x] Add draft/confirmed versioned Settlement snapshots with immutable confirmed revisions.
- [x] Keep commercial completion independent from customer/supplier payment status.

## Storage and reporting

- [x] Project confirmed storage destinations to one costed inventory inflow.
- [x] Allow storage as an explicit later Deal source and project one costed outflow.
- [x] Preserve movement-derived stock and integrate Phase 4 movements with weighted-average valuation.
- [x] Derive storage claim/result from source and issue values; never store an editable claim balance.
- [x] Add a consolidated settlement report using confirmed revisions for headline profit totals.
- [x] Keep Deal commercial and financial settlement summaries visibly separate.

## Verification and release

- [x] Cover canonical simple, storage/shortage, multi-customer, multi-source, many-to-many, later storage source, expense, claim-sign, versioning, and six-role cases.
- [x] Upgrade and seed the working MySQL schema twice idempotently.
- [x] Run clean disposable MySQL migration/seed and safe Phase 4 rollback/reapply.
- [x] Complete full automated/build/syntax gates after final edits.
- [x] Complete desktop/mobile browser workflows and console/overflow checks.
- [x] Complete privacy, credential, private-source-ignore, wording, and Git-diff checks.
- [x] Commit a clean final tree and create `v3-phase4-settlement-storage-ready` only after every required gate passes.
