import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright-core';
const url = process.env.REPAIRTRACK_TEST_URL;
const site = process.env.REPAIRTRACK_TEST_SITE;
assert(url && ['localhost', '127.0.0.1'].includes(new URL(url).hostname));
const fixture = JSON.parse(fs.readFileSync(site + '/tests/console-fixture.json', 'utf8'));
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH || process.cwd() + '/qa/chromium',
  args: ['--no-sandbox'],
  env: { ...process.env, LD_LIBRARY_PATH: process.cwd() + '/qa' },
});
async function staff(role) {
  const context = await browser.newContext({
    javaScriptEnabled: false,
    viewport: { width: 1440, height: 1000 },
  });
  await context.route('**/*', (r) =>
    r.request().url().startsWith(url) ? r.continue() : r.abort()
  );
  const page = await context.newPage();
  await page.goto(url + '/admin/login.php');
  await page.locator('[name=email]').fill(role.toLowerCase() + '.console@example.test');
  await page.locator('[name=password]').fill('ConsoleDemo2026!');
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/staff_home.php');
  return page;
}
try {
  const manager = await staff('MANAGER');
  assert(await manager.getByRole('heading', { name: 'Manager console' }).isVisible());
  assert.equal((await manager.request.get(url + '/admin/staff.php')).status(), 403);
  assert.equal(
    (await manager.request.get(url + '/admin/device.php?id=' + fixture.outside)).status(),
    404
  );
  await manager.goto(url + '/admin/device.php?id=' + fixture.device);
  assert.equal(await manager.getByRole('heading', { name: 'Internal repair history' }).count(), 0);
  await manager.locator('#technician').selectOption(String(fixture.accounts.TECHNICIAN.user_id));
  await manager.getByRole('button', { name: 'Review assignment' }).click();
  await manager.getByRole('button', { name: 'Confirm change' }).click();
  assert(await manager.getByRole('status').isVisible());
  const tech = await staff('TECHNICIAN');
  await tech.screenshot({ path: site + '/technician-console.png', fullPage: true });
  await tech.goto(url + '/admin/device.php?id=' + fixture.device);
  await tech.locator('#repair-note').fill('Browser private repair note');
  await tech.getByRole('button', { name: 'Review progress' }).click();
  const replay = await tech.locator('form').evaluate((f) => Object.fromEntries(new FormData(f)));
  await tech.getByRole('button', { name: 'Confirm change' }).click();
  assert(await tech.getByText('Browser private repair note', { exact: true }).isVisible());
  assert.equal(
    (
      await tech.request.post(url + '/admin/device.php', { form: { ...replay, intent: 'save' } })
    ).status(),
    409
  );
  assert.equal(
    (await tech.request.post(url + '/admin/device.php', { form: { csrf_token: 'bad' } })).status(),
    403
  );
  await tech.setViewportSize({ width: 390, height: 844 });
  assert(await tech.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  await tech.screenshot({ path: site + '/technician-mobile.png', fullPage: true });

  const viewer = await staff('VIEWER');
  await viewer.goto(url + '/admin/device.php?id=' + fixture.device);
  assert.equal(await viewer.getByText('Browser private repair note', { exact: true }).count(), 0);
  assert.equal(await viewer.getByRole('button', { name: 'Review progress' }).count(), 0);
  assert.equal((await viewer.request.get(url + '/admin/intake_create.php')).status(), 403);
  const courier = await staff('COURIER');
  assert(await courier.getByRole('heading', { name: 'Courier console' }).isVisible());
  assert.equal(
    (await courier.request.get(url + '/admin/device.php?id=' + fixture.device)).status(),
    403
  );
  await courier.screenshot({ path: site + '/courier-console.png', fullPage: true });

  const intake = await staff('INTAKE_AGENT');
  await intake.getByRole('link', { name: 'New device intake' }).click();
  await intake.locator('[name=customer_name]').fill('Menu Browser Customer');
  await intake.locator('[name=contact_value]').fill('09178889999');
  await intake.locator('[name=device_type]').selectOption('Other');
  await intake.locator('[name=device_other]').fill('Handheld PC');
  await intake.locator('[name=make]').fill('Valve');
  await intake.locator('[name="problems[]"][value="Other"]').check();
  await intake.locator('[name="conditions[]"][value="No visible damage"]').check();
  await intake.getByRole('button', { name: 'Review details' }).click();
  assert((await intake.getByRole('alert').textContent()).includes('Describe Other'));
  await intake.locator('[name=reported_symptoms]').fill('Left trigger sticks');
  await intake.getByRole('button', { name: 'Review details' }).click();
  assert(await intake.getByRole('heading', { name: 'Review with the customer' }).isVisible());
  assert((await intake.locator('.intake-review').textContent()).includes('Handheld PC'));
  await intake.getByRole('button', { name: 'Edit details' }).click();
  assert(await intake.locator('[name="problems[]"][value="Other"]').isChecked());
  assert.equal(
    await intake.locator('[name=reported_symptoms]').inputValue(),
    'Left trigger sticks'
  );
  await intake.setViewportSize({ width: 390, height: 844 });
  assert(await intake.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  await intake.screenshot({ path: site + '/quick-intake-mobile.png', fullPage: true });
  await intake.getByRole('button', { name: 'Review details' }).click();
  await intake.locator('[name=confirm_review]').check();
  await intake.getByRole('button', { name: 'Save intake draft' }).click();
  await intake.waitForURL('**/staff_home.php');
  assert(await intake.getByRole('status').isVisible());
  await intake.locator('#drafts a[href*="intake_create"]').first().click();
  await intake.getByRole('link', { name: 'Complete checks and issue ticket' }).click();
  assert.equal(await intake.locator('input[type=file]').count(), 2);

  const jsContext = await browser.newContext({
    storageState: await intake.context().storageState(),
  });
  await jsContext.route('**/*', (r) =>
    r.request().url().startsWith(url) ? r.continue() : r.abort()
  );
  const quick = await jsContext.newPage();
  await quick.goto(url + '/admin/intake_create.php');
  await quick.locator('[name=customer_name]').fill('JS Menu Customer');
  await quick.locator('[name=contact_value]').fill('09170001111');
  await quick.getByRole('button', { name: 'Continue to device' }).click();
  await quick.locator('[name=device_type]').selectOption('Laptop');
  await quick.locator('[name=make]').fill('Acer');
  await quick.locator('[name="problems[]"][value="Will not turn on"]').check();
  await quick.locator('[name="conditions[]"][value="No visible damage"]').check();
  await quick.screenshot({ path: site + '/quick-intake-desktop.png', fullPage: true });
  await quick.getByRole('button', { name: 'Review details' }).click();
  assert(await quick.getByRole('heading', { name: 'Review with the customer' }).isVisible());
  console.log(
    'PASS: five staff consoles, role/branch rejection, assignment, private notes, review/replay/CSRF, mobile and no-JS quick intake including Other.'
  );
} finally {
  await browser.close();
}
