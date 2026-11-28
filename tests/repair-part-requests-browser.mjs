import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright-core';

const origin = process.env.REPAIRTRACK_TEST_URL;
const site = process.env.REPAIRTRACK_TEST_SITE;
assert(origin && ['localhost', '127.0.0.1'].includes(new URL(origin).hostname));
const fixture = JSON.parse(fs.readFileSync(site + '/tests/console-fixture.json', 'utf8'));
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH || process.cwd() + '/qa/chromium',
  args: ['--no-sandbox'],
  env: { ...process.env, LD_LIBRARY_PATH: process.cwd() + '/qa' },
});
try {
  const startRole = async (email) => {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    await context.route('**/*', (route) =>
      route.request().url().startsWith(origin) ? route.continue() : route.abort()
    );
    const page = await context.newPage();
    await page.goto(origin + '/admin/login.php');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill('ConsoleDemo2026!');
    await page.getByRole('button', { name: 'Login', exact: true }).click();
    await page.waitForURL('**/staff_home.php');
    return { page, context };
  };
  const tech = await startRole('technician.console@example.test');
  const label = 'Browser fan ' + Math.random().toString(16).slice(2, 10);
  await tech.page.goto(origin + '/admin/part_requests.php?device_id=' + fixture.device);
  await tech.page.getByRole('heading', { name: 'Request a part for this repair' }).waitFor();
  const bad = await tech.page.request.post(origin + '/admin/part_requests.php', {
    form: { csrf_token: 'invalid', intent: 'review' },
  });
  assert.equal(bad.status(), 403);
  await tech.page.locator('[name=part_description]').fill(label);
  await tech.page.locator('[name=quantity]').fill('1');
  await tech.page.locator('[name=reason]').fill('Required to replace a failed cooling component.');
  await tech.page.getByRole('button', { name: 'Review request' }).click();
  await tech.page.getByRole('heading', { name: 'Review part request' }).waitFor();
  await tech.page.getByRole('button', { name: 'Submit internal request' }).click();
  await tech.page.getByRole('status').waitFor();
  await tech.page.getByRole('row').filter({ hasText: label }).waitFor();
  for (const width of [1440, 768, 390, 320]) {
    await tech.page.setViewportSize({ width, height: 844 });
    assert(await tech.page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      'Parts request page overflow at ' + width);
  }
  const manager = await startRole('manager.console@example.test');
  await manager.page.goto(origin + '/admin/part_requests.php');
  await manager.page.getByRole('row').filter({ hasText: label })
    .getByRole('link', { name: /Open request/ }).click();
  await manager.page.getByRole('heading', { name: /Request #/ }).waitFor();
  await manager.page.getByRole('button', { name: 'Review decision' }).click();
  await manager.page.getByRole('button', { name: 'Record decision' }).click();
  await manager.page.getByRole('status').waitFor();
  await manager.page.getByRole('row').filter({ hasText: label })
    .getByText('ACKNOWLEDGED').waitFor();
  const viewer = await startRole('viewer.console@example.test');
  const unauthorized = await viewer.page.goto(origin + '/admin/part_requests.php');
  assert.equal(unauthorized.status(), 403);
  await viewer.context.close();
  await manager.context.close();
  await tech.context.close();
  console.log('PASS: technician creates internal part request, manager acknowledges, viewer denied, CSRF/mobile.');
} finally {
  await browser.close();
}
