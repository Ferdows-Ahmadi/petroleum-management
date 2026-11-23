// In a disposable checkout, copy .env.example to .env, create an empty SQLite file, and set
// APP_ENV=testing, DB_CONNECTION=sqlite, and DB_DATABASE to that file's absolute path. Run:
//   composer install
//   php artisan key:generate
//   npm ci
//   npm run build
//   php artisan migrate --seed --seeder='Tests\Fixtures\DemoSeeder'
//   php artisan app:create-manager
//   php artisan serve --host=127.0.0.1
// In a second shell, set TEST_BASE_URL to that local URL, and TEST_EMAIL and TEST_PASSWORD
// to the test manager's credentials. Install optional browser tooling and run:
//   npm install --no-save --package-lock=false playwright
//   npx playwright install chromium
//   node tests/browser/payment-counterparty.cjs
// An installed Edge can be used with PLAYWRIGHT_CHANNEL=msedge.
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const { expect } = require('playwright/test');

const receipt = 'CUSTOMER_RECEIPT';
const payment = 'SUPPLIER_PAYMENT';
const dualRoleName = 'شرکت خدماتی آفتاب نمونه';

async function main() {
    const base = new URL(process.env.TEST_BASE_URL);
    assert(['localhost', '127.0.0.1', '[::1]'].includes(base.hostname), 'Use a disposable local test site');
    assert(process.env.TEST_EMAIL && process.env.TEST_PASSWORD, 'Supply the test user credentials');
    const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });
    try {
        const page = await browser.newPage({ baseURL: base.origin });
        await page.goto('/login');
        await page.locator('#email').fill(process.env.TEST_EMAIL);
        await page.locator('#password').fill(process.env.TEST_PASSWORD);
        await page.locator('#password').press('Enter');
        await page.waitForURL(url => url.pathname !== '/login');

        await page.goto('/daily-journal?entry=money');
        const form = page.locator('form[action$="/money-movements"]');
        const type = form.locator('[name=movement_type]');
        const box = form.locator('[data-money-party-combo]');
        const query = box.locator('[data-party-query]');
        const id = box.locator('[data-party-id]');
        const option = box.locator(`[data-party-option][data-name="${dualRoleName}"]`);
        const dualRoleId = await option.getAttribute('data-id');

        async function chooseDualRole() {
            await query.fill(dualRoleName);
            await option.click();
        }
        async function assertCleared() {
            assert.equal(await query.inputValue(), '', 'Visible counterparty');
            assert.equal(await id.inputValue(), '', 'Submitted counterparty ID');
        }

        assert.equal(await type.inputValue(), receipt);
        for (const nextType of [payment, receipt]) {
            await chooseDualRole();
            await expect(id).toHaveValue(dualRoleId);
            await type.selectOption(nextType);
            await assertCleared();
        }

        // Keep the search empty when clicking an option. Both the role and the query
        // will match the old lookup again after switching away and back.
        let releaseLookup;
        const gate = new Promise(resolve => { releaseLookup = resolve; });
        await page.route('**/inline/parties', async route => {
            const response = await route.fetch();
            await gate;
            await route.fulfill({ response });
        }, { times: 1 });
        const lookup = page.waitForRequest('**/inline/parties');
        await query.click();
        await option.click();
        await lookup;
        await type.selectOption(payment);
        await type.selectOption(receipt);
        const completion = page.waitForResponse('**/inline/parties');
        releaseLookup();
        const response = await completion;
        assert(response.ok(), 'Inline selection should succeed');
        await response.finished();
        await page.waitForLoadState('networkidle');
        await assertCleared();

        for (const otherType of ['OTHER_RECEIPT', 'OTHER_PAYMENT']) {
            await chooseDualRole();
            await expect(id).toHaveValue(dualRoleId);
            await type.selectOption(otherType);
            await assertCleared();
            await expect(box).toBeHidden();
            assert.equal(await form.evaluate(element => new FormData(element).has('counterparty_id')), false);
            await type.selectOption(receipt);
            await expect(query).toBeVisible();
            await assertCleared();
        }
        console.log('Passed dual-role switches, delayed lookup, and noncommercial counterparty omission.');
    } finally {
        await browser.close();
    }
}

main().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
