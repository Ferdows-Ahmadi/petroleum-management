// Use the disposable manager fixture and TEST_* settings documented in payment-counterparty.cjs.
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

async function main() {
    const base = new URL(process.env.TEST_BASE_URL);
    assert(['localhost', '127.0.0.1', '[::1]'].includes(base.hostname), 'Use a local test site');
    const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL || 'msedge' });
    try {
        const page = await browser.newPage({ baseURL: base.origin, viewport: { width: 1920, height: 960 } });
        await page.goto('/login');
        await page.locator('#email').fill(process.env.TEST_EMAIL);
        await page.locator('#password').fill(process.env.TEST_PASSWORD);
        await page.locator('button[type=submit]').click();
        await page.waitForURL('**/dashboard');

        const menu = page.locator('#office-menu');
        assert.equal(await menu.locator('a:visible').count(), 7, 'Desktop manager navigation');
        assert.equal(await menu.locator('i, svg, img').count(), 0, 'Desktop navigation is text-only');
        await menu.getByRole('link', { name: 'گزارش‌ها' }).click();
        await page.waitForURL('**/reports/purchases');

        await page.setViewportSize({ width: 390, height: 844 });
        // Set only the rendered name so the case is reproducible with any fictional manager.
        await page.locator('.topbar .profile-name').evaluate(name => {
            name.textContent = 'عبدالرحمن احمدی مدیر عمومی شرکت تجارت مواد نفتی نمونه';
        });
        await page.evaluate(() => document.fonts.ready);
        const logout = page.locator('.topbar form[action$="/logout"] button');
        const layout = await logout.evaluate(button => {
            const { left, top, right, bottom, width, height } = button.getBoundingClientRect();
            const viewport = document.documentElement;
            const points = [left + 5, right - 5].flatMap(x => [top + 5, bottom - 5].map(y => [x, y]));
            return {
                left, right, width, height,
                viewport: viewport.clientWidth,
                scrollWidth: viewport.scrollWidth,
                cornersClickable: points.every(([x, y]) => button.contains(document.elementFromPoint(x, y))),
            };
        });
        assert.equal(layout.scrollWidth, layout.viewport, 'No horizontal scrolling at 390px');
        assert.ok(layout.width > 0 && layout.height > 0 && layout.left >= 0 && layout.right <= layout.viewport,
            'The entire logout button must fit in the mobile viewport');
        assert.ok(layout.cornersClickable, 'All four corners of logout must accept pointer input');
        await logout.click();
        await page.waitForURL('**/login');
        await page.goto('/dashboard');
        assert.equal(new URL(page.url()).pathname, '/login', 'The manager session was signed out');
    } finally {
        await browser.close();
    }
}

main().catch(error => { console.error(error.message); process.exitCode = 1; });
