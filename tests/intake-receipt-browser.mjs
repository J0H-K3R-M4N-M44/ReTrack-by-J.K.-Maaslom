import assert from 'node:assert/strict';
import { readFileSync, renameSync } from 'node:fs';
import { chromium } from 'playwright-core';

const base = 'http://127.0.0.1:8742';
const fixture = JSON.parse(readFileSync(process.env.INTAKE_RECEIPT_FIXTURE, 'utf8'));
const browser = await chromium.launch({ channel: 'chrome' });
try {
  const page = await browser.newPage();
  await page.route('**/*', route =>
    route.request().url().startsWith(base + '/') ? route.continue() : route.abort()
  );
  await page.goto(base + '/admin/login.php');
  await page.locator('#email').fill('admin@example.test');
  await page.locator('#password').fill('DemoRepair2026!');
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/dashboard.php');
  await page.goto(base + '/admin/ticket.php?id=' + fixture.id);
  const card = suffix => page.locator('section').filter({
    has: page.getByRole('heading', { name: fixture.code + suffix, exact: true })
  });
  const photo = section => section.getByRole('link', { name: 'View condition photo' });
  const signed = page.getByRole('link', { name: 'View signed intake form' });
  for (const link of [photo(card('-01')), photo(card('-02')), signed]) {
    const url = new URL(await link.getAttribute('href'), page.url()).href;
    const image = await page.request.get(url);
    assert.equal(image.status(), 200);
    assert.equal(image.headers()['content-type'], 'image/png');
  }
  // Set aside synthetic test images to simulate a partially restored evidence folder.
  const moved = [];
  try {
    for (const file of [fixture.secondPhoto, fixture.signedForm]) {
      renameSync(file, file + '.held');
      moved.push(file);
    }
    await page.reload();
    assert(await photo(card('-01')).isVisible());
    assert.equal(await photo(card('-02')).count(), 0);
    assert(await card('-02').getByText('Condition photo unavailable for this device.').isVisible());
    assert.equal(await signed.count(), 0);
    assert(await page.getByText('Signed intake form unavailable for this ticket.').isVisible());
  } finally {
    for (const file of moved) renameSync(file + '.held', file);
  }
  await page.reload();
  assert(await photo(card('-02')).isVisible());
  assert(await signed.isVisible());
  console.log('Passed two-device receipt, missing evidence and restored attachments.');
} finally {
  await browser.close();
}
