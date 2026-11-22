import assert from 'node:assert/strict';
import { readFileSync, writeFileSync } from 'node:fs';
import { chromium } from 'playwright-core';

// Local disposable MySQL + fictional PHP fixture. No external site requests.
const fixtureFile = process.env.PICKUP_FIXTURE;
assert(fixtureFile, 'PICKUP_FIXTURE is required.');
const fixture = JSON.parse(readFileSync(fixtureFile, 'utf8'));
const base = 'http://127.0.0.1:8742';
const browser = await chromium.launch({ channel: 'chrome' });

async function signedIn(javaScriptEnabled = true) {
  const context = await browser.newContext({
    javaScriptEnabled,
    viewport: { width: 390, height: 844 },
  });
  await context.route('**/*', (route) =>
    route.request().url().startsWith(base + '/') ? route.continue() : route.abort()
  );
  const page = await context.newPage();
  page.setDefaultTimeout(12000);
  await page.goto(base + '/admin/login.php');
  await page.locator('[name=email]').fill(fixture.admin);
  await page.locator('[name=password]').fill(fixture.password);
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/admin/dashboard.php');
  return { page, context };
}
async function noOverflow(page, label) {
  const size = await page.evaluate(() => {
    const pageWidth = document.documentElement.scrollWidth;
    const visible = document.documentElement.clientWidth;
    const offenders = [...document.querySelectorAll('body *')]
      .map((element) => ({ element, box: element.getBoundingClientRect() }))
      .filter(({ box }) => box.right > visible + 1 || box.left < -1)
      .slice(0, 12)
      .map(({ element, box }) => ({
        selector: element.tagName.toLowerCase() + (element.className && typeof element.className === 'string'
          ? '.' + element.className.trim().replaceAll(' ', '.')
          : ''),
        left: Math.round(box.left),
        right: Math.round(box.right),
      }));
    const scrollContainers = [...document.querySelectorAll('html, body, body *')]
      .filter(element => element.scrollWidth > element.clientWidth + 1)
      .slice(0, 16)
      .map(element => ({
        selector: element.tagName.toLowerCase() +
          (typeof element.className === 'string' ? '.' + element.className.trim().replaceAll(' ', '.') : ''),
        client: element.clientWidth, scroll: element.scrollWidth,
      }));
    return { page: pageWidth, visible, offenders, scrollContainers };
  });
  assert(size.page <= size.visible + 1,
    label + ' overflows: ' + JSON.stringify(size));
}

try {
  const session = await signedIn();
  const page = session.page;
  assert.equal(await page.getByRole('heading', { name: 'Admin dashboard' }).count(), 1);
  assert.equal(await page.getByText('Under construction', { exact: true }).count(), 0);
  assert.equal(await page.locator('.construction-mark').count(), 0);
  const quick = page.getByRole('navigation', { name: 'Quick actions' });
  assert.equal(await quick.getByRole('link', { name: 'New intake', exact: true }).count(), 1);
  assert.equal(await quick.getByRole('link', { name: 'Review repairs', exact: true }).count(), 1);
  assert.equal(await quick.getByRole('link', { name: 'Cash desk', exact: true }).count(), 1);
  assert.equal(await page.getByRole('heading', { name: 'Recently updated devices' }).count(), 1);
  assert((await page.locator('.system-status').innerText()).includes('data loaded'));
  assert(!(await page.locator('.system-status').evaluate(el => el.classList.contains('is-unavailable'))));
  assert.equal(await page.locator('.metric-card').count(), 4);

  for (const width of [320, 390, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await page.reload();
    await noOverflow(page, 'Overview at ' + width + 'px');
    assert(await page.getByRole('link', { name: 'New intake' }).isVisible());
  }
  await page.setViewportSize({ width: 390, height: 844 });
  await page.screenshot({ path: 'mvp-overview-mobile.png', fullPage: true });

  await page.getByRole('link', { name: 'Repair workbench' }).click();
  assert(new URL(page.url()).searchParams.get('tab') === 'workbench');
  assert(!(await page.locator('.analytics-grid').isVisible()),
    'Overview KPIs leaked into the workbench tab.');
  await page.getByRole('link', { name: 'Customer roster' }).click();
  assert(!(await page.locator('.welcome-actions').isVisible()),
    'Quick actions leaked into the customer roster.');
  await page.goto(base + '/admin/dashboard.php');
  await page.getByRole('navigation', { name: 'Quick actions' }).getByRole('link', { name: 'New intake' }).click();
  assert(page.url().endsWith('/admin/intake_create.php'));
  await session.context.close();

  const noJs = await signedIn(false);
  await noJs.page.goto(base + '/admin/dashboard.php');
  assert(await noJs.page.getByRole('link', { name: 'Review repairs' }).isVisible());
  await noOverflow(noJs.page, 'No-JS mobile overview');

  // Emulate one query failure on the disposable runner, then restore source.
  const dataFile = 'app/dashboard_data.php';
  const original = readFileSync(dataFile, 'utf8');
  const anchor = '    $metrics = [];';
  assert.equal(original.split(anchor).length, 2);
  try {
    writeFileSync(dataFile, original.replace(anchor,
      "    throw new RuntimeException('Fixture data failure');\n" + anchor));
    await noJs.page.reload();
    assert((await noJs.page.locator('.system-status').innerText()).includes('data unavailable'));
    assert(await noJs.page.locator('.system-status').evaluate(el => el.classList.contains('is-unavailable')));
    assert(await noJs.page.locator('.sidebar-footer').evaluate(el => el.classList.contains('is-unavailable')));
    assert.equal(await noJs.page.locator('.metric-card .metric-value').first().innerText(), '—');
  } finally {
    writeFileSync(dataFile, original);
  }
  await noJs.context.close();
  console.log('PASS: admin overview actions, failure state, tabs, no-JS and 320–1440px fit.');
} finally {
  await browser.close();
}
