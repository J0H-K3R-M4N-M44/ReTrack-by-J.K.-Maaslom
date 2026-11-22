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
  await manager.setViewportSize({ width: 360, height: 900 });
  await checkWidth(manager);
  await manager.setViewportSize({ width: 1440, height: 900 });
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
  // Quarantine recorder cannot approve their own restock. A distinct admin reviews it.
  assert.equal(await manager.locator('form').filter({
    has: manager.locator('input[name=quarantine_id]')
  }).count(),0,'Quarantine creator can approve their own decision.');
  const admin = await login('admin',1440);
  await admin.goto(base + '/admin/inventory.php?branch=' + fixture.branch);
  const disposition = admin.locator('form').filter({
    has: admin.locator('input[name=quarantine_id]')
  }).first();
  await disposition.locator('[name=decision]').selectOption('RELEASE');
  await disposition.locator('[name=reason]').fill(
    'Second-person inspection confirmed the previously isolated battery is reusable.');
  await disposition.locator('[name=confirm]').check();
  await press(admin,'Record disposition');
  await admin.getByRole('status').getByText('Independent quarantine disposition recorded').waitFor();
  // Five units of newly counted stock must wait for second-person approval.
  await manager.goto(base + '/admin/inventory.php?branch=' + fixture.branch);
  const nextBattery = manager.locator('article.dashboard-card').filter({ hasText: 'Quarantine test battery' });
  await nextBattery.locator('summary').filter({ hasText: 'Record a verified shelf count' }).click();
  const highCount = nextBattery.locator('form').filter({ has: manager.locator('button[value=count]') });
  await highCount.locator('[name=quantity]').fill('9');
  await highCount.locator('[name=reason]').fill('Repeated physical count verified five additional parts at this branch.');
  await highCount.locator('[name=confirm]').check();
  await press(manager,'Save verified count');
  await manager.getByRole('status').getByText('Large count difference queued', {exact:false}).waitFor();
  await admin.goto(base + '/admin/inventory.php?branch=' + fixture.branch);
  const countReview = admin.locator('form').filter({
    has: admin.locator('input[name=review_id]')
  }).first();
  await countReview.locator('[name=decision]').selectOption('approve');
  await countReview.locator('[name=reason]').fill(
    'Independent recount verified the additional usable batteries before approval.');
  await countReview.locator('[name=confirm]').check();
  await press(admin,'Record count decision');
  await admin.getByRole('status').getByText('Count review decision recorded').waitFor();
  await admin.getByText('9.000', {exact:false}).first().waitFor();
  // Cross-branch parts move out at dispatch and into the destination only on receipt.
  await manager.goto(base + '/admin/parts_transfers.php?branch=' + fixture.branch);
  await manager.getByRole('heading', { name: 'Physical parts transfers' }).waitFor();
  await checkWidth(manager);
  await manager.locator('summary').filter({ hasText: 'New parts manifest' }).click();
  const send = manager.locator('details').filter({ hasText: 'New parts manifest' }).locator('form');
  await send.locator('[name=destination]').selectOption(String(fixture.transfer_branch));
  await send.locator('[name=sku_lines]').fill(fixture.transfer_sku + ',2');
  await send.locator('[name=note]').fill('Sealed and counted physical battery shipment at sending branch.');
  await send.locator('[name=confirm]').check();
  await press(manager,'Record parts dispatch');
  await manager.getByRole('status').getByText('Parts dispatched under manifest', {exact:false}).waitFor();
  await admin.goto(base + '/admin/parts_transfers.php?branch=' + fixture.transfer_branch);
  await admin.getByRole('heading', { name: 'Physical parts transfers' }).waitFor();
  await admin.setViewportSize({width:360,height:900});
  await checkWidth(admin);
  const item = admin.locator('article.dashboard-card').filter({hasText:fixture.transfer_sku});
  await item.locator('summary').filter({hasText:'Confirm receipt or report shortage'}).click();
  const firstReceipt = item.locator('form').filter({has: admin.locator('button[value=receive]')});
  await firstReceipt.locator('[name=amount]').fill('1');
  await firstReceipt.locator('[name=note]').fill('One unit received; second expected unit missing from sealed box.');
  await firstReceipt.locator('[name=confirm]').check();
  await press(admin,'Record physical receipt');
  await admin.getByRole('status').getByText('Physical receipt recorded', {exact:false}).waitFor();
  await admin.getByText('Status EXCEPTION', {exact:false}).first().waitFor();
  const remaining = admin.locator('article.dashboard-card').filter({hasText:fixture.transfer_sku});
  await remaining.locator('summary').filter({hasText:'Confirm receipt or report shortage'}).click();
  const late = remaining.locator('form').filter({has: admin.locator('button[value=receive]')});
  await late.locator('[name=amount]').fill('1');
  await late.locator('[name=note]').fill('Missing sealed battery arrived and was physically counted.');
  await late.locator('[name=confirm]').check();
  await press(admin,'Record physical receipt');
  await admin.getByText('Status RECEIVED', {exact:false}).first().waitFor();
  // A second shipment is independently investigated; no missing part is received
  // or credited merely because the source branch accepts an operational loss.
  await manager.goto(base + '/admin/parts_transfers.php?branch=' + fixture.branch);
  await manager.locator('summary').filter({hasText:'New parts manifest'}).click();
  const lossDispatch = manager.locator('details').filter({hasText:'New parts manifest'}).locator('form');
  await lossDispatch.locator('[name=destination]').selectOption(String(fixture.transfer_branch));
  await lossDispatch.locator('[name=sku_lines]').fill(fixture.transfer_sku + ',1');
  await lossDispatch.locator('[name=note]').fill('Second independently counted battery shipment with signed seal reference.');
  await lossDispatch.locator('[name=confirm]').check();
  await press(manager,'Record parts dispatch');
  await manager.getByRole('status').getByText('Parts dispatched under manifest',{exact:false}).waitFor();
  await admin.goto(base + '/admin/parts_transfers.php?branch=' + fixture.transfer_branch);
  const missingItem = admin.locator('article.dashboard-card').filter({hasText:fixture.transfer_sku}).first();
  await missingItem.locator('summary').filter({hasText:'Confirm receipt or report shortage'}).click();
  const missingForm = missingItem.locator('form').filter({has:admin.locator('button[value=missing]')});
  await missingForm.locator('[name=note]').fill('Courier and receiving manager verified a missing sealed battery case.');
  await missingForm.locator('[name=confirm]').check();
  await missingForm.locator('button[value=missing]').click();
  await admin.getByRole('status').getByText('Missing-part discrepancy recorded',{exact:false}).waitFor();
  const investigationItem = admin.locator('article.dashboard-card').filter({hasText:fixture.transfer_sku}).first();
  await investigationItem.locator('summary').filter({hasText:'Open formal loss investigation'}).click();
  const investigation = investigationItem.locator('form').filter({has:admin.locator('button[value=report-loss]')});
  await investigation.locator('[name=evidence_reference]').fill('SEAL-'+fixture.transfer_sku+'-INCIDENT');
  await investigation.locator('[name=reason]').fill(
    'Checked signed dispatch sheet, shipment seal and receiver count; battery remains missing.');
  await investigation.locator('[name=confirm]').check();
  await investigation.locator('button[value=report-loss]').click();
  await admin.getByRole('status').getByText('Formal loss investigation recorded',{exact:false}).waitFor();
  const reviewer = await login('loss_reviewer',1440);
  await reviewer.goto(base + '/admin/parts_transfers.php?branch=' + fixture.branch);
  const reviewItem = reviewer.locator('article.dashboard-card').filter({hasText:fixture.transfer_sku}).first();
  await reviewItem.locator('summary').filter({hasText:'Independently review missing-stock case'}).click();
  const decision = reviewItem.locator('form').filter({has:reviewer.locator('button[value=decide-loss]')});
  await decision.locator('[name=decision]').selectOption('approve');
  await decision.locator('[name=reason]').fill(
    'Independently verified origin manifest, destination incident report and sealed shipment discrepancy.');
  await decision.locator('[name=confirm]').check();
  await decision.locator('button[value=decide-loss]').click();
  await reviewer.getByRole('status').getByText('Independent loss decision recorded',{exact:false}).waitFor();
  await reviewer.getByText('Status LOSS_CLOSED',{exact:false}).first().waitFor();
  await reviewer.context().close();
  await admin.context().close();
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
  const reusableReturn = manager.locator('form').filter({ has: manager.locator('[name=stock_action][value=return]') });
  await reusableReturn.locator('[name=return_reason]').fill('Removed and inspected reusable SSD before final testing.');
  await reusableReturn.locator('[name=restock_confirm]').check();
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
  const partsForbidden = await cashier.goto(base + '/admin/parts_transfers.php');
  assert.equal(partsForbidden.status(),403,'Cashier gained access to physical parts transfers.');
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
