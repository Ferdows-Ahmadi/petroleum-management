# V3 Phase 2 Implementation Checklist

## Checkpoint and boundaries

- [x] Start `v3-phase2-deal-foundation` from planning HEAD `5949a573eda70a0da2fc35687a2d598e00d0624b`.
- [x] Read all six V3 planning documents before implementation.
- [x] Inspect the V2.1 Party, trade, product, warehouse, weighing, inventory, ledger, authorization, route, and test architecture.
- [x] Do not repeat the private workbook audit.
- [x] Keep the protected V2/V2.1 tags unchanged.
- [x] Keep Saraf transfers, settlement, partnership allocation, advanced allocations, storage automation, and Excel import out of Phase 2.

## Unified Party foundation

- [x] Extend normalized Party roles with `SARAF` and `PARTNER`.
- [x] Normalize any literal legacy `BOTH` assignment to `CUSTOMER` + `SUPPLIER` without changing Party IDs.
- [x] Preserve existing customer/supplier roles, relationships, ledgers, soft deletion, and inactive historical visibility.
- [x] Add role-aware Party filtering, selectors, and multi-role badges.
- [x] Prevent duplicate role rows and keep role assignment transactional.

## Deal domain

- [x] Add `Deal`, `DealSource`, and `DealDestination` with one Product per Deal.
- [x] Generate a stable human reference after creation.
- [x] Allow nullable commercial fields in drafts without fabricated values.
- [x] Use child tables so later phases can add multiple sources and destinations.
- [x] Store server-calculated line amounts and preliminary gross difference.
- [x] Add a small Deal lifecycle: draft/in progress/completed/cancelled.
- [x] Keep Purchase/Sale tables as unchanged compatibility history, not duplicate Deal authority.

## Direct workflow and weighing

- [x] Build a transactional Deal workflow service for draft save and atomic completion.
- [x] Require supplier, customer, source quantity/rate, final quantity/rate, and balanced direct quantity only at completion.
- [x] Create no Purchase, Sale, Warehouse, or InventoryMovement record for a direct Deal.
- [x] Reuse direct-net and two-stage weighing calculations and finalization.
- [x] Link new weighing records to Deal destinations without fake Sale/SaleItem records.
- [x] Preserve pending second weight, private evidence, cleanup, authorization, and finalized immutability.
- [x] Project completed source/destination obligations into the existing Party ledger without recording payments.

## Professional Deal workspace

- [x] Make `معاملات` the Deal workspace for authorized Deal creators while retaining role-safe V2.1 compatibility views.
- [x] Provide a polished Dari RTL form, progressive weighing/details disclosure, live amount/margin previews, and recent Deal list.
- [x] Add a useful Deal detail view with only implemented sections.
- [x] Support compact filters and responsive 1366×768 and 390×844 behavior.
- [x] Preserve the approved logo, visual language, accessibility, and server-authoritative values.

## Verification and release

- [x] Add Party migration/role/filter tests.
- [x] Add Deal draft, calculation, completion, rollback, authorization, direct-flow, ledger, and no-inventory tests.
- [x] Add Deal-native direct-net, two-stage, pending, evidence, and authorization tests.
- [x] Keep the full V2.1 suite green and add regression assertions for rendered UI.
- [x] Validate V2.1 database upgrade, clean MySQL migrate/seed, and practical rollback on disposable schemas.
- [x] Run PHPUnit, Pint, Blade compile, Vite, strict Composer validation, and PHP syntax sweep.
- [x] Run live desktop/mobile browser workflows and inspect console/layout/private evidence behavior.
- [x] Run privacy, credential, ignored-source, role, wording, diff, and clean-worktree checks.
- [x] Update required architecture/business/build documentation without rewriting planning history.
- [x] Create `v3-phase2-deal-foundation-ready` only after every gate passes.
