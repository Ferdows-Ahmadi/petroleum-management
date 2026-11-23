# V3 Phase 5 UX checklist

## Scope lock

- [x] UX redesign only; no new accounting or commercial domain.
- [x] Phase 4 Deal, settlement, storage, money, evidence, weighing, precision, and RBAC behavior preserved.
- [x] No partnership expansion, historical import, reversal/reallocation, fleet, or contracts redesign.
- [x] No database migration required.

## Navigation and workspaces

- [x] Final main navigation: Dashboard, Deals, Accounts, Daily Journal, Storage, Reports, Settings.
- [x] Finance removed from main navigation.
- [x] Legacy Finance routes redirect to the corresponding retained workspace.
- [x] Saraf is available through Accounts; expenses through Journal; financial accounts through Settings.
- [x] Reports and Settings use tabs and tables rather than launcher-card grids.

## Design system

- [x] Deep teal `#0B5F59`, dark teal `#084A46`, gold `#D9A22E`, light canvas, white surfaces.
- [x] Compact 40-44 px controls and 44-48 px rows, 6-10 px radii, restrained shadows.
- [x] Desktop right sidebar and compact topbar.
- [x] Mobile drawer, single-column forms, internal table scroll, no page-level overflow target.
- [x] Focus visibility, labels, semantic tables, and accessible combobox behavior.
- [x] Browser print hides navigation/actions and adds logo/report header.

## Key workflows

- [x] Minimal split login with optimized terminal image, full logo, and password reveal control.
- [x] Dashboard summary strip, recent Deals table, and role-safe important-account table.
- [x] Deal date and product defaults; writable Supplier/Customer; direct final weight; advanced details hidden.
- [x] Reusable Party combobox supports filtering, arrows, Enter, Escape, exact-name detection, inline create, and feedback.
- [x] Accounts search and role tabs lead to one Party profile, balance, money actions, and running ledger.
- [x] Journal provides authoritative receipt/payment and expense entry above the derived daily ledger.
- [x] Storage is inventory/movement table-first.
- [x] Existing reports share top tabs, relevant filters, large result tables, and browser print.
- [x] Settings presents implemented company, product, warehouse, user/access, and financial-account sections.

## Release gates

- [x] Full PHPUnit result: 248 tests and 1,054 assertions.
- [x] Pint, Blade compile, Vite production build, Composer strict validation, and PHP syntax across 295 project files pass.
- [x] MySQL 8.4.11 migration status confirms all 17 migrations applied; no fresh operation was required because Phase 5 changes no schema.
- [x] Browser demo flow passes with anonymized/fictional data: two Parties created inline, a 10,000 kg Deal completed, the Party profile edited in context, and a 5,000.00 USD receipt recorded through the existing Saraf.
- [x] Desktop 1366x768 and 1920x1080 pass.
- [x] Mobile 390x844 passes across all nine required workspaces.
- [x] Browser zoom geometry at 90, 100, and 110 percent passes through equivalent 1518, 1366, and 1242 CSS-pixel desktop viewports.
- [x] Console, visible controls, report destinations, Settings tabs, legacy redirects, and page-overflow checks pass.
- [x] All six roles and financial privacy pass through the full authorization regression suite.
- [x] Screenshot set captured for login, dashboard, Deals, Deal detail, Accounts, Journal, Storage, Reports, and Settings under the ignored validation workspace.
- [x] Privacy, credential, ignored private spreadsheets, restricted wording, and Git diff checks pass.
- [x] Release process reserves annotated `v3-phase5-defense-ux-ready` for the final verified Phase 5 HEAD only.
