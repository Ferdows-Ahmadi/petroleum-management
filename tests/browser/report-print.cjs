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
        const reports = page.getByRole('navigation', { name: 'اصلی' }).getByRole('link', { name: 'گزارش‌ها' });
        await reports.click();
        assert.equal(new URL(page.url()).pathname, '/reports/purchases');
        const print = page.getByRole('button', { name: 'چاپ / PDF' });
        assert.equal(await print.evaluate(button => {
            const text = Array.from(button.childNodes).find(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
            const range = document.createRange();
            range.selectNodeContents(text);
            // Mixed RTL/LTR runs have separate rectangles even on a single line.
            return new Set(Array.from(range.getClientRects(), rect => Math.round(rect.top))).size;
        }), 1, 'The desktop print label must fit on one line');
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
