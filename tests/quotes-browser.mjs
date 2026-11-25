import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright-core';
const url = process.env.REPAIRTRACK_TEST_URL,
  site = process.env.REPAIRTRACK_TEST_SITE;
assert(url && ['localhost', '127.0.0.1'].includes(new URL(url).hostname));
const fixture = JSON.parse(fs.readFileSync(site + '/tests/console-fixture.json'));
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
  await page.locator('#email').fill('manager.console@example.test');
  await page.locator('#password').fill('ConsoleDemo2026!');
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/staff_home.php');
  await page.goto(url + '/admin/device.php?id=' + fixture.device);
  await page.getByRole('link', { name: 'Open estimate', exact: true }).click();
  const bad = await page.request.post(url + '/admin/quote.php?id=' + fixture.device, {
    form: { csrf_token: 'bad' },
  });
  assert.equal(bad.status(), 403);
  await page.locator('.quote-editor summary').click();
  for (const width of [390, 320]) {
    await page.setViewportSize({ width, height: 900 });
    const fieldSizes = await page.locator(
      '.quote-editor .quote-form input:not([type=hidden]):not([type=checkbox]):not([type=radio]), ' +
      '.quote-editor .quote-form select, .quote-editor .quote-form textarea'
    ).evaluateAll(elements => elements
      .filter(element => element.getClientRects().length > 0)
      .map(element => ({
        name: element.name,
        height: element.getBoundingClientRect().height,
      })));
    assert(fieldSizes.length > 0 &&
      fieldSizes.every(item => item.height >= 44),
      'Quote form control below 44px at ' + width + 'px: ' + JSON.stringify(fieldSizes));
  }
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page
    .locator('[name=labor_description]')
    .fill('Diagnose cooling issue <img src=x onerror=alert(1)>');
  await page.locator('[name=labor_amount]').fill('125.15');
  await page.locator('[name=parts_description]').fill('Replacement fan');
  await page.locator('[name=parts_amount]').fill('200.20');
  await page.getByRole('button', { name: 'Review draft', exact: true }).click();
  await page.getByRole('heading', { name: 'Review before saving' }).waitFor();
  assert((await page.locator('body').innerText()).includes('325.35'));
  assert.equal(await page.locator('img[src=x]').count(), 0);
  await page.getByRole('button', { name: 'Go back', exact: true }).click();
  assert.equal(await page.locator('[name=labor_amount]').inputValue(), '125.15');
  await page.getByRole('button', { name: 'Review draft', exact: true }).click();
  const form = page.locator('form:has([name=intent][value=save])');
  const replay = {
    csrf_token: await form.locator('[name=csrf_token]').inputValue(),
    form_key: await form.locator('[name=form_key]').inputValue(),
    intent: 'save',
  };
  await page.getByRole('button', { name: 'Confirm and save' }).click();
  await page.getByRole('status').waitFor();
  assert.equal((await page.request.post(url + '/admin/quote.php', { form: replay })).status(), 409);
  const share = page.locator('form:has([name=action][value=share])');
  await share.locator('[name=confirm]').check();
  await share.getByRole('button', { name: 'Review sharing' }).click();
  await page.getByRole('button', { name: 'Confirm and save' }).click();
  await page.getByRole('heading', { name: 'Customer response', exact: true }).waitFor();
  assert(
    (await page.locator('.quote-steps [aria-current=step]').innerText()).includes('Record response')
  );
  for (const width of [1440, 768, 390, 320]) {
    await page.setViewportSize({ width, height: 1000 });
    assert(
      await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      'Quote overflow ' + width
    );
    if (width === 1440 || width === 390)
      await page.screenshot({ path: site + '/quote-' + width + '.png', fullPage: true });
  }
  const response = page.locator('form:has([name=action][value=decision])');
  await response.locator('[name=decision]').selectOption('APPROVED');
  await response.locator('[name=customer_name]').fill('Demo Customer');
  await response
    .locator('[name=reference]')
    .fill('Customer confirmed displayed revision in person.');
  await response.locator('[name=confirm]').check();
  await response.getByRole('button', { name: 'Review response' }).click();
  await page.getByRole('button', { name: 'Confirm and save' }).click();
  await page.getByRole('heading', { name: 'Approved — ready for the technician' }).waitFor();
  const nojs = await browser.newContext({
    javaScriptEnabled: false,
    storageState: await context.storageState(),
    viewport: { width: 390, height: 844 },
  });
  const plain = await nojs.newPage();
  await plain.goto(url + '/admin/quote.php?id=' + fixture.device);
  await plain.getByRole('heading', { name: 'Current estimate' }).waitFor();
  assert(await plain.getByRole('link', { name: 'Open repair progress' }).isVisible());
  const tech = await browser.newPage();
  await tech.goto(url + '/admin/login.php');
  await tech.locator('#email').fill('technician.console@example.test');
  await tech.locator('#password').fill('ConsoleDemo2026!');
  await tech.getByRole('button', { name: 'Login', exact: true }).click();
  await tech.waitForURL('**/staff_home.php');
  await tech.goto(url + '/admin/device.php?id=' + fixture.device);
  await tech.locator('#repair-status').selectOption('IN_PROGRESS');
  await tech.getByRole('button', { name: 'Review progress' }).click();
  await tech.getByRole('button', { name: 'Confirm change' }).click();
  await tech.getByRole('status').waitFor();
  assert.equal(await tech.locator('#repair-status').inputValue(), 'IN_PROGRESS');
  assert.deepEqual(errors, []);
  console.log(
    'PASS: quote review/edit/save, CSRF/replay, escaped text, responsive/no-JS, recorded approval and technician start.'
  );
} finally {
  await browser.close();
}
