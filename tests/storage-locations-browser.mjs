import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';

const origin = process.env.REPAIRTRACK_TEST_URL;
assert(origin && ['localhost', '127.0.0.1'].includes(new URL(origin).hostname));
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH || process.cwd() + '/qa/chromium',
  args: ['--no-sandbox'],
  env: { ...process.env, LD_LIBRARY_PATH: process.cwd() + '/qa' },
});
try {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  await context.route('**/*', (r) =>
    r.request().url().startsWith(origin) ? r.continue() : r.abort()
  );
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto(origin + '/admin/login.php');
  await page.locator('[name=email]').fill('manager.console@example.test');
  await page.locator('[name=password]').fill('ConsoleDemo2026!');
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/staff_home.php');
  await page.goto(origin + '/admin/locations.php');
  await page.getByRole('heading', { name: 'Storage locations' }).waitFor();
  const branchSelect = page.locator('[name=branch_id]');
  assert((await branchSelect.locator('option').count()) > 1);
  await branchSelect.selectOption({ index: 1 });
  const code = 'QA-' + Math.random().toString(16).slice(2, 10).toUpperCase();
  await page.locator('[name=location_code]').fill(code);
  await page.locator('[name=location_name]').fill('Browser test workbench');
  await page.locator('[name=location_type]').selectOption('WORKBENCH');
  await page.getByRole('button', { name: 'Review location' }).click();
  await page.getByRole('heading', { name: 'Review before creating' }).waitFor();
  await page.getByRole('button', { name: 'Create storage location' }).click();
  await page.getByRole('status').getByText('Storage location created.').waitFor();
  await page.getByText(code).waitFor();
  assert(await page.getByText('Browser test workbench').isVisible());
  for (const width of [1440, 390, 320]) {
    await page.setViewportSize({ width, height: 844 });
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      'Location page overflow at ' + width);
  }
  const invalidCsrf = await page.request.post(origin + '/admin/locations.php', {
    form: { csrf_token: 'invalid', intent: 'review' },
  });
  assert.equal(invalidCsrf.status(), 403);
  assert.deepEqual(errors, []);
  console.log('PASS: manager location review/create, listed result, mobile layouts and CSRF rejection.');
} finally {
  await browser.close();
}
