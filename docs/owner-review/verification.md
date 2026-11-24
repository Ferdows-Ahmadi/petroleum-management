# Verification record

The approved development baseline was \`00310ab18ce3157997168af74774b3bc99fc6d04\`. The following runs used disposable local fixtures on Windows. The private contact preview used a read-only database transaction against the intended installation. Its row-level results are held separately by the owner.

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
| Windows lifecycle | Fresh installer created both isolated shortcuts. Start and restart served the login page with HTTP 200. Restart changed the server PID. Stop closed its listener. |
| Upgrade and package guards | The expected SQL endpoint and schema are checked before stopping the application. The rehearsed upgrade verified the manifest, SQL and uploads and required matching server identity in staging. The Windows guard suite accepted valid inputs and rejected seven mismatches. |

The warehouse reports show the quantity assigned by stored allocation records. If multiple warehouses supplied a sale, the historical data does not always establish each warehouse’s share of the customer’s payment. The report leaves that amount undetermined and explains the gap.

The visual comparison used the same desktop page with Windows Tahoma and locally bundled Vazirmatn before selecting the latter. The mobile screenshots are Edge viewport simulations. Review of the actual Dari wording by company staff and approval of the final Windows release remain operational acceptance steps.

## Repeat the automated checks

Run from a disposable checkout with the locked dependencies installed and a local test application key. For MySQL, provision isolated schemas named \`petroleum_management_test\` and \`petroleum_management_restore_test\` and verify the server, port and data directory before executing PHPUnit. The MySQL suite resets these test schemas.

\`\`\`powershell
php vendor/bin/phpunit
php vendor/bin/phpunit -c phpunit.mysql.xml
node --test tests/frontend/*.test.mjs
npm run build
\`\`\`

For the Edge checks, prepare the fictional demo and testing-only credentials described in [the browser harness](../../tests/browser/payment-counterparty.cjs). Run that harness and [the print regression](../../tests/browser/report-print.cjs) against the same loopback URL. For operator instructions see the [Dari checklist and screenshots](README.md).
