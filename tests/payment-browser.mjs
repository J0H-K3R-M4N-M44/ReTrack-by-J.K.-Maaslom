import assert from 'node:assert/strict';
import { mkdirSync, readFileSync } from 'node:fs';
import { chromium } from 'playwright-core';

const base = 'http://127.0.0.1:8742';
const fixture = JSON.parse(readFileSync(process.env.PICKUP_FIXTURE, 'utf8'));
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH,
  channel: process.env.CHROME_PATH ? undefined : 'chrome',
});

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
  await page.waitForURL(role === 'admin' ? '**/dashboard.php' : '**/staff_home.php');
  return page;
}

async function press(page, name) {
  await page.getByRole('button', { name, exact: true }).click();
}

async function checkWidth(page) {
  await page.waitForLoadState('load');
  const overflow = await page.evaluate(() => {
    if (document.documentElement.scrollWidth <= innerWidth) return null;
    return [...document.querySelectorAll('body *')]
      .filter(element => element.getBoundingClientRect().right > innerWidth)
      .map(element => element.tagName + '.' + element.className);
  });
  assert.equal(overflow, null, 'Page exceeds viewport: ' + page.url() + ' ' + (overflow || []).join(', '));
}

async function checkRole(role, destination) {
  const page = await login(role, 1440);
  if (role === 'admin') {
    await page.getByRole('link', { name: 'Staff workspace', exact: true }).click();
  }
  const screenshots = process.env.RUNNER_TEMP + '/workspace-screens';
  mkdirSync(screenshots, { recursive: true });
  for (const width of [1440, 360]) {
    await page.setViewportSize({ width, height: 900 });
    await checkWidth(page);
    const nav = page.locator(width === 1440 ? '.rt-side' : '#rt-menu nav');
    if (width === 360) await page.locator('#rt-more').click();
    const cashLink = page.locator('#rt-menu nav a[href$="view=cash"]');
    assert.equal(await cashLink.count(), role === 'admin' ? 1 : 0);
    await nav.getByRole('link', { name: destination, exact: true }).click();
    await page.locator('#work-list').waitFor();
    await checkWidth(page);
    await page.screenshot({ path: screenshots + '/' + role + '-' + width + '.png' });
  }
  await page.context().close();
}

try {
  const manager = await login('manager', 1440);
  // Exercise shelf quarantine and a verified count through the real manager UI.
  await manager.goto(base + '/admin/inventory.php?branch=' + fixture.branch);
  await manager.getByRole('heading', { name: 'Branch inventory' }).waitFor();
  await checkWidth(manager);
  const battery = manager.locator('article.dashboard-card').filter({ hasText: 'Quarantine test battery' });
  await battery.locator('summary').filter({ hasText: 'Quarantine damaged stock' }).click();
  const quarantine = battery.locator('form').filter({ has: manager.locator('button[value=quarantine]') });
  await quarantine.locator('[name=quantity]').fill('1');
  await quarantine.locator('[name=reason]').fill('Damaged battery inspected and isolated in quarantine shelf.');
  await quarantine.locator('[name=confirm]').check();
  await press(manager,'Quarantine damaged items');
  await manager.getByRole('status').getByText('Damaged stock isolated', { exact: false }).waitFor();
  const afterQuarantine = manager.locator('article.dashboard-card').filter({ hasText: 'Quarantine test battery' });
  await afterQuarantine.getByText('2.000', { exact: false }).first().waitFor();
  await afterQuarantine.locator('summary').filter({ hasText: 'Record a verified shelf count' }).click();
  const recount = afterQuarantine.locator('form').filter({ has: manager.locator('button[value=count]') });
  await recount.locator('[name=quantity]').fill('3');
  await recount.locator('[name=reason]').fill('Recount found one additional reusable battery on the shelf.');
  await recount.locator('[name=confirm]').check();
  await press(manager,'Save verified count');
  await manager.getByRole('status').getByText('Verified shelf count recorded', { exact: false }).waitFor();
  await manager.goto(base + '/admin/staff_home.php');
  const sidebar = await manager.locator('.rt-side').boundingBox();
  const main = await manager.locator('#rt-main').boundingBox();
  assert(main.x >= sidebar.x + sidebar.width, 'Sidebar covers the queue');
  await manager.locator('.rt-side').getByRole('link', { name: 'Status board' }).click();
  await manager.setViewportSize({ width: 360, height: 900 });
  const preview = manager.locator('.board-preview').first();
  const summary = preview.locator('summary');
  await summary.focus();
  await summary.press('Space');
  await preview.getByText(fixture.ticket, { exact: true }).waitFor();
  await checkWidth(manager);
  await summary.press('Enter');
  assert.equal(await preview.locator('dl').isVisible(), false);
  await summary.click();
  await preview.locator('dl').waitFor();
  await manager.setViewportSize({ width: 1440, height: 900 });
  await manager.locator('.rt-side').getByRole('link', { name: 'Repair queue' }).click();
  await manager.getByRole('link', { name: 'Open device' }).click();
  await manager.getByRole('link', {name:'Open estimate'}).click();
  await manager.locator('summary').filter({hasText:'Prepare a revised estimate'}).click();
  await manager.locator('[name=labor_description]').fill('Diagnosis');
  await manager.locator('[name=labor_amount]').fill('350');
  await manager.locator('[name=part_id]').selectOption(String(fixture.part));
  await manager.locator('[name=parts_amount]').fill('2400');
  await press(manager,'Review draft');
  await press(manager,'Confirm and save');
  await manager.locator('[name=confirm]').check();
  await press(manager,'Review sharing');
  await press(manager,'Confirm and save');
  await manager.locator('[name=decision]').selectOption('APPROVED');
  await manager.locator('[name=customer_name]').fill('Pickup Test');
  await manager.locator('[name=reference]').fill('Customer agreed at counter');
  await manager.locator('[name=confirm]').check();
  await press(manager,'Review response');
  await press(manager,'Confirm and save');
  await press(manager,'Reserve quoted quantity');
  await manager.getByText('Reserved for this device', {exact:false}).waitFor();
  const deviceUrl = base + '/admin/device.php?id=' + fixture.device;
  const tech = await login('technician',360);
  async function stage(status) {
    await tech.goto(deviceUrl);
    await tech.locator('select[name=status]').selectOption(status);
    await press(tech,'Review progress');
    await press(tech,'Confirm change');
  }
  await stage('IN_PROGRESS');
  await tech.getByRole('link',{name:'Open estimate'}).click();
  await press(tech,'Record installation');
  await tech.getByText('available · Installed', {exact:false}).waitFor();
  // Manager verifies physical restock; technician must install the replacement before testing.
  const quoteUrl = base + '/admin/quote.php?id=' + fixture.device;
  await manager.goto(quoteUrl);
  await manager.locator('[name=return_reason]').fill('Removed and inspected reusable SSD before final testing.');
  await manager.locator('[name=restock_confirm]').check();
  await press(manager,'Record part return to stock');
  await manager.getByText('Awaiting reservation', {exact:false}).waitFor();
  await press(manager,'Reserve quoted quantity');
  await manager.getByText('Reserved for this device', {exact:false}).waitFor();
  await tech.goto(quoteUrl);
  await press(tech,'Record installation');
  await tech.getByText('available · Installed', {exact:false}).waitFor();
  await stage('TESTING');
  await stage('READY_FOR_PICKUP');
  await tech.context().close();
  await manager.goto(deviceUrl);
  await manager.getByRole('link', { name: 'Open billing and handover' }).click();
  await manager.locator('[name=confirm]').check();
  await press(manager, 'Review bill issuance');
  await press(manager, 'Issue final bill');
  await manager.getByRole('heading', { name: 'Record counter cash' }).waitFor();

  const cashier = await login('cashier', 360);
  const forbidden = await cashier.goto(base + '/admin/inventory.php');
  assert.equal(forbidden.status(), 403, 'Cashier gained access to branch inventory.');
  await cashier.goto(base + '/admin/staff_home.php?view=cash');
  await checkWidth(cashier);
  await cashier.locator('#rt-more').click();
  await cashier.locator('#rt-search').fill('cash');
  await cashier.locator('#rt-menu nav').getByRole('link', { name: 'Cash desk' }).click();
  await cashier.locator('#queue-search').fill(fixture.ticket);
  await press(cashier, 'Search');
  await cashier.getByRole('link', { name: 'Open cash record' }).click();
  await cashier.getByRole('heading', { name: 'Record counter cash' }).waitFor();
  await cashier.waitForLoadState('load');
  await checkWidth(cashier);
  assert.equal(await cashier.locator('#cash-amount').inputValue(), '2750.00');
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
  for (const [role, destination] of Object.entries({
    admin: 'Repair queue', intake_agent: 'Saved drafts', technician: 'Status board',
    courier: 'History', viewer: 'Status board',
  })) {
    await checkRole(role, destination);
    console.log('PASS: desktop and mobile navigation for ' + role);
  }
} finally {
  await browser.close();
}
