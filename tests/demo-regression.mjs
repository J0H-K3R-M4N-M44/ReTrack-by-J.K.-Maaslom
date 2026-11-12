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
const page = await browser.newPage();
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

  const result = await page.evaluate(() => {
    state = seedDemo();
    role = 'Cashier';
    activeTicket = state.tickets.find((item) => item.bill)?.id;
    const item = ticket();
    context = { id: item.id };
    const form = document.createElement('form');
    form.innerHTML = '<input name="amount"><input name="method" value="Counter cash">';
    const before = item.payments.length;
    form.elements.amount.value = String(balance(item) + 1);
    submit('cash', form);
    const rejected = item.payments.length === before;
    form.elements.amount.value = String(balance(item));
    submit('cash', form);
    return { rejected, remaining: balance(item), recorded: item.payments.length === before + 1 };
  });
  assert.deepEqual(result, { rejected: true, remaining: 0, recorded: true });

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
