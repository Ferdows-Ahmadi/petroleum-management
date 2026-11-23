# V3 UI Workflow Proposal

## Experience goal

The future interface should let the office complete the work currently spread across many sheets with fewer entries and clearer consequences. It should be Afghan Dari first, RTL, professional, fast on an office desktop, usable on smaller screens, and grounded in the approved V2.1 visual language without copying spreadsheet visuals.

This document proposes information architecture and interaction behavior only. It does not change the current UI.

## Proposed top-level navigation

1. `داشبورد`
2. `معاملات`
3. `حساب‌ها`
4. `روزنامچه`
5. `ذخیره`
6. `گزارش‌ها`
7. `تنظیمات` for authorized roles

This keeps seven understandable work areas and removes implementation entities from primary navigation.

- Settlement (`تصفیه`) belongs inside a Deal.
- Loading/logistics (`بارگیری`) and weighing belong inside a Deal.
- Saraf belongs in Party accounts/profile and appears in Journal transfer actions.
- Products, warehouses, user administration, internal cash/bank accounts, and controlled reference lists belong in Settings.
- Detailed audit screens can remain reachable contextually without becoming primary navigation.

## Global interaction conventions

- Preserve RTL composition, Dari labels, Jalali input/presentation, Western numeric entry, current logo, and consistent V2.1 controls.
- Use one clear page title, primary action, compact filters, and source-backed status wording.
- Prefer a work form above recent/relevant activity when speed matters.
- Use drawers, dialogs, or expandable sections for short contextual additions; use a full page for a Deal because it is a durable workflow.
- Show user language such as “Customer owes office” instead of unexplained debit/credit signs.
- Display native currency beside every amount and the explicit rate beside converted amounts.
- Never hide a material discrepancy behind a total; surface shortage, unallocated quantity, unpaid balance, and unapproved settlement.
- Keep authorization server-enforced. Hidden buttons are only a usability layer.

## Dashboard

The dashboard answers “what needs attention now?” rather than showing every module as a card.

Suggested sections:

- active deals awaiting source, delivery, weighing, reconciliation, or approval;
- today's petroleum delivery quantity and confirmed deal value;
- outstanding customer receivables and supplier payables by currency;
- Saraf positions requiring attention;
- unexplained quantity/shortage warnings;
- storage positions and low/negative-data-quality warnings;
- today's receipts, payments, expenses, and closing position;
- recent activity allowed by the user's role.

Use a compact attention list and a few high-value totals. Do not build a large shortcut grid.

## Deals workspace (`معاملات`)

### List and quick start

The list supports search/filter by:

- reference;
- party;
- product;
- state;
- delivery/date range;
- unresolved weighing, quantity, settlement, or money status.

The primary action is `معامله جدید`.

Quick start asks only for:

- customer;
- product;
- requested/approximate kilograms;
- desired delivery date;
- optional proposed sale rate.

Typing a new Party can open a minimal inline identity form with role assignment, following V2.1's useful inline-partner behavior.

### Deal page structure

The Deal page should have a persistent summary header and workflow sections:

```text
[reference] [state] [product] [quantity status] [money status]

Overview | Sources | Delivery & logistics | Expenses | Partnership | Settlement | Money | Activity
```

On narrower screens these become a vertical progress list or compact tab selector.

### Overview

Show:

- customer request and commercial summary;
- source and destination totals;
- delivered, storage, and unexplained kilograms;
- revenue, petroleum cost, expenses, preliminary result;
- customer/supplier outstanding positions;
- next required action.

Do not expose database model names.

### Sources

Inline repeating rows for supplier or storage source:

- source;
- expected/accepted kg;
- rate and currency;
- date/reference;
- optional evidence and notes.

The simple case starts with one row. “Add another source” reveals additional rows. Storage-specific fields appear only when storage is selected.

### Destinations

Customer and optional storage destinations use the same progressive pattern. Customer fields include requested/final kilograms and sale rate; storage destination has no sale price or receivable.

### Delivery, logistics, and weighing

The ordinary one-truck path is a compact section with vehicle, driver/contact snapshot, date, and weighing choice.

- Direct net shows one authoritative net field.
- Two-stage shows first weight, clear pending state, and later second-weight completion.
- Advanced details reveal station, ticket number, loading/unloading, road notes, and private evidence.
- Typed cost shortcuts can add freight, road, driver, or weighbridge expense without leaving the Deal.

Multiple logistics movements appear as a chronological list with “Add another truck/delivery.” Do not force a separate fleet or driver CRUD workflow.

### Allocation review

For a one-source/one-destination deal, allocation is automatic and usually hidden.

When there are multiple sources or destinations, show a quantity allocation table:

| Source | Destination | Allocated kg | Cost basis | Status |
|---|---|---:|---|---|

The UI should suggest allocations and show unallocated/excess kilograms. It must not silently decide an uncertain cost allocation.

### Expenses

Inline list with category, payee, amount, currency, explicit rate when converted, effective date, paid/unpaid state, and optional evidence. Advanced details include link to a specific movement/source/destination.

### Partnership

Collapsed by default. When enabled, the user selects Party, scope, contribution, result basis, loss participation, and terms. The screen previews the effect but settlement approval remains authoritative.

### Settlement (`تصفیه`)

Settlement is a review workspace, not a duplicate entry form. It displays:

- source quantities and costs;
- customer/storage destinations and revenue;
- shortage/excess reconciliation;
- expenses and adjustments;
- gross result;
- partnership allocations;
- office result;
- customer, supplier, expense, and money outstanding;
- warnings and source links.

The user resolves missing inputs at their source. Only explicit typed adjustments are entered here. Approval creates an immutable revision with preparer/approver details.

### Money

Show deal-related receipts/payments independently from physical status. Quick actions:

- receive from customer;
- pay supplier;
- record through Saraf;
- allocate an existing movement;
- reverse an incorrect posting when authorized.

The form supports installments, multiple dates, several Sarafs, original currency, explicit conversion, and allocation to more than one deal. It previews all party-position effects before posting.

### Activity and evidence

One chronological audit stream combines deal state changes, source/destination postings, weighings, private evidence, storage movements, expenses, money, settlement revisions, and reversals. Evidence opens only through protected authorized routes.

## Accounts and Party profile (`حساب‌ها`)

### Accounts workspace

One search across customers, suppliers, Sarafs, and partners. Filters include role, active state, currency, receivable/payable direction, and recent activity.

Each result shows role chips and plain position text. Do not maintain separate customer, supplier, Saraf, and partner directories.

### Party profile

Suggested tabs:

- Summary;
- Ledger;
- Deals;
- Purchases/Sales by role;
- Money and Saraf transfers;
- Partnership;
- Identity and roles.

The ledger keeps the familiar simple shape:

- date;
- description/reference;
- optional quantity and rate;
- increases office claim;
- decreases office claim;
- running position;
- source link.

For negative positions, the labels switch to clear payable language. Currency ledgers remain separate.

Quick receipt/payment/transfer actions are inline for authorized users. Adding/removing a role is in profile settings, not a separate duplicate profile.

## Daily journal (`روزنامچه`)

The journal is a derived day/account view with an optional source-event entry form.

Header controls:

- Jalali date;
- internal account or Saraf Party;
- currency;
- search/filter.

Summary:

- opening position;
- receipts;
- payments;
- expenses;
- transfers;
- closing position;
- unresolved difference.

Rows link to their source Deal, Party, expense, capital event, or transfer. Users do not copy an existing payment into the journal.

Quick action choices use human intent:

- customer receipt;
- supplier payment;
- Saraf transfer;
- expense;
- office-only movement.

Selecting a specific intent reveals only relevant fields.

## Storage (`ذخیره`)

Storage remains a movement-derived workspace:

- warehouse/product selector;
- current kilograms and supported cost/value;
- total in/out for the selected period;
- remaining storage claim/result metric with plain explanation;
- recent source-linked movements;
- shortage/excess and unsupported-cost warnings.

Users add storage activity from a Deal or an explicit controlled adjustment. They never type a current stock total.

The claim/result display should distinguish:

- cost carried with remaining stock;
- zero-stock surplus/loss result;
- unsupported or incomplete valuation.

## Reports (`گزارش‌ها`)

One report center organized by business question:

- Deals and settlement;
- purchases/sources;
- sales/destinations;
- logistics/weighing/shortage;
- storage movement and valuation;
- Party and Saraf positions;
- daily money/journal;
- expenses;
- partnership/capital;
- profit/loss and reconciliation;
- import reconciliation for authorized administrators.

Reports are read-only and derive from source events. Filters retain Jalali inputs and visible active criteria. Export, if later approved, must honor role/privacy boundaries.

## Settings (`تنظیمات`)

Authorized low-frequency configuration:

- users, roles, and active state;
- products;
- warehouses;
- internal cash/bank accounts;
- controlled expense and adjustment reasons;
- currencies/rate basis display;
- reference numbering;
- import batches and review permissions.

Party identity is normally managed from Accounts/Profile, not Settings.

## Progressive disclosure

### Always visible in a simple deal

- customer, product, requested quantity/date;
- one source;
- one destination;
- actual weight and rate;
- primary save/advance action;
- clear quantity and money status.

### Visible when applicable

- additional sources/destinations;
- storage endpoint;
- direct-net versus two-stage details;
- more trucks/deliveries;
- currency conversion;
- expenses;
- partnership;
- allocation matrix;
- shortage/excess explanation;
- multiple Sarafs and payment allocations.

### Audit-only or advanced

- internal identifiers and source provenance;
- settlement revisions;
- reversals;
- import match evidence;
- technical valuation completeness.

## Desktop and mobile behavior

### Desktop office

- Wide deal header remains visible while scrolling.
- Two-column simple sections may show entry and live reconciliation side by side.
- Dense ledgers use sticky headers, keyboard-friendly inputs, and horizontal containment rather than shrinking text.
- Primary actions remain in a predictable footer/header location.

### Small screen

- Sections stack in workflow order.
- Tables switch to readable row summaries with expandable details.
- Status and next action remain near the top.
- No critical action depends on hover.
- Evidence capture/upload remains possible but private.

## What should not become a separate CRUD page

- settlement lines;
- source/destination allocation rows;
- loading line items;
- vehicle/driver snapshots;
- weighings detached from their Deal context;
- Deal expenses;
- payment allocations;
- Party ledger rows;
- journal duplicates;
- storage totals;
- partnership result rows.

Detailed record pages can exist for audit and permission-sensitive review, but ordinary work begins from Deal, Party, Journal, or Storage context.

## UX decisions needing confirmation

- `NEEDS HUMAN CONFIRMATION`: exact Dari wording for positive/negative customer, supplier, and Saraf positions.
- `NEEDS HUMAN CONFIRMATION`: which Deal transitions require a second approver.
- `NEEDS HUMAN CONFIRMATION`: default allocation suggestion for many-to-many deals.
- `NEEDS HUMAN CONFIRMATION`: whether transport staff need a focused “pending deliveries” landing view in addition to Deal access.

## Phase 2 implementation status (2026-08-30)

For General Manager, System Administrator, and Purchase/Sales Officer, `معاملات` now opens a Deal-centered Dari RTL workspace. Common commercial fields are visible first; optional weighing and private evidence use progressive disclosure. Source, destination, live purchase/sale previews, `مفاد ابتدایی`, draft save, and atomic completion are composed as one office workflow. The recent Deal table supplies compact search and status filtering.

The Deal detail page shows only implemented commercial, Party, weighing/evidence, and preliminary-economics sections. It does not render empty settlement, Saraf, partnership, allocation, or storage panels. Transport, warehouse, and accounting users retain their role-safe V2.1 transaction context where needed, while authorized Deal readers can open Deal details. Responsive rules stack Party cards, completion controls, and summaries on small screens without changing the approved brand direction.

## Phase 3 implementation status (2026-08-30)

- Accounts remains Party-centered. Customer/supplier roles expose contextual receive/pay actions to General Manager and Accountant; Party Deal activity, money events, and running position share one profile.
- A Party with the Saraf role gains a dedicated office-position summary and chronological `تاریخ | تفصیل | دریافت | پرداخت | بیلانس` table with related commercial Party and Deal.
- Deal details expose obligation, received/paid, remaining, and a derived unpaid/partial/paid indicator to finance-authorized readers. Commercial Deal status is unchanged.
- Daily Journal is now a primary navigation destination and switches between internal-account and Saraf contexts with a date filter, opening/closing position, daily totals, and chronological derived rows.
- Confirmed movement details are audit views. Draft-only edit/delete controls disappear after confirmation.
- Purchase/Sales Officer does not receive Saraf profile selection or movement-detail access; Warehouse and Transport roles remain outside finance; System Admin retains read-only finance access.

## Phase 4 implementation status (2026-08-31)

- Deal detail now presents all source/destination lines, allocation provenance, quantity reconciliation, confirmed adjustments, Deal expenses, result totals, and settlement revision history in one contextual workspace.
- The simple one-source/one-destination case remains automatic. Advanced source, destination, and allocation forms appear only where the Deal requires them.
- Commercial settlement and financial settlement have separate headings and totals. Final commercial result is never labelled or positioned as received cash.
- The Storage workspace includes movement-derived stock, supported value, and an explained storage-claim/result metric. Deal movements link back through the Deal reference.
- The report center links to one consolidated settlement report for confirmed/unresolved Deals, profit/loss, shortage, storage allocation, and direct expense totals.
- General Manager and Accountant confirm settlement. Purchase/Sales Officer may prepare commercial records and view results. System Admin is read-only for settlement, while Warehouse and Transport roles do not receive final-profit controls or unrelated finance detail.

## Phase 5 defense UX implementation status (2026-08-31)

- The operational navigation is now exactly Dashboard, Deals, Accounts, Daily Journal, Storage, Reports, then Settings for authorized administrators. The former Finance entry is removed from navigation; its saved URLs redirect into the relevant authoritative workspace.
- A shared light-surface, deep-teal, restrained-gold design system defines spacing, 40-44 px controls, 44-48 px table rows, compact toolbars, small badges, right-side desktop navigation, off-canvas mobile navigation, contained table scrolling, and print behavior.
- Login uses the optimized terminal photograph and transparent new logo with no promotional copy. Dashboard uses one compact summary strip followed by recent Deal and important-account tables.
- Deal entry keeps current Jalali date and a sensible product default, uses writable Party comboboxes, keeps the normal direct-final-weight path visible, and hides reference, evidence, and two-stage details behind disclosure controls. The primary UI no longer advertises draft state.
- Accounts is a searchable Party table with role tabs and one contextual profile/ledger. Daily Journal combines authoritative receipt/payment and expense entry with the existing derived ledger; Party and Saraf selection reuse the same inline combobox.
- Storage, Reports, and Settings are table-first tabbed workspaces. Existing report domains share one tab bar and browser-print treatment; no report or accounting domain was added.
- Confirmed financial, settlement, weighing, evidence, and inventory immutability remain unchanged. Responsive validation targets desktop, mobile, and 90/100/110 percent zoom without page-level horizontal overflow.
