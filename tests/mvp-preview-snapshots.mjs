import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright-core';

const base = 'http://127.0.0.1:8742';
const fixturePath = process.env.MVP_SCREENSHOT_FIXTURE;
const out = process.env.MVP_SCREENSHOT_OUTPUT;
assert(fixturePath && out, 'Missing screenshot fixture/output paths');
const fixture = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
fs.mkdirSync(out, { recursive: true });

const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome',
  args: ['--no-sandbox'],
});
const ctx = await browser.newContext({ viewport: { width: 1440, height: 960 } });
const page = await ctx.newPage();
const runtimeErrors = [];
page.on('pageerror', error => runtimeErrors.push(error.message));

async function snap(filename) {
  await page.screenshot({
    path: path.join(out, filename), fullPage: true, animations: 'disabled',
  });
  console.log('Screenshot:', filename);
}
try {
  await page.goto(base + '/', { waitUntil: 'networkidle' });
  await page.getByRole('link', { name: /track your repair/i }).first().waitFor();
  await snap('01-landing-desktop.png');

  await page.goto(base + '/track.php', { waitUntil: 'networkidle' });
  await page.locator('#tracking-code').waitFor();
  await snap('02-customer-entry-desktop.png');

  await page.locator('#tracking-code').fill(fixture.code);
  await page.locator('#portal-pin').fill(fixture.pin);
  await page.getByRole('button', { name: 'View repair' }).click();
  await page.getByRole('heading', { name: 'Your repair update' }).waitFor();
  const text = await page.locator('main').innerText();
  for (const expected of ['In repair', 'Recorded location', 'Repair journey', 'Repair timeline']) {
    assert(text.includes(expected), 'Missing customer field: ' + expected);
  }
  await snap('03-repair-tracking-desktop.png');
  await page.setViewportSize({ width: 390, height: 844 });
  await snap('04-repair-tracking-mobile.png');

  await page.setViewportSize({ width: 1440, height: 960 });
  await page.goto(base + '/admin/login.php', { waitUntil: 'networkidle' });
  await page.locator('[name=email]').waitFor();
  await snap('05-staff-login-desktop.png');
  await page.locator('[name=email]').fill('manager@sim.example.test');
  await page.locator('[name=password]').fill(fixture.password);
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/staff_home.php**', { timeout: 20000 });
  await page.locator('main').waitFor();
  await snap('06-staff-workspace-desktop.png');

  await page.goto(base + '/admin/device.php?id=' + fixture.device, { waitUntil: 'networkidle' });
  await page.locator('main').waitFor();
  assert((await page.locator('main').innerText()).includes('Repair details'));
  await snap('07-device-detail-desktop.png');

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(base + '/admin/staff_home.php', { waitUntil: 'networkidle' });
  await page.locator('main').waitFor();
  await snap('08-staff-workspace-mobile.png');

  assert.deepEqual(runtimeErrors, [], 'Uncaught client JavaScript errors');
  console.log('PASS: 8 screenshots from seeded actual customer and staff pages.');
} finally {
  await browser.close();
}
