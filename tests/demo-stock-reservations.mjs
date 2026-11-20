import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH,
  channel: process.env.CHROME_PATH ? undefined : 'chrome',
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});
try {
  const context = await browser.newContext();
  const page = await context.newPage();
  const url = pathToFileURL(resolve('demo-prototype/index.html')).href;
  await page.goto(url);
  const dialog = page.locator('dialog');
  async function visit(route) {
    await page.evaluate(route => go(route), route);
    await page.waitForURL('**#' + route);
  }
  async function confirm() {
    await dialog.locator('[type=submit]').click();
    await dialog.waitFor({state:'hidden'});
  }
  async function fixture() {
    await page.evaluate(() => {
      state = seedDemo();
      state.branch = 'North Branch';
      state.tickets[1].requests.push({type:'Cancellation', status:'Requested', text:'Please return my device.'});
      saveDemo(true);
      setRole('Admin');
      activeTicket = state.tickets[1].id;
      go('inventory');
    });
    await page.locator('[data-action=approve-part]').waitFor();
  }
  async function quantities(north, central, status) {
    await page.waitForFunction(([north, central, status]) => {
      const data = readStoredDemo();
      const qty = branch => data.stock.find(row => row.id === 'ssd' && row.branch === branch).qty;
      return qty('North Branch') === north && qty('Central Branch') === central &&
        data.tickets[1].parts[0].status === status;
    }, [north, central, status]);
  }
  async function reserve() {
    await page.locator('[data-action=approve-part]').click();
    await quantities(4, 5, 'Reserved');
  }
  async function cancel() {
    await visit('repair');
    await page.locator('[data-action=review-request]').click();
    await dialog.locator('[name=decision]').selectOption('Cancellation approved');
    await dialog.locator('[name=explanation]').fill('Approved as-is return.');
    await confirm();
  }
  await fixture();
  await reserve();
  await cancel();
  await quantities(5, 5, 'Released');

  await fixture();
  await reserve();
  await visit('repair');
  await page.locator('[data-action=transfer]').click();
  await dialog.locator('[name=destination]').selectOption('Central Branch');
  await dialog.locator('[name=evidence]').fill('Device only, SSD remains at North.');
  await confirm();
  await quantities(5, 5, 'Requested');
  await visit('inventory');
  assert.equal(await page.locator('[data-action=approve-part]').count(), 0);
  await visit('transfers');
  await page.locator('#branch-picker').selectOption('Central Branch');
  await page.locator('[data-action=receive-transfer]').click();
  await dialog.locator('[name=evidence]').fill('Device received.');
  await confirm();
  await visit('inventory');
  await page.locator('[data-action=approve-part]').click();
  await quantities(5, 4, 'Reserved');
  await page.locator('[data-action=consume-part]').click();
  await quantities(5, 4, 'Consumed');
  await page.evaluate(() => {activeTicket = state.tickets[1].id;});
  await cancel();
  await quantities(5, 4, 'Consumed');

  await fixture();
  await reserve();
  await page.evaluate(() => {
    state.tickets[1].quotes[0].items = [{ id:'clean', qty:1, price:650 }];
    saveDemo();
  });
  await page.locator('[data-action=consume-part]').click();
  await page.getByText('The current approved quote must cover this part and its remaining quantity.').waitFor();
  await quantities(4, 5, 'Reserved');
  await page.locator('[data-action=release-part]').click();
  await quantities(5, 5, 'Released');
  // Two staff tabs viewing the last SSD cannot both reserve it.
  await fixture();
  await page.evaluate(() => {
    const ticket = state.tickets[1];
    ticket.parts.push({ ...ticket.parts[0] });
    state.stock.find(row => row.id === 'ssd' && row.branch === 'North Branch').qty = 1;
    saveDemo();
    render();
  });
  const second = await context.newPage();
  await second.goto(url);
  await second.evaluate(() => { setRole('Admin'); go('inventory'); });
  await Promise.all([
    page.locator('[data-action=approve-part]').first().click(),
    second.locator('[data-action=approve-part]').last().click(),
  ]);
  await page.waitForFunction(() => readStoredDemo().tickets[1].parts.some(part => part.status === 'Reserved'));
  const result = await page.evaluate(() => {
    const data = readStoredDemo();
    return [data.stock.find(row => row.id === 'ssd' && row.branch === 'North Branch').qty,
      data.tickets[1].parts.filter(part => part.status === 'Reserved').length];
  });
  assert.deepEqual(result, [0, 1]);
  console.log('Passed cancellation, dispatch, receipt, destination reservation, consumption and quote mismatch.');
} finally {
  await browser.close();
}
