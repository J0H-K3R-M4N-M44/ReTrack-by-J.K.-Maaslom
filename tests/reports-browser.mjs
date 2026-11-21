import assert from 'node:assert/strict';
import { mkdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { chromium } from 'playwright-core';

const base = process.env.REPAIRTRACK_TEST_URL;
assert(base && ['localhost', '127.0.0.1'].includes(new URL(base).hostname));
const fixture = JSON.parse(readFileSync(process.env.REPAIRTRACK_REPORT_FIXTURE, 'utf8'));
const pictures = process.env.REPORT_SCREENSHOTS;
if (pictures) mkdirSync(pictures, { recursive: true });
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH,
  channel: process.env.CHROME_PATH ? undefined : 'chrome',
  args: ['--no-sandbox'],
});
const pageErrors = [];

async function session(options = {}) {
  const context = await browser.newContext({
    viewport: { width: 1440, height: 1000 },
    colorScheme: 'dark',
    ...options,
  });
  await context.route('**/*', (route) => {
    const local = new URL(route.request().url()).origin === new URL(base).origin;
    return local ? route.continue() : route.abort();
  });
  const page = await context.newPage();
  page.on('pageerror', (error) => pageErrors.push(error.message));
  return page;
}

async function login(key, options) {
  const page = await session(options);
  await page.goto(base + '/admin/login.php');
  await page.locator('#email').fill(fixture.emails[key]);
  await page.locator('#password').fill(fixture.password);
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL(key === 'admin' ? '**/dashboard.php' : '**/staff_home.php');
  return page;
}

async function openReports(page, key) {
  if (key === 'admin') {
    await page.getByRole('link', { name: 'Reports and records' }).click();
  } else {
    await page.getByRole('navigation', { name: 'Workspace pages' }).getByRole('link', { name: 'Reports' }).click();
  }
  await page.waitForURL('**/admin/reports.php');
}

async function apply(page, days, branch) {
  await page.getByLabel('Reporting period').selectOption(String(days));
  await page.locator('select[name="branch"]').selectOption(String(branch));
  await page.getByRole('button', { name: 'Apply filters' }).click();
}

async function totals(page) {
  return page.locator('.report-kpis dd:first-of-type').allTextContents();
}

async function checkWidth(page, width) {
  await page.setViewportSize({ width, height: 900 });
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Page overflow at ' + width);
  assert(await page.locator('main').evaluate((el) => el.getBoundingClientRect().right <= innerWidth + 1));
}

async function screenshot(page, name) {
  if (pictures) await page.screenshot({ path: join(pictures, name + '.png') });
}

const { market, station, quiet, outside, inactive } = fixture.branches;
try {
  const guest = await session();
  await guest.goto(base + '/admin/reports.php');
  assert(guest.url().endsWith('/login.php'));
  await guest.context().close();

  const manager = await login('manager');
  await openReports(manager, 'manager');
  assert.deepEqual(await totals(manager), ['4', '4', '2']);
  assert.equal(await manager.locator('#daily tbody tr').count(), 7);
  assert.equal(await manager.locator('select[name="branch"] option').count(), 4);
  assert.equal(await manager.locator('.rt-side [aria-current="page"]').innerText(), 'Reports');

  await apply(manager, 30, market);
  assert.equal(await manager.locator('#daily tbody tr').count(), 10);
  assert.equal(await manager.locator('.report-point').count(), 6);
  const firstDate = await manager.locator('#daily time').first().getAttribute('datetime');
  await manager.getByRole('link', { name: 'Next', exact: true }).click();
  const pageTwo = new URL(manager.url());
  assert.deepEqual([pageTwo.searchParams.get('days'), pageTwo.searchParams.get('branch'),
    pageTwo.searchParams.get('page'), pageTwo.hash], ['30', String(market), '2', '#daily']);
  assert((await manager.locator('#daily time').first().getAttribute('datetime')) < firstDate);
  await manager.getByRole('link', { name: 'Previous', exact: true }).click();
  assert.equal(await manager.locator('#daily time').first().getAttribute('datetime'), firstDate);

  await apply(manager, 7, market);
  assert.deepEqual(await totals(manager), ['3', '3', '2']);
  const date = manager.locator('.report-point summary').first();
  await date.hover();
  assert(await manager.locator('.report-point').first().evaluate((el) => el.open));
  await date.focus();
  await manager.keyboard.press('Escape');
  assert(!(await manager.locator('.report-point').first().evaluate((el) => el.open)));
  await manager.locator('h1').hover();
  await manager.keyboard.press('Tab');
  assert(await manager.locator('.report-point').nth(1).evaluate((el) => el.open));
  await manager.keyboard.press('Escape');
  await manager.locator('.report-status summary').click();
  assert(await manager.getByText('Completed', { exact: true }).isVisible());
  await manager.locator('h1').hover();
  await screenshot(manager, 'staff-report-desktop');
  for (const width of [1280, 1024, 768, 430, 390, 360, 320]) await checkWidth(manager, width);
  await manager.setViewportSize({ width: 1440, height: 1000 });

  await apply(manager, 7, quiet);
  assert(await manager.getByText('No recorded activity in this period.').isVisible());
  assert.deepEqual(await totals(manager), ['0', '0', '0']);
  await manager.getByRole('link', { name: 'View the last 90 days' }).click();
  assert.equal(await manager.locator('select[name="branch"]').inputValue(), String(quiet));
  assert.equal(await manager.getByLabel('Reporting period').inputValue(), '90');
  for (const branch of [outside, inactive]) {
    const response = await manager.goto(base + '/admin/reports.php?days=30&branch=' + branch);
    assert.equal(response.status(), 403);
    assert.equal(await manager.locator('.report-kpis').count(), 0);
    await manager.getByRole('link', { name: 'View my assigned branches' }).click();
    assert.equal(await manager.getByLabel('Reporting period').inputValue(), '30');
  }
  const alias = base + '/admin/staff_home.php?view=reports&days=90&branch=' + market + '&page=9';
  await manager.goto(alias);
  assert(new URL(manager.url()).pathname.endsWith('/reports.php'));
  assert(await manager.getByText('Page 9 of 9').isVisible());
  assert.equal(await manager.request.post(base + '/admin/reports.php').then((r) => r.status()), 405);

  const admin = await login('admin');
  await openReports(admin, 'admin');
  assert.deepEqual(await totals(admin), ['4', '4', '2']);
  await admin.goto(base + '/admin/dashboard.php?tab=reports&days=30&branch=' + market + '&page=2');
  assert(await admin.getByText('Page 2 of 3').isVisible());
  await admin.context().close();

  const technician = await login('tech');
  await openReports(technician, 'tech');
  await apply(technician, 7, market);
  assert.deepEqual(await totals(technician), ['2', '2', '1']);
  assert(await technician.getByRole('heading', { name: 'Assigned workload' }).isVisible());
  assert.equal(await technician.getByText('Cash collected', { exact: true }).count(), 0);
  await technician.locator('.report-status summary').click();
  await screenshot(technician, 'technician-report-desktop');
  await technician.setViewportSize({ width: 390, height: 844 });
  await technician.goto(base + '/admin/learning.php');
  await technician.getByRole('link', { name: 'More', exact: false }).click();
  await technician.getByRole('navigation', { name: 'All destinations' }).getByRole('link', { name: 'Reports' }).click();
  await apply(technician, 7, market);
  await screenshot(technician, 'technician-report-mobile');
  await technician.setViewportSize({ width: 390, height: 915 });
  await apply(technician, 30, market);
  await technician.getByRole('link', { name: 'View daily figures' }).click();
  await screenshot(technician, 'daily-report-mobile');
  await technician.context().close();

  const mobile = await login('cash', {
    viewport: { width: 360, height: 800 }, hasTouch: true, isMobile: true, colorScheme: 'light',
  });
  await mobile.getByRole('navigation', { name: 'Mobile shortcuts' }).getByRole('link', { name: 'Reports' }).tap();
  await apply(mobile, 7, market);
  assert.deepEqual(await totals(mobile), ['₱1,000.20', '3', '2']);
  assert.equal(await mobile.locator('.report-balance').innerText(), '₱90.00');
  const point = mobile.locator('.report-point').first();
  await point.locator('summary').tap();
  assert(await point.evaluate((el) => el.open));
  assert(await point.locator('.report-tip').isVisible());
  await point.locator('summary').tap();
  await checkWidth(mobile, 360);
  await mobile.evaluate(() => window.scrollTo(0, 0));
  await screenshot(mobile, 'cash-report-mobile');
  await apply(mobile, 7, station);
  assert.deepEqual(await totals(mobile), ['₱9.99', '1', '0']);
  assert.equal(await mobile.locator('.report-balance').innerText(), '₱30.01');
  await mobile.context().close();

  const plain = await session({ javaScriptEnabled: false, storageState: await manager.context().storageState() });
  await plain.goto(base + '/admin/reports.php');
  await apply(plain, 90, station);
  await plain.getByRole('link', { name: 'Next', exact: true }).click();
  assert(await plain.getByText('Page 2 of 9').isVisible());
  assert.equal(await plain.locator('select[name="branch"]').inputValue(), String(station));
  await plain.context().close();
  await manager.context().close();

  const detached = await login('detached');
  await openReports(detached, 'detached');
  assert(await detached.getByRole('heading', { name: 'No active branch assigned' }).isVisible());
  assert.equal(await detached.locator('.report-kpis').count(), 0);
  await detached.context().close();

  for (const key of ['viewer', 'courier', 'intake']) {
    const page = await login(key);
    assert.equal(await page.locator('.rt-side a[href="reports.php"]').count(), 0);
    const denied = await page.goto(base + '/admin/reports.php');
    assert.equal(denied.status(), 403);
    assert.equal(await page.locator('.report-kpis').count(), 0);
    await page.context().close();
  }
  assert.deepEqual(pageErrors, []);
  console.log('PASS report browser checks: login, navigation, role and branch access, filters, paging, interaction, mobile and JavaScript disabled.');
} finally {
  await browser.close();
}
