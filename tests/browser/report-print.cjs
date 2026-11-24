// Use the same disposable, seeded TEST_BASE_URL / TEST_EMAIL / TEST_PASSWORD as payment-counterparty.cjs.
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

async function main() {
    const base = new URL(process.env.TEST_BASE_URL);
    assert(['localhost', '127.0.0.1', '[::1]'].includes(base.hostname));
    const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL || 'msedge' });
    try {
        const page = await browser.newPage();
        await page.goto(new URL('/login', base).href);
        await page.locator('#email').fill(process.env.TEST_EMAIL);
        await page.locator('#password').fill(process.env.TEST_PASSWORD);
        await Promise.all([page.waitForURL('**/dashboard'), page.locator('button[type=submit]').click()]);
        for (const report of ['inventory', 'purchases', 'sales', 'accounts', 'accounts/journal']) {
            await page.emulateMedia({ media: 'screen' });
            const response = await page.goto(new URL('/reports/' + report, base).href);
            assert.equal(response.status(), 200);
            const rows = page.locator('.report-document table tbody tr:visible');
            const count = await rows.count();
            assert.ok(count > 0, 'Seed a fictional row for ' + report);
            await page.emulateMedia({ media: 'print' });
            assert.equal(await rows.count(), count, 'Print lost data rows in ' + report);
            assert.ok(await page.locator('.print-header').isVisible());
            assert.equal(await page.locator('.topbar:visible, form.filters:visible').count(), 0);
        }
    } finally {
        await browser.close();
    }
}

main().catch(error => { console.error(error.message); process.exitCode = 1; });
