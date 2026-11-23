# Codex UI Handoff

Use this static prototype as the visual and information-architecture source of truth.

## Do
- Convert the shell into Blade layouts/partials/components.
- Keep all user-facing strings in Laravel localization files.
- Use Bootstrap RTL and preserve the same spacing, typography hierarchy, table density, card shapes, sidebar structure, badges, forms, and responsive behavior.
- Replace demo values with real database values only when each module is implemented.
- Keep pages functional and conventional.

## Do not
- Redesign the app into a generic English SaaS template.
- Move the sidebar to the left in Dari mode.
- Add gradients/animations/charts everywhere.
- Add fake analytics that the database cannot support.
- Split customer and supplier into unrelated entities.
- Make stock or balances manually editable.
- Create independent editable tables for reports such as daily journal or profit/loss when they can be derived.

## Recommended Blade structure
```
resources/views/
  layouts/app.blade.php
  partials/sidebar.blade.php
  partials/topbar.blade.php
  components/
    page-header.blade.php
    status-badge.blade.php
    data-table.blade.php
    form-field.blade.php
    stat-card.blade.php
  dashboard/index.blade.php
  partners/...
  products/...
  warehouses/...
  purchases/...
  sales/...
  inventory/...
  transport/...
  finance/...
  capital/...
  reports/...
  admin/...
```
