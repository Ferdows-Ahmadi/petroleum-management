# Verification record

The approved development baseline was `00310ab18ce3157997168af74774b3bc99fc6d04`. The following runs used disposable local fixtures on Windows. The private contact preview used a read-only database transaction against the intended installation. Its row-level results are held separately by the owner.

| Check | Observed result |
|---|---|
| Full SQLite 3.45.2 suite with PHP 8.5.0 | 385 passed, 5 MySQL-only cases skipped, 2,658 assertions |
| Full MySQL 8.4.11 suite with PHP 8.5.0 | 390 passed, 2,699 assertions, including concurrency, backup and separate empty-schema recovery |
| Frontend regression suite | 15 passed using Node 24.18.0 |
| Production assets | Vite build passed. CSS references a bundled variable font and local icon assets. |
| Edge 158.0.4370.33 | Four Windows viewports, 69 candidate page/viewport captures. Every document fit the viewport. The desktop manager had seven text-only links in RTL order. The mobile menu opened by keyboard and restored focus after Escape. |
| Browser controls | Existing payment-counterparty test passed. Printed inventory, purchases, sales, accounts and journal kept the same visible data-row count as the screen. The inventory A4 PDF was rendered back to an image and inspected. |
| Fresh and upgraded installations | The fictional existing draft, completed transaction, original note, product ID and prior backup survived. The two pre-upgrade USD ledger balances (−958.75 and +1,180.25) matched afterward. Fresh and repeated initialization yielded exactly two default fuels. |
| Recovery | Restoring into a separate empty schema reproduced all table rows, the historical and new notes, and the sample upload bytes. The configuration and application key were preserved during upgrade. Invalid backup target and nonempty restore both failed. |
| Maintenance-window code rollback | Took and verified a post-upgrade backup, parked the candidate tree, restored the original code at the permanent test path and authenticated to the dashboard on both versions (HTTP 200). Both deals, exact balances, product IDs, uploaded bytes and appended-note database row matched. |
| Windows package regression | The scratch package loaded from the committed source and locked dependencies. All 6,955 file hashes matched. Stale files and a broken lockfile were rejected. |
| Windows lifecycle | Fresh installer created both isolated shortcuts. Start and restart served the login page with HTTP 200. Restart changed the server PID. Stop closed its listener. |
| Upgrade and package guards | The expected SQL endpoint and schema are checked before stopping the application. The rehearsed upgrade verified the manifest, SQL and uploads and required matching server identity in staging. The Windows guard suite accepted valid inputs and rejected seven mismatches. |

The warehouse reports show the quantity assigned by stored allocation records. If multiple warehouses supplied a sale, the historical data does not always establish each warehouse’s share of the customer’s payment. The report leaves that amount undetermined and explains the gap.

The visual comparison used the same desktop page with Windows Tahoma and locally bundled Vazirmatn before selecting the latter. The mobile screenshots are Edge viewport simulations. Review of the actual Dari wording by company staff and approval of the final Windows release remain operational acceptance steps.

## Repeat the automated checks

Run from a disposable checkout with the locked dependencies installed and a local test application key. For MySQL, provision isolated schemas named `petroleum_management_test` and `petroleum_management_restore_test` and verify the server, port and data directory before executing PHPUnit. The MySQL suite resets these test schemas.

```powershell
php vendor/bin/phpunit
php vendor/bin/phpunit -c phpunit.mysql.xml
node --test tests/frontend/*.test.mjs
npm run build
```

For the Edge checks, prepare the fictional demo and testing-only credentials described in [the browser harness](../../tests/browser/payment-counterparty.cjs). Run that harness and [the print regression](../../tests/browser/report-print.cjs) against the same loopback URL. For operator instructions see the [Dari checklist and screenshots](README.md).


## PR review follow-up on legacy submissions

We replayed the same complete HTTP requests on the reviewed commit `4f5a6a8` and the revised branch, using fictional customers, suppliers, stock and financial accounts. Each request contained a fresh operation key and valid business fields. None contained a draft-saving parameter.

| Request | Observed on the reviewed commit | Observed after the fix |
|---|---|---|
| Legacy purchase | Editable `DRAFT` | Read-only submission awaiting approval. No stock or payable is posted at submission. |
| Legacy sale | Editable `DRAFT` | Awaits weighing. Finalizing all lines makes it eligible for approval. The sale posts only on the authorized confirm action. A requested weight can remain unknown until weighing. |
| Legacy expense | Editable `DRAFT` | Awaits explicit financial approval. No account balance changes on submission. |
| Capital contribution | Editable `DRAFT` | Awaits explicit financial approval. Neither the capital position nor cash balance changes on submission. |
| Journal expense without confirmation intent | Created a confirmed expense | Validation rejects the request. No financial transaction is created. |

The normal purchase and sale confirmation services continue to post stock using the existing financial and inventory rules. Repeated approvals post once. A withdrawn pending financial submission remains recorded and cannot be approved. Historical draft records retain their existing authorized handling. New daily deals require a supported registration action in the service itself. Real unfinished weighing and partial delivery remain available.

Final regressions passed with 399 MySQL tests (2,868 assertions), 394 SQLite tests (2,827 assertions, 5 MySQL-only skips), and 15 JavaScript tests. The Vite production build also passed. The role, replay, withdrawal, historical-draft and multi-line weighing tests exercise the normal routes with complete submissions. No migration was needed for these status values. The [Dari rollback instructions](../windows-upgrade-fa.md) explain the compatibility boundary after new submissions have been written.
