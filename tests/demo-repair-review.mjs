import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { chromium } from 'playwright-core';

const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH,
  channel: process.env.CHROME_PATH ? undefined : 'chrome',
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});
try {
  const context = await browser.newContext();
  const pages = await Promise.all([context.newPage(), context.newPage()]);
  const url = pathToFileURL(resolve('demo-prototype/index.html')).href;
  async function prepareDiagnosis(page, device, findings) {
    await page.locator('#device-picker').selectOption(String(device));
    await page.locator('[data-action=diagnose]').click();
    await page.getByLabel('Findings').fill(findings);
    await page.getByLabel('Cause category').selectOption('Power');
  }
  const save = page => page.getByRole('button', { name: 'Save findings' }).click();
  await Promise.all(pages.map(async page => {
    await page.goto(url);
    await page.evaluate(() => { setRole('Technician'); go('repair'); });
    await prepareDiagnosis(page, 0, 'First inspection');
  }));
  const [first, second] = pages;
  await second.getByLabel('Findings').fill('Second inspection');
  await save(first);
  await first.locator('dialog').waitFor({state:'hidden'});
  await save(second);
  await second.getByRole('alert').filter({hasText:'Your entry was not saved'}).waitFor();
  assert.equal(await second.getByLabel('Findings').inputValue(), 'Second inspection');
  await second.getByRole('button', {name:'View current repair'}).click();
  assert.equal(await second.evaluate(() => ticket().devices[0].diagnosis), 'First inspection');

  // Separate devices on one ticket can be edited concurrently.
  await prepareDiagnosis(first, 0, 'Loose power jack');
  await prepareDiagnosis(second, 1, 'Faulty power supply');
  await Promise.all(pages.map(save));
  await Promise.all(pages.map(page => page.locator('dialog').waitFor({state:'hidden'})));
  assert.deepEqual(await first.evaluate(() => readStoredDemo().tickets[0].devices.map(d => d.diagnosis)),
    ['Loose power jack', 'Faulty power supply']);

  // Two counter staff each create a separate draft at nearly the same time.
  await Promise.all(pages.map(async (page, index) => {
    await page.evaluate(() => { setRole('Cashier'); go('intake'); });
    await page.getByLabel('Customer name').fill('Concurrent intake ' + index);
    await page.getByLabel('Phone or email').fill('intake' + index + '@example.test');
    await page.getByRole('button', {name:'Continue'}).click();
    await page.getByLabel('Device brand and model').fill('Dell Latitude');
    await page.getByLabel('Reported problem').fill('No power');
    await page.getByLabel('Visible condition').fill('Scratched');
    await page.getByLabel('Accessories').fill('None');
    await page.getByRole('button', {name:'Continue'}).click();
  }));
  await Promise.all(pages.map(page => page.getByRole('button', {name:'Save intake draft'}).click()));
  await Promise.all(pages.map(page => page.getByRole('button', {name:'Review & issue'}).waitFor()));
  const ids = await first.evaluate(() => readStoredDemo().tickets
    .filter(t => t.customer.startsWith('Concurrent intake')).map(t => t.id));
  assert.equal(ids.length, 2);
  assert.equal(new Set(ids).size, 2);
  // Both cashiers can review a draft, but only one can issue it.
  await Promise.all(pages.map(async page => {
    await page.evaluate(id => {
      state = readStoredDemo();
      activeTicket = id;
      render();
    }, ids[0]);
    await page.getByRole('button', {name:'Review & issue'}).click();
    const form = page.locator('[data-form=issue]');
    await form.getByLabel('Condition photo label').fill('device.jpg');
    await form.getByRole('checkbox').check();
  }));
  await Promise.all(pages.map(page => page.getByRole('button', {name:'Issue demo tracking code'}).click()));
  await Promise.any(pages.map(page => page.getByRole('button', {name:'View current repair'}).waitFor({timeout:3000})));
  const issueEvents = await first.evaluate(id => readStoredDemo().tickets.find(t => t.id === id).events
    .filter(event => event.text.includes('tracking issued')).length, ids[0]);
  assert.equal(issueEvents, 1);
  console.log('Passed overlapping diagnoses, independent device edits, simultaneous intake and duplicate issuance.');

} finally {
  await browser.close();
}
