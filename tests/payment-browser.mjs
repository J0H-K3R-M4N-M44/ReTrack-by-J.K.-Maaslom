import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { chromium } from 'playwright-core';

const base = 'http://127.0.0.1:8742';
const fixture = JSON.parse(readFileSync(process.env.PICKUP_FIXTURE, 'utf8'));
const browser = await chromium.launch({ channel: 'chrome' });

async function login(role, width) {
  const page = await browser.newPage({ viewport: { width, height: 900 } });
  page.setDefaultTimeout(12000);
  await page.route('**/*', route => {
    return route.request().url().startsWith(base + '/') ? route.continue() : route.abort();
  });
  await page.goto(base + '/admin/login.php');
  await page.locator('#email').fill(fixture[role]);
  await page.locator('#password').fill(fixture.password);
  await press(page, 'Login');
  await page.waitForURL('**/staff_home.php');
  return page;
}

async function press(page, name) {
  await page.getByRole('button', { name, exact: true }).click();
}

async function checkWidth(page) {
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
    'Page exceeds viewport: ' + page.url());
}

try {
  const manager = await login('manager', 1440);
  const sidebar = await manager.locator('.rt-side').boundingBox();
  const main = await manager.locator('#rt-main').boundingBox();
  assert(main.x >= sidebar.x + sidebar.width, 'Sidebar covers the queue');
  await manager.getByRole('link', { name: 'Open device' }).click();
  await manager.getByRole('link', { name: 'Open billing and handover' }).click();
  await manager.locator('[name=confirm]').check();
  await press(manager, 'Review bill issuance');
  await press(manager, 'Issue final bill');
  await manager.getByRole('heading', { name: 'Record counter cash' }).waitFor();

  const cashier = await login('cashier', 360);
  await checkWidth(cashier);
  await cashier.locator('#rt-more').click();
  await cashier.locator('#rt-search').fill('cash');
  await cashier.locator('#rt-menu nav').getByRole('link', { name: 'Cash desk' }).click();
  await cashier.locator('#queue-search').fill(fixture.ticket);
  await press(cashier, 'Search');
  await cashier.getByRole('link', { name: 'Open cash record' }).click();
  await checkWidth(cashier);
  assert.equal(await cashier.locator('#cash-amount').inputValue(), '100.00');
  await cashier.locator('[name=confirm]').check();
  await press(cashier, 'Review payment');
  await press(cashier, 'Confirm cash received');
  await cashier.locator('#recipient').fill('Pickup Test');
  await cashier.locator('[name=condition_confirmed]').check();
  await press(cashier, 'Review handover');
  await press(cashier, 'Confirm handover and close ticket');
  await cashier.getByRole('heading', { name: 'Ticket closed' }).waitFor();
  await checkWidth(cashier);
  console.log('PASS: manager billing at 1440px, cashier search, payment and pickup at 360px.');
} finally {
  await browser.close();
}
