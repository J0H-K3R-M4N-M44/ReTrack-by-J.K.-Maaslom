import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright-core';
const url = process.env.REPAIRTRACK_TEST_URL,
  site = process.env.REPAIRTRACK_TEST_SITE;
assert(url && ['localhost', '127.0.0.1'].includes(new URL(url).hostname));
const f = JSON.parse(fs.readFileSync(site + '/tests/workspace-fixture.json'));
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH || process.cwd() + '/qa/chromium',
  args: ['--no-sandbox'],
  env: { ...process.env, LD_LIBRARY_PATH: process.cwd() + '/qa' },
});
try {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  await context.route('**/*', (r) =>
    r.request().url().startsWith(url) ? r.continue() : r.abort()
  );
  const page = await context.newPage(),
    errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.goto(url + '/admin/login.php');
  await page.locator('#show-password').click();
  assert.equal(await page.locator('#password').getAttribute('type'), 'text');
  await page.locator('#show-password').click();
  await page.locator('#email').fill('manager.console@example.test');
  await page.locator('#password').fill('ConsoleDemo2026!');
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/staff_home.php');
  assert((await page.locator('tbody tr').count()) <= 10);
  await page.getByRole('link', { name: 'Status board', exact: true }).click();
  assert.equal(await page.locator('table').count(), 0);
  assert((await page.locator('.board-card').count()) <= 10);
  const point = page.locator('.trend-point').first();
  await point.locator('summary').hover();
  assert(await point.evaluate((el) => el.open));
  await point.locator('summary').focus();
  await page.keyboard.press('Escape');
  assert(!(await point.evaluate((el) => el.open)));
  await page.screenshot({ path: site + '/workspace-board-desktop.png', fullPage: true });
  for (const width of [768, 390, 320]) {
    await page.setViewportSize({ width, height: 900 });
    assert(
      await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      `Board overflow ${width}`
    );
  }
  await page.getByRole('link', { name: 'History', exact: true }).click();
  await page.locator('#queue-search').fill('WORK-0');
  await page.getByRole('button', { name: 'Search', exact: true }).click();
  await page.locator('tbody tr').filter({ hasText: 'WORK-0' }).waitFor();
  assert.equal(await page.locator('table').count(), 1);
  const cashContext = await browser.newContext(),
    cash = await cashContext.newPage();
  await cash.goto(url + '/admin/login.php');
  await cash.locator('#email').fill('cashier.workspace@example.test');
  await cash.locator('#password').fill('WorkspaceDemo2026!');
  await cash.getByRole('button', { name: 'Login', exact: true }).click();
  await cash.waitForURL('**/staff_home.php');
  assert(await cash.getByRole('heading', { name: 'Cash desk', exact: true }).isVisible());
  await cash.locator('#queue-search').fill(f.code);
  await cash.getByRole('button', { name: 'Search', exact: true }).click();
  await cash.locator('tbody tr').filter({ hasText: 'WORK-BILL' }).waitFor();
  assert.equal((await cash.request.get(url + '/admin/device.php?id=' + f.device)).status(), 403);
  await cash.setViewportSize({ width: 390, height: 844 });
  assert(await cash.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  await cash.screenshot({ path: site + '/workspace-cash-mobile.png', fullPage: true });
  const visitorContext = await browser.newContext(),
    visitor = await visitorContext.newPage();
  await visitor.goto(url + '/track.php#code=' + f.code);
  await visitor.locator('#portal-pin').fill(f.pin === '0000' ? '1111' : '0000');
  await visitor.getByRole('button', { name: 'View repair' }).click();
  assert(await visitor.getByRole('alert').isVisible());
  await visitor.locator('#tracking-code').fill(f.code);
  await visitor.locator('#portal-pin').fill(f.pin);
  await visitor.getByRole('button', { name: 'View repair' }).click();
  assert(await visitor.getByRole('heading', { name: 'Your repair update' }).isVisible());
  const touchContext = await browser.newContext({
      hasTouch: true,
      isMobile: true,
      viewport: { width: 390, height: 844 },
      storageState: await context.storageState(),
    }),
    touch = await touchContext.newPage();
  await touch.goto(url + '/admin/staff_home.php');
  await touch.locator('.trend-point summary').first().tap();
  assert(
    await touch
      .locator('.trend-point')
      .first()
      .evaluate((el) => el.open)
  );
  assert.deepEqual(errors, []);
  console.log(
    'PASS: login reveal, board/history, hover/focus/Escape/tap, cashier search/scope, PIN and mobile layouts.'
  );
} finally {
  await browser.close();
}
