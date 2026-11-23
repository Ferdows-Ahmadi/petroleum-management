# UI Specification — نفت‌یار

## Design direction
- Dari-first, RTL-first.
- Modern internal business application, not a marketing SaaS dashboard.
- Dark navy right sidebar, warm petroleum/gold accent, off-white workspace.
- Large readable tables, restrained shadows, rounded cards, clear status badges.
- Western digits for financial/weight data; Dari labels.
- Solar Hijri dates in the user interface.

## Main navigation
1. Dashboard
2. Trade: Purchases, Sales, Settlements, Contracts
3. Inventory: Products, Warehouses, Inventory Movements, Transfers, Adjustments
4. Parties: Business Partners, Partner Ledgers, Receivables, Payables
5. Transport: Trucks, Drivers, Trips/Loading, Weighing
6. Finance: Financial Accounts, Payments/Receipts, Daily Journal, Expenses, Capital Partners, Capital Transactions
7. Reports
8. Administration: Users, Roles, Settings

## Core UX rules
- BusinessPartner may be CUSTOMER, SUPPLIER, or BOTH.
- Current stock is displayed as calculated data, never as an editable warehouse field.
- Partner balances are derived from ledger transactions, not editable balances.
- Sale final quantity may come from a linked weighbridge record.
- Sales calculation: quantity_kg / 1000 * rate_per_ton.
- Weighing calculation: first_weight_kg - second_weight_kg.
- Daily Journal is generated from financial transactions.
- Settlements reconcile a purchase lot with one or more sales.
- Reports are derived views, not independent editable data modules.

## Responsive behavior
- Desktop: fixed right sidebar + topbar.
- Tablet/mobile: sidebar becomes an off-canvas panel opened by menu button.
- Tables are horizontally scrollable.
- Forms collapse to one column on small screens.
