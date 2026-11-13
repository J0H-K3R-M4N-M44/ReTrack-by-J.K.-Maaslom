import assert from 'node:assert/strict';
import fs from 'node:fs';
const fixture = JSON.parse(
  fs.readFileSync(process.env.REPAIRTRACK_TEST_SITE + '/tests/demo-seed-fixture.json', 'utf8')
);
import { chromium } from 'playwright-core';
const url = process.env.REPAIRTRACK_TEST_URL;
assert(url && ['localhost', '127.0.0.1'].includes(new URL(url).hostname));
const browser = await chromium.launch({
  executablePath: process.cwd() + '/qa/chromium',
  args: ['--no-sandbox'],
  env: { ...process.env, LD_LIBRARY_PATH: process.cwd() + '/qa' },
});
try {
  for (const [email, heading] of [
    ['admin@example.test', null],
    ['manager.central@example.test', 'Manager console'],
    ['intake.central@example.test', 'Intake console'],
    ['tech.central@example.test', 'Technician console'],
    ['courier.central@example.test', 'Courier console'],
    ['viewer.central@example.test', 'Viewer console'],
  ]) {
    const context = await browser.newContext();
    await context.route('**/*', (r) =>
      r.request().url().startsWith(url) ? r.continue() : r.abort()
    );
    const page = await context.newPage();
    await page.goto(url + '/admin/login.php');
    await page.locator('[name=email]').fill(email);
    await page.locator('[name=password]').fill('DemoRepair2026!');
    await page.getByRole('button', { name: 'Login', exact: true }).click();
    await page.waitForURL(heading ? '**/staff_home.php' : '**/dashboard.php');
    if (heading)
      assert(await page.getByRole('heading', { name: heading, exact: true }).isVisible());
    if (!heading) {
      await page.goto(url + '/admin/ticket.php?id=' + fixture.ticket_id);
      for (const kind of ['photo', 'signed_form']) {
        const response = await page.request.get(
          url + '/admin/intake_evidence.php?ticket=' + fixture.ticket_id + '&kind=' + kind
        );
        assert.equal(response.status(), 200);
        assert(response.headers()['content-type'].includes('image/png'));
      }
      await page.goto(url + '/admin/dashboard.php?tab=workbench');
      assert(await page.getByRole('link', { name: 'Next', exact: true }).first().isVisible());
    }
    await context.close();
  }
  console.log(
    'PASS: seeded logins open all six role screens, queue paging and private evidence previews.'
  );
} finally {
  await browser.close();
}
