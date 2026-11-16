import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
const url = process.env.REPAIRTRACK_TEST_URL,
  site = process.env.REPAIRTRACK_TEST_SITE;
assert(url && ['localhost', '127.0.0.1'].includes(new URL(url).hostname));
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH || process.cwd() + '/qa/chromium',
  args: ['--no-sandbox'],
  env: { ...process.env, LD_LIBRARY_PATH: process.cwd() + '/qa' },
});
try {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  await context.route('**/*', (r) =>
    r.request().url().startsWith(url) ? r.continue() : r.abort()
  );
  const page = await context.newPage(),
    errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.goto(url + '/admin/login.php');
  await page.locator('#email').fill('interface.admin@example.test');
  await page.locator('#password').fill('InterfaceDemo2026!');
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/dashboard.php');
  await page.getByRole('button', { name: 'Hide navigation', exact: true }).click();
  assert(await page.locator('#admin-navigation').isHidden());
  await page.reload();
  assert(await page.locator('#admin-navigation').isHidden());
  await page.getByRole('button', { name: 'Show navigation', exact: true }).click();
  assert(await page.getByRole('link', { name: 'Repair workbench', exact: true }).isVisible());
  await page.locator('.console-account summary').click();
  assert(await page.getByRole('button', { name: 'Log out', exact: true }).isVisible());
  await page.keyboard.press('Escape');
  assert(!(await page.locator('.console-account').evaluate((el) => el.open)));
  await page.screenshot({ path: site + '/interface-admin-desktop.png', fullPage: true });
  for (const width of [390, 320]) {
    await page.setViewportSize({ width, height: 844 });
    const trigger = page.getByRole('button', { name: 'Open navigation', exact: true });
    await trigger.click();
    const dialog = page.getByRole('dialog', { name: 'Admin navigation' });
    assert(await dialog.isVisible());
    assert(await dialog.getByRole('link', { name: 'Repair workbench', exact: true }).isVisible());
    assert(
      await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      JSON.stringify(
        await page.evaluate(() =>
          Array.from(document.querySelectorAll('body *'))
            .filter((el) => el.getBoundingClientRect().right > innerWidth + 1)
            .slice(0, 15)
            .map((el) => ({
              tag: el.tagName,
              cls: el.className,
              width: el.getBoundingClientRect().width,
              right: el.getBoundingClientRect().right,
            }))
        )
      )
    );
    await page.keyboard.press('Shift+Tab');
    assert(
      await page.evaluate(() =>
        document.querySelector('dialog[open]').contains(document.activeElement)
      ),
      'Focus escaped modal'
    );
    if (width === 390) await page.screenshot({ path: site + '/interface-navigation-mobile.png' });
    await page.keyboard.press('Escape');
    assert(await trigger.evaluate((el) => el === document.activeElement));
    assert(await dialog.isHidden());
  }
  await page.getByRole('button', { name: 'Open navigation', exact: true }).click();
  await page
    .getByRole('dialog')
    .getByRole('link', { name: 'Repair workbench', exact: true })
    .click();
  await page.waitForURL('**/dashboard.php?tab=workbench');
  assert(await page.locator('[data-dashboard-panel=overview]').first().isHidden());
  await page.goto(url + '/admin/staff_home.php?view=queue&q=definitely-no-device');
  await page.locator('.active-filters').waitFor();
  assert((await page.locator('.active-filters').innerText()).includes('definitely-no-device'));
  await page.getByRole('link', { name: 'Show all records' }).click();
  assert.equal(new URL(page.url()).searchParams.get('q'), null);
  await page.getByRole('button', { name: 'Workspace menu' }).click();
  await page.getByRole('dialog').getByRole('link', { name: 'Saved drafts', exact: true }).click();
  await page.waitForURL('**/staff_home.php?view=drafts');
  await page.setViewportSize({ width: 1440, height: 1000 });
  assert(await page.getByRole('link', { name: 'Status board', exact: true }).isVisible());
  assert(
    (await page.getByRole('button', { name: 'Workspace menu' }).count()) === 0 ||
      (await page.locator('[data-navigation-toggle]').isHidden())
  );
  const plainContext = await browser.newContext({
    javaScriptEnabled: false,
    viewport: { width: 320, height: 844 },
    storageState: await context.storageState(),
  });
  const plain = await plainContext.newPage();
  await plain.goto(url + '/admin/dashboard.php');
  assert(await plain.getByRole('link', { name: 'Repair workbench', exact: true }).isVisible());
  await plain.getByRole('link', { name: 'Repair workbench', exact: true }).click();
  assert(new URL(plain.url()).searchParams.get('tab') === 'workbench');
  await plain.goto(url + '/admin/staff_home.php');
  assert(await plain.getByRole('link', { name: 'Saved drafts', exact: true }).isVisible());
  assert(
    await plain.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
    'No-JS overflow'
  );
  assert.deepEqual(errors, []);
  console.log(
    'PASS: desktop persistence, mobile modal/focus/Escape, account menu, filters, resize and no-JS navigation.'
  );
} finally {
  await browser.close();
}
