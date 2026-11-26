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


async function verifyLinenStatusContrast(page) {
  const returnUrl = page.url();
  await page.goto(base + '/admin/staff_home.php?view=queue');
  const results = await page.evaluate(() => {
    document.documentElement.dataset.theme = 'linen';
    const names = [
      'received', 'diagnosing', 'awaiting_customer_approval',
      'awaiting_parts', 'in_progress', 'testing',
      'ready_for_pickup', 'completed', 'cancelled',
    ];
    const container = document.createElement('div');
    container.style.cssText = 'position:absolute;left:-10000px;top:0;';
    document.body.append(container);
    const luminance = (color) => {
      const open = color.indexOf('(');
      const close = color.indexOf(')', open + 1);
      if (open < 0 || close <= open) {
        throw new Error('Unexpected computed color: ' + color);
      }
      const values = color.slice(open + 1, close).split(',').slice(0, 3).map(value => Number(value.trim()));
      if (values.length !== 3 || !values.every(Number.isFinite)) {
        throw new Error('Could not parse computed RGB color: ' + color);
      }
      const linear = values.map(value => {
        const channel = value / 255;
        return channel <= 0.04045
          ? channel / 12.92
          : ((channel + 0.055) / 1.055) ** 2.4;
      });
      return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
    };
    const rows = names.map(name => {
      const badge = document.createElement('span');
      badge.className = 'workspace-status status-' + name;
      badge.textContent = name;
      container.append(badge);
      const style = getComputedStyle(badge);
      const foreground = luminance(style.color);
      const background = luminance(style.backgroundColor);
      const lighter = Math.max(foreground, background);
      const darker = Math.min(foreground, background);
      return {
        status: name,
        foreground: style.color,
        background: style.backgroundColor,
        contrast: (lighter + 0.05) / (darker + 0.05),
      };
    });
    container.remove();
    return rows;
  });
  for (const row of results) {
    assert(row.contrast >= 4.5,
      'Linen status badge contrast below 4.5:1: ' + JSON.stringify(row));
  }
  await page.goto(returnUrl);
  console.log('PASS: nine repair status badges meet 4.5:1 contrast in Linen.');
}

try {
  const session = await signedIn();
  await verifyLinenStatusContrast(session.page);
  const page = session.page;
  assert.equal(await page.getByRole('heading', { name: 'Operations overview' }).count(), 1);
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
    if (width <= 720) {
      const mobileNavigation = await page.locator('.rt-dock > a').evaluateAll(items =>
        items.map(item => ({
          label: item.innerText.trim(),
          width: item.getBoundingClientRect().width,
          height: item.getBoundingClientRect().height,
        }))
      );
      assert.equal(mobileNavigation.length, 4,
        'Admin mobile navigation must show three shortcuts and More');
      assert(mobileNavigation.every(item =>
        item.label && item.width >= 44 && item.height >= 44
      ), 'Admin mobile shortcuts need visible labels and usable targets: ' +
        JSON.stringify(mobileNavigation));

      await page.locator('#rt-more').click();
      const destinations = page.locator('#rt-menu nav[aria-label="All destinations"] a');
      const names = await destinations.evaluateAll(items =>
        items.map(item => item.textContent.trim())
      );
      assert(names.includes('Overview') && names.includes('Workbench') &&
        names.includes('Customers') && names.includes('Staff accounts') &&
        names.includes('Cash desk') && names.includes('Reports'),
        'Admin More menu is missing a destination: ' + JSON.stringify(names));
      assert(await destinations.count() >= 10,
        'Admin More menu should expose the remaining authorized destinations');
    }
  }
  await page.setViewportSize({ width: 390, height: 844 });
  await page.screenshot({ path: 'mvp-overview-mobile.png', fullPage: false });

  await page.getByRole('link', { name: 'Workbench' }).click();
  assert(new URL(page.url()).searchParams.get('tab') === 'workbench');
  assert(!(await page.locator('.analytics-grid').isVisible()),
    'Overview KPIs leaked into the workbench tab.');
  await page.getByRole('link', { name: 'Customers' }).click();
  assert(!(await page.locator('.welcome-actions').isVisible()),
    'Quick actions leaked into the customer roster.');
  await page.goto(base + '/admin/dashboard.php');
  await page.getByRole('navigation', { name: 'Quick actions' }).getByRole('link', { name: 'New intake' }).click();
  assert(page.url().endsWith('/admin/intake_create.php'));
  await session.context.close();

  const noJs = await signedIn(false);
  await noJs.page.goto(base + '/admin/dashboard.php');
  assert(await noJs.page.getByRole('navigation', { name: 'Quick actions' }).getByRole('link', { name: 'Review repairs', exact: true }).isVisible());
  await noOverflow(noJs.page, 'No-JS mobile overview');

  // Emulate one query failure on the disposable runner, then restore source.
  const dataFile = 'app/dashboard_data.php';
  const original = readFileSync(dataFile, 'utf8');
  const anchor = '    $metrics = [];';
  assert.equal(original.split(anchor).length, 2);
  try {
    writeFileSync(dataFile, original.replace(anchor,
      "    throw new RuntimeException('Fixture data failure');\n" + anchor));
    const failureResponse = await noJs.page.goto(base + '/admin/dashboard.php?failure_test=1');
    const failureStatusText = (await noJs.page.locator('.system-status').innerText()).toLowerCase();
    assert(failureStatusText.includes('data unavailable'),
      'Simulated query failure did not reach dashboard: ' + JSON.stringify({
        statusText: failureStatusText, httpStatus: failureResponse.status(),
        url: noJs.page.url(),
      }));
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
