import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';

// Use an installed Chrome/Chromium, or set CHROME_PATH to its executable.
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH,
  channel: process.env.CHROME_PATH ? undefined : 'chrome',
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
  headless: true,
});
const session = await browser.newContext();
const page = await session.newPage();
const errors = [];
page.on('pageerror', (error) => errors.push(error.message));
const url = pathToFileURL(resolve('demo-prototype/index.html')).href;

try {
  await page.goto(url + '#overview');
  await page.waitForFunction(() => location.hash === '#login' || role === 'Admin');
  assert.equal(await page.evaluate(() => role), 'Public', 'A URL must not grant the Admin role');
  assert.equal(await page.evaluate(() => location.hash), '#login');

  await page.goto(url + '#track/%ZZ');
  await page.waitForFunction(() => location.hash === '#home');
  assert.equal(await page.evaluate(() => role), 'Public');

  // Visit each staff screen at phone and desktop widths.
  for (const width of [390, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    for (const staffRole of ['Admin', 'Technician', 'Cashier', 'Collector']) {
      for (const route of [
        'overview',
        'repairs',
        'intake',
        'repair',
        'customers',
        'team',
        'inventory',
        'parts',
        'messages',
        'finance',
        'reports',
        'logistics',
        'transfers',
        'settings',
        'alerts',
        'audit',
      ]) {
        await page.evaluate(
          ({ staffRole, route }) => {
            role = staffRole;
            location.hash = route;
            render();
          },
          { staffRole, route }
        );
        assert.ok(await page.locator('#content').count(), `${staffRole}: ${route}`);
      }
    }
  }

  // Use the same forms a cashier uses, including the durable receipt and cross-tab lock.
  await page.evaluate(() => {
    state = seedDemo();
    state.branch = 'Central Branch';
    saveDemo(true);
    setRole('Cashier');
    go('finance');
  });
  const collect = page.locator('[data-action=cash][data-id="RT-1044"]');
  await collect.click();
  const form = page.locator('[data-form=cash]');
  await form.locator('[name=amount]').fill('650.01');
  await form.getByRole('checkbox').check();
  await form.getByRole('button', { name: 'Issue cash receipt' }).click();
  await form.getByRole('status').filter({hasText: 'remaining balance'}).waitFor();
  await form.locator('[name=amount]').fill('650');
  await form.getByRole('button', { name: 'Issue cash receipt' }).click();
  const release = page.locator('[data-action=release][data-id="RT-1044"]');
  await release.click();
  const handover = page.locator('[data-form=release]');
  await handover.locator('[name=evidence]').fill('Device checked at the counter.');

  // Another cashier reverses part of the payment while the first cashier is holding the device.
  const second = await page.context().newPage();
  await second.goto(url);
  await second.evaluate(() => { setRole('Cashier'); go('finance'); });
  await second.locator('[data-action=refund][data-id="RT-1044"]').click();
  const refund = second.locator('[data-form=refund]');
  await refund.locator('[name=amount]').fill('100');
  await refund.locator('[name=reason]').fill('Cash count corrected.');
  // Full browser storage must leave the modal open and the saved balance untouched.
  await second.evaluate(() => {
    const write = Storage.prototype.setItem;
    Storage.prototype.setItem = function (key, value) {
      Storage.prototype.setItem = write;
      throw new DOMException('Full', 'QuotaExceededError');
    };
  });
  await refund.getByRole('button', {name: 'Save correction'}).click();
  await second.getByText('Change not saved.', {exact: false}).waitFor();
  assert.equal(await second.evaluate(() => balance(readStoredDemo().tickets[2])), 0);
  await refund.getByRole('button', {name: 'Save correction'}).click();
  await second.locator('dialog').waitFor({state: 'hidden'});
  await handover.getByRole('button', {name: 'Confirm device return'}).click();
  await page.getByText('The ticket changed.', {exact: false}).waitFor();
  const actual = await page.evaluate(() => {
    const ticket = readStoredDemo().tickets.find(item => item.id === 'RT-1044');
    return [balance(ticket), ticket.custody, ticket.payments.length, ticket.refunds.length];
  });
  assert.deepEqual(actual, [100, 'At branch', 1, 1]);
  assert.equal(await second.evaluate(() => {
    verified = true;
    const current = readStoredDemo().tickets.find(item => item.id === 'RT-1044');
    return portalHistory(current).includes('Cash count corrected.');
  }), false, 'The customer timeline must omit the internal correction reason.');
  await second.close();

  await page.evaluate(() => {
    role = 'Customer';
    verified = true;
    activeDevice = 9;
    location.hash = 'track/DEMO-1042';
  });
  await page.waitForFunction(() => location.hash === '#portal');
  assert.deepEqual(await page.evaluate(() => ({ verified, activeDevice })), {
    verified: false,
    activeDevice: 0,
  });
  assert.deepEqual(errors, []);
  console.log(
    'Passed: public routes, malformed links, 128 staff screen renders, cash guards and tracking reset.'
  );
} finally {
  await browser.close();
}
