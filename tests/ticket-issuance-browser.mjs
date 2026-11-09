import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright-core';
const url = process.env.REPAIRTRACK_TEST_URL;
const site = process.env.REPAIRTRACK_TEST_SITE;
assert(url && ['localhost', '127.0.0.1'].includes(new URL(url).hostname));
assert(site);
const id = fs.readFileSync(site + '/tests/browser-issue-id.txt', 'utf8').trim();
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH || process.cwd() + '/qa/chromium',
  args: ['--no-sandbox'],
  env: { ...process.env, LD_LIBRARY_PATH: process.cwd() + '/qa' },
});
const pixel = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5FoAAAAASUVORK5CYII=',
  'base64'
);
async function fill(page) {
  const location = await page.locator('[name=location_id] option').nth(1).getAttribute('value');
  await page.locator('[name=location_id]').selectOption(location);
  await page.locator('[name=terms_version]').fill('TEST-ONLY-v1');
  for (const name of ['checklist_confirmed', 'signature_confirmed', 'duplicate_confirmed'])
    await page.locator('[name=' + name + ']').check();
  for (const name of ['photo', 'signed_form'])
    await page
      .locator('[name=' + name + ']')
      .setInputFiles({ name: name + '.png', mimeType: 'image/png', buffer: pixel });
}
try {
  const context = await browser.newContext({ javaScriptEnabled: false });
  await context.route('**/*', (route) =>
    route.request().url().startsWith(url) ? route.continue() : route.abort()
  );
  const page = await context.newPage();
  await page.goto(url + '/admin/login.php');
  await page.locator('[name=email]').fill('admin@example.test');
  await page.locator('[name=password]').fill('DemoRepair2026!');
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/dashboard.php');
  await page.goto(url + '/admin/intake_create.php?id=' + id);
  await page.getByRole('link', { name: 'Complete checks and issue ticket' }).click();
  assert(await page.getByRole('heading', { name: 'Complete intake checks' }).isVisible());
  await page.setViewportSize({ width: 1440, height: 1000 });
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  await page.screenshot({ path: site + '/issue-entry-desktop.png', fullPage: true });
  const csrf = await page.locator('[name=csrf_token]').inputValue();
  const formKey = await page.locator('[name=form_key]').inputValue();
  assert.equal(
    (
      await page.request.post(url + '/admin/intake_issue.php', {
        form: { intent: 'issue', csrf_token: 'wrong', form_key: formKey },
      })
    ).status(),
    403
  );
  // A file renamed to PNG must fail, and a partially stored pair must be removed.
  const privatePath = process.env.REPAIRTRACK_PRIVATE_DIR;
  const before = fs.readdirSync(privatePath).length;
  await fill(page);
  await page.locator('[name=signed_form]').setInputFiles({
    name: 'bad.png',
    mimeType: 'image/png',
    buffer: Buffer.from('<?php echo 1;'),
  });
  await page.getByRole('button', { name: 'Review issuance' }).click();
  assert((await page.locator('body').textContent()).includes('JPEG or PNG'));
  assert.equal(fs.readdirSync(privatePath).length, before, 'Failed upload pair cleaned');
  // Expired review keys remove their temporary files and cannot issue a ticket.
  await page.goto(url + '/admin/intake_issue.php?id=' + id);
  await fill(page);
  await page.getByRole('button', { name: 'Review issuance' }).click();
  const expiredBody = await page
    .locator('form')
    .evaluate((form) => Object.fromEntries(new FormData(form)));
  const sessionCookie = (await context.cookies()).find(
    (cookie) => cookie.name === 'repairtrack_admin'
  );
  const sessionPath = process.env.REPAIRTRACK_SESSION_DIR + '/sess_' + sessionCookie.value;
  fs.writeFileSync(
    sessionPath,
    fs.readFileSync(sessionPath, 'utf8').replace(/s:7:"expires";i:\d+;/g, 's:7:"expires";i:1;')
  );
  const expired = await page.request.post(url + '/admin/intake_issue.php', {
    form: { ...expiredBody, intent: 'issue', confirm_issue: '1' },
  });
  assert.equal(expired.status(), 409);
  assert.equal(fs.readdirSync(privatePath).length, before, 'Expired review files cleaned');

  await page.goto(url + '/admin/intake_issue.php?id=' + id);
  await fill(page);
  await page.getByRole('button', { name: 'Review issuance' }).click();
  assert(await page.getByRole('heading', { name: 'Confirm ticket issuance' }).isVisible());
  const imageUrl = await page.locator('.evidence-previews img').first().getAttribute('src');
  const image = await page.request.get(url + '/admin/' + imageUrl);
  assert.equal(image.status(), 200);
  assert.equal(image.headers()['content-type'], 'image/png');
  const anonymous = await browser.newContext();
  const denied = await anonymous.request.get(url + '/admin/' + imageUrl, { maxRedirects: 0 });
  assert.equal(denied.status(), 302);
  await anonymous.close();
  await page.setViewportSize({ width: 390, height: 844 });
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  await page.screenshot({ path: site + '/issue-review-mobile.png', fullPage: true });
  const body = await page
    .locator('form')
    .evaluate((form) => Object.fromEntries(new FormData(form)));
  await page.locator('[name=confirm_issue]').check();
  await page.getByRole('button', { name: 'Issue ticket', exact: true }).click();
  await page.waitForURL('**/ticket.php?id=' + id);
  assert.match(await page.locator('h1').textContent(), /^RT-[A-F0-9]{12}$/);
  assert((await page.locator('body').textContent()).includes('RECEIVED'));
  const replay = await page.request.post(url + '/admin/intake_issue.php', {
    form: { ...body, intent: 'issue', confirm_issue: '1' },
  });
  assert.equal(replay.status(), 409);
  const evidenceLink = await page
    .getByRole('link', { name: 'View signed intake form' })
    .getAttribute('href');
  assert.equal((await page.request.get(url + '/admin/' + evidenceLink)).status(), 200);
  assert.equal(
    (
      await page.request.get(url + '/admin/intake_evidence.php?ticket=' + id + '&kind=../../config')
    ).status(),
    404
  );
  assert.equal((await page.request.get(url + '/admin/intake_create.php?id=' + id)).status(), 409);
  await page.goto(url + '/admin/dashboard.php?tab=workbench');
  assert(
    await page
      .locator('[data-dashboard-panel="workbench"] a[href="ticket.php?id=' + id + '"]')
      .isVisible()
  );

  const otherContext = await browser.newContext();
  const other = await otherContext.newPage();
  await other.goto(url + '/admin/login.php');
  await other.locator('[name=email]').fill('other-branch-admin@example.test');
  await other.locator('[name=password]').fill('DemoRepair2026!');
  await other.getByRole('button', { name: 'Login', exact: true }).click();
  await other.waitForURL('**/dashboard.php');
  assert.equal((await other.request.get(url + '/admin/ticket.php?id=' + id)).status(), 404);
  assert.equal((await other.request.get(url + '/admin/' + evidenceLink)).status(), 404);
  await otherContext.close();
  console.log(
    'PASS: real HTTP uploads, invalid-image cleanup, private previews, no-JS review/issue, mobile, receipt, replay rejection and issued draft protection.'
  );
} finally {
  await browser.close();
}
