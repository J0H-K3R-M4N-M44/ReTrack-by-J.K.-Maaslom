import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright-core';
const url = process.env.REPAIRTRACK_TEST_URL;
const site = process.env.REPAIRTRACK_TEST_SITE;
assert(url && ['localhost', '127.0.0.1'].includes(new URL(url).hostname));
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH || process.cwd() + '/qa/chromium',
  args: ['--no-sandbox'],
  env: { ...process.env, LD_LIBRARY_PATH: process.cwd() + '/qa' },
});
async function pageFor(options = {}) {
  const context = await browser.newContext(options);
  await context.route('**/*', (r) =>
    r.request().url().startsWith(url) ? r.continue() : r.abort()
  );
  return context.newPage();
}
async function login(page, email, password) {
  await page.goto(url + '/admin/login.php');
  await page.locator('[name=email]').fill(email);
  await page.locator('[name=password]').fill(password);
  await page.getByRole('button', { name: 'Login', exact: true }).click();
}
try {
  const page = await pageFor({ javaScriptEnabled: false });
  await login(page, 'admin@example.test', 'DemoRepair2026!');
  await page.waitForURL('**/dashboard.php');
  await page.getByRole('link', { name: 'Staff management' }).click();
  await page.getByRole('button', { name: 'Review staff details' }).click();
  assert(await page.locator('[role=alert]').isVisible());
  await page.locator('#full_name').fill('Browser Staff');
  await page.locator('#email').fill('browser.staff@example.test');
  await page.locator('#role').selectOption('TECHNICIAN');
  await page.locator('[name="branches[]"]').first().check();
  await page.locator('[name=default_branch]').first().check();
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.screenshot({ path: site + '/staff-desktop.png', fullPage: true });
  await page.getByRole('button', { name: 'Review staff details' }).click();
  assert(await page.getByRole('heading', { name: 'Review staff details' }).isVisible());
  const review = await page
    .locator('form')
    .filter({ has: page.getByRole('button', { name: 'Create staff account' }) })
    .evaluate((f) => Object.fromEntries(new FormData(f)));
  await page.getByRole('button', { name: 'Create staff account' }).click();
  await page.waitForURL('**/staff.php');
  const activation = await page.locator('#activation-link').inputValue();
  const activationURL = new URL(activation, url + '/admin/staff.php').href;
  const replay = await page.request.post(url + '/admin/staff.php', {
    form: { ...review, intent: 'confirm' },
  });
  assert.equal(replay.status(), 409);
  assert.equal(
    (
      await page.request.post(url + '/admin/staff.php', { form: { csrf_token: 'invalid' } })
    ).status(),
    403
  );
  const staff = await pageFor();
  await staff.goto(activationURL);
  await staff.locator('[name=password]').fill('BrowserStaff2026!');
  await staff.locator('[name=confirm_password]').fill('BrowserStaff2026!');
  await staff.getByRole('button', { name: 'Activate account' }).click();
  assert((await staff.locator('[role=status]').textContent()).includes('account is active'));
  await login(staff, 'browser.staff@example.test', 'BrowserStaff2026!');
  await staff.waitForURL('**/staff_home.php');
  for (const path of ['staff.php', 'dashboard.php', 'intake_create.php'])
    assert.equal((await staff.request.get(url + '/admin/' + path)).status(), 403);
  await page.locator('#staff-search').fill('browser.staff@example.test');
  await page.getByRole('button', { name: 'Filter staff' }).click();
  await page.getByRole('link', { name: 'Manage Browser Staff' }).click();
  await page.locator('#full_name').fill('Browser Staff Updated');
  await page.getByRole('button', { name: 'Review staff details' }).click();
  await page.getByRole('button', { name: 'Save staff changes' }).click();
  await page.locator('#staff-search').fill('browser.staff@example.test');
  await page.getByRole('button', { name: 'Filter staff' }).click();
  await page.getByRole('link', { name: 'Manage Browser Staff Updated' }).click();
  await page.setViewportSize({ width: 390, height: 844 });
  await page.screenshot({ path: site + '/staff-mobile.png', fullPage: true });
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  await page.screenshot({ path: site + '/staff-mobile.png', fullPage: true });
  await page.getByRole('button', { name: 'Review deactivation' }).click();
  await page.getByRole('button', { name: 'Go back', exact: true }).click();
  assert.equal(await page.locator('#full_name').inputValue(), 'Browser Staff Updated');
  await page.getByRole('button', { name: 'Review deactivation' }).click();
  await page.getByRole('button', { name: 'Deactivate account', exact: true }).click();
  await staff.goto(url + '/admin/staff_home.php');
  assert(staff.url().endsWith('/admin/login.php'));
  console.log('PASS: staff lifecycle, permissions, CSRF, replay and mobile layout.');
} finally {
  await browser.close();
}
