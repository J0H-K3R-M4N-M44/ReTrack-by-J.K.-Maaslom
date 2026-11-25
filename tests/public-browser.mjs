import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright-core';
const url = process.env.REPAIRTRACK_TEST_URL;
const site = process.env.REPAIRTRACK_TEST_SITE;
assert(url && ['localhost', '127.0.0.1'].includes(new URL(url).hostname));
const fixture = JSON.parse(fs.readFileSync(site + '/tests/public-fixture.json'));
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
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto(url + '/track.php#code=' + fixture.code);
  assert.equal(await page.locator('#tracking-code').inputValue(), fixture.code);
  assert.equal(new URL(page.url()).hash, '');
  await page.getByRole('button', { name: 'View repair' }).click();
  await page.getByRole('heading', { name: 'Your repair update' }).waitFor();
  assert(await page.getByRole('heading', { name: 'Current shared estimate' }).isVisible());
  assert((await page.locator('.public-estimate').innerText()).includes('99.00'));
  assert(!(await page.locator('.public-estimate').innerText()).includes('325.35'));
  assert(await page.getByRole('heading', { name: 'Next step' }).first().isVisible());
  assert(!(await page.content()).includes('PRIVATE_SENTINEL'));
  assert.equal(
    await page.locator('script:not([src])').count(),
    0,
    'Customer message became executable'
  );
  for (const width of [1440, 768, 390, 320]) {
    await page.setViewportSize({ width, height: 900 });
    assert(
      await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      `Tracking overflow at ${width}`
    );
  }
  await page.screenshot({ path: site + '/public-tracking-mobile.png', fullPage: true });
  await page.getByRole('button', { name: 'Close tracking' }).click();
  assert(await page.locator('#tracking-code').isVisible());
  const nojs = await browser.newContext({
    javaScriptEnabled: false,
    viewport: { width: 390, height: 844 },
  });
  const visitor = await nojs.newPage();
  await visitor.goto(url + '/track.php');
  await visitor.locator('#tracking-code').fill(fixture.code);
  await visitor.getByRole('button', { name: 'View repair' }).click();
  assert(await visitor.getByRole('heading', { name: 'Your repair update' }).isVisible());
  assert((await visitor.locator('.public-estimate').innerText()).includes('99.00'));
  await page.goto(url + '/admin/login.php');
  await page.locator('[name=email]').fill('manager.console@example.test');
  await page.locator('[name=password]').fill(['ConsoleDemo', '2026!'].join(''));
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/staff_home.php');
  await page.locator('#queue-status').selectOption('DIAGNOSING');
  await page.getByRole('button', { name: 'Search', exact: true }).click();
  assert.equal(new URL(page.url()).searchParams.get('status'), 'DIAGNOSING');
  for (const width of [1440, 390, 320]) {
    await page.setViewportSize({ width, height: 900 });
    assert(
      await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      `Console overflow at ${width}`
    );
  }
  await page.locator('#queue-status').selectOption('');
  await page.getByRole('button', { name: 'Search', exact: true }).click();
  await page.waitForURL(currentUrl => new URL(currentUrl).searchParams.get('status') !== 'DIAGNOSING');
  await page.setViewportSize({ width: 390, height: 900 });
  const mobileQueue = await page.locator('.workspace-table').evaluate(table => ({
    rowDisplay: getComputedStyle(table.tBodies[0].rows[0]).display,
    tableMinWidth: getComputedStyle(table).minWidth,
    tableRole: table.getAttribute('role'),
    rowRoles: [...table.tBodies[0].rows].every(row => row.getAttribute('role') === 'row'),
    cellRoles: [...table.tBodies[0].rows].every(row =>
      [...row.cells].every(cell => cell.getAttribute('role') === 'cell')),
    headerRoles: [...table.tHead.rows].every(row =>
      [...row.cells].every(cell => cell.getAttribute('role') === 'columnheader')),
    allCellsHaveLabels: [...table.tBodies[0].rows].every(row =>
      [...row.cells].every(cell => Boolean(cell.dataset.label))),
    wrapperFits: table.parentElement.scrollWidth <= table.parentElement.clientWidth + 1,
  }));
  assert.equal(mobileQueue.rowDisplay, 'grid', 'Mobile queue row did not become a record card');
  assert.equal(mobileQueue.tableMinWidth, '0px', 'Mobile queue retained a wide table minimum');
  assert.equal(mobileQueue.tableRole, 'table', 'Mobile queue must retain table semantics');
  assert(mobileQueue.rowRoles && mobileQueue.cellRoles && mobileQueue.headerRoles,
    'Mobile queue must retain row, cell and column-header roles');
  assert(mobileQueue.allCellsHaveLabels, 'Mobile queue cards are missing visible field labels');
  assert(mobileQueue.wrapperFits, 'Mobile queue still requires horizontal scrolling');

  // Verify that actual staff form controls follow the selected theme, not legacy defaults.
  await page.locator('#rt-menu summary').click();
  await page.locator('[data-rt-setting="theme"]').selectOption('linen');
  await page.locator('#rt-menu summary').click();
  const themedSearch = await page.locator('#queue-search').evaluate(element => ({
    background: getComputedStyle(element).backgroundColor,
    color: getComputedStyle(element).color,
    border: getComputedStyle(element).borderTopColor,
  }));
  assert.deepEqual(themedSearch, {
    background: 'rgb(255, 253, 249)',
    color: 'rgb(20, 32, 43)',
    border: 'rgb(131, 145, 151)',
  }, 'Staff queue search field does not follow Linen theme tokens');
  const themedFilter = await page.locator('#queue-status').evaluate(element => ({
    background: getComputedStyle(element).backgroundColor,
    color: getComputedStyle(element).color,
  }));
  assert.deepEqual(themedFilter, {
    background: 'rgb(255, 253, 249)',
    color: 'rgb(20, 32, 43)',
  }, 'Staff queue filter does not follow Linen theme tokens');

  await page.screenshot({ path: site + '/mvp-queue-mobile.png', fullPage: true });
  await page.goto(url + '/admin/ticket.php?id=' + fixture.ticket);
  assert.equal(
    (
      await page.request.post(url + '/admin/ticket.php?id=' + fixture.ticket, {
        form: { csrf_token: 'invalid', confirm_share: '1' },
      })
    ).status(),
    403
  );
  await page.locator('[name=confirm_share]').check();
  await page.getByRole('button', { name: 'Create tracking code and PIN' }).click();
  assert.equal(
    await page.locator('#private-code').count(),
    1,
    await page.locator('main').innerText()
  );
  assert.match(await page.locator('#private-code').inputValue(), /^[A-F0-9]{5}$/);
  await visitor.reload();
  assert(
    await visitor.locator('#tracking-code').isVisible(),
    'Existing session survived replacement'
  );
  await page.reload();
  assert.equal(
    await page.locator('#private-code').count(),
    0,
    'Private code was not one-time display'
  );
  assert.deepEqual(errors, []);
  console.log(
    'PASS: customer flow, escaped updates, no-JS access, revocation, code sharing/CSRF, filters and mobile layout.'
  );
} finally {
  await browser.close();
}
