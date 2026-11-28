import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright-core';

const url = process.env.REPAIRTRACK_TEST_URL,
  site = process.env.REPAIRTRACK_TEST_SITE;
assert(url && ['localhost', '127.0.0.1'].includes(new URL(url).hostname));
const f = JSON.parse(fs.readFileSync(site + '/tests/workspace-fixture.json'));
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH || process.cwd() + '/qa/chromium',
  args: ['--no-sandbox'],
  env: { ...process.env, LD_LIBRARY_PATH: process.cwd() + '/qa' },
});

async function allowLocalOnly(context) {
  await context.route('**/*', (route) =>
    route.request().url().startsWith(url) ? route.continue() : route.abort()
  );
}

async function login(page, email, password) {
  await page.goto(url + '/admin/login.php');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/staff_home.php');
}

async function assertVisualOrder(locator, description) {
  const positions = await locator.evaluateAll((elements) =>
    elements.map((element, index) => {
      const bounds = element.getBoundingClientRect();
      return { index, top: Math.round(bounds.top), left: Math.round(bounds.left) };
    })
  );
  const visualOrder = [...positions].sort((a, b) => {
    if (Math.abs(a.top - b.top) > 2) return a.top - b.top;
    return a.left - b.left;
  });
  assert.deepEqual(
    visualOrder.map((item) => item.index),
    positions.map((item) => item.index),
    `${description} visual order differs from DOM reading order`
  );
}

async function checkRoleOverview(role, email, expectedHeading, expectedCard) {
  const roleContext = await browser.newContext({
    viewport: { width: 1440, height: 1000 },
  });
  await allowLocalOnly(roleContext);
  const rolePage = await roleContext.newPage();
  rolePage.on('pageerror', (error) => errors.push(error.message));

  await login(rolePage, email, 'ConsoleDemo2026!');
  assert.equal(
    await rolePage.locator('[data-role-overview]').getAttribute('data-role-overview'),
    role,
    `${role} did not land on its own overview`
  );
  assert(await rolePage.getByRole('heading', { name: expectedHeading, exact: true }).isVisible());
  assert(await rolePage.locator('.rt-overview-cards article').count() > 0);
  assert(await rolePage.locator('.rt-overview-actions a').count() > 0);
  assert(await rolePage.locator('.rt-overview').getByText(expectedCard, { exact: false }).count() > 0);
  assert(await rolePage.locator('.rt-dock a').first().getAttribute('href') === 'staff_home.php?view=overview');

  const help = rolePage.locator('.rt-help-tip').first();
  const helpSummary = help.locator('summary');
  await helpSummary.hover();
  assert(await help.evaluate((element) => element.open), `${role} help tooltip did not open on hover`);
  await rolePage.mouse.move(1200, 900);
  assert(!(await help.evaluate((element) => element.open)), `${role} help tooltip did not close on pointer leave`);
  await helpSummary.evaluate((element) => element.focus({ focusVisible: true }));
  assert(await help.evaluate((element) => element.open), `${role} help tooltip did not open on keyboard focus`);
  await rolePage.keyboard.press('Escape');
  assert(!(await help.evaluate((element) => element.open)), `${role} help tooltip did not close on Escape`);

  for (const width of [390, 320]) {
    await rolePage.setViewportSize({ width, height: 844 });
    assert(
      await rolePage.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      `${role} overview overflow at ${width}px`
    );
  }

  await rolePage.setViewportSize({ width: 390, height: 844 });
  await helpSummary.click();
  assert(await help.evaluate((element) => element.open), `${role} help tooltip did not open on tap/click`);
  const tooltipBounds = await help.locator('.rt-help-tooltip').boundingBox();
  assert(
    tooltipBounds && tooltipBounds.x >= 0 && tooltipBounds.x + tooltipBounds.width <= 390,
    `${role} help tooltip leaves 390px viewport`
  );
  await rolePage.keyboard.press('Escape');
  assert(!(await help.evaluate((element) => element.open)), `${role} tap/click help tooltip did not close on Escape`);
  await rolePage.screenshot({
    path: site + '/workspace-overview-' + role.toLowerCase() + '.png',
    fullPage: true,
  });
  await roleContext.close();
}

const errors = [];
try {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  await allowLocalOnly(context);
  const page = await context.newPage();
  page.on('pageerror', (error) => errors.push(error.message));

  await page.goto(url + '/admin/login.php');
  await page.locator('#show-password').click();
  assert.equal(await page.locator('#password').getAttribute('type'), 'text');
  await page.locator('#show-password').click();
  await page.locator('#email').fill('manager.console@example.test');
  await page.locator('#password').fill('ConsoleDemo2026!');
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/staff_home.php');

  assert.equal(
    await page.locator('[data-role-overview]').getAttribute('data-role-overview'),
    'MANAGER'
  );
  assert(await page.getByRole('heading', { name: 'Branch overview', exact: true }).isVisible());
  assert(await page.locator('.rt-overview-cards article').count() === 4);
  await page.screenshot({ path: site + '/workspace-overview-manager.png', fullPage: true });

  // Help text is available on pointer hover and keyboard focus, and Escape closes it.
  const managerHelp = page.locator('.rt-help-tip').first(),
    managerHelpSummary = managerHelp.locator('summary');
  await managerHelpSummary.hover();
  assert(await managerHelp.evaluate((element) => element.open));
  await page.mouse.move(1200, 900);
  assert(!(await managerHelp.evaluate((element) => element.open)));
  await managerHelpSummary.evaluate((element) => element.focus({ focusVisible: true }));
  assert(await managerHelp.evaluate((element) => element.open));
  await page.keyboard.press('Escape');
  assert(!(await managerHelp.evaluate((element) => element.open)));

  // Collapsed icon navigation shows a compact label without replacing its accessible name.
  const rail = page.locator('#rt-rail');
  await rail.click();
  const navLink = page.locator('.rt-side a[data-tooltip]').first(),
    navTooltip = page.locator('.rt-floating-tooltip');
  await navLink.hover();
  await navTooltip.waitFor({ state: 'visible' });
  assert.equal((await navTooltip.innerText()).trim(), await navLink.getAttribute('aria-label'));
  assert.equal(await navLink.getAttribute('title'), null, 'Native title bubble should not duplicate the custom hover label');
  await page.mouse.move(1200, 900);
  await navTooltip.waitFor({ state: 'hidden' });
  assert.equal(await navLink.getAttribute('title'), 'Overview', 'Native title fallback was not restored');
  await navLink.evaluate((element) => element.focus({ focusVisible: true }));
  await navTooltip.waitFor({ state: 'visible' });
  await page.keyboard.press('Escape');
  await navTooltip.waitFor({ state: 'hidden' });
  await rail.click();

  await page.getByRole('link', { name: 'Repair queue', exact: true }).click();
  assert((await page.locator('tbody tr').count()) <= 10);
  await page.getByRole('link', { name: 'Status board', exact: true }).click();
  assert.equal(await page.locator('table').count(), 0);
  assert((await page.locator('.board-card').count()) <= 10);
  const point = page.locator('.trend-point').first();
  await point.locator('summary').hover();
  assert(await point.evaluate((element) => element.open));
  await point.locator('summary').focus();
  await page.keyboard.press('Escape');
  assert(!(await point.evaluate((element) => element.open)));
  const boardGroup = page.getByRole('group', { name: 'Repair devices grouped by status' });
  assert(await boardGroup.isVisible(), 'Status board group has no accessible name');
  const lanes = boardGroup.locator('.board-lane');
  assert((await lanes.count()) > 0, 'Status board has no populated lanes');
  for (let index = 0; index < await lanes.count(); index++) {
    const lane = lanes.nth(index);
    const heading = lane.locator('h3');
    const headingId = await heading.getAttribute('id');
    assert.equal(await lane.getAttribute('role'), 'group');
    assert.equal(await lane.getAttribute('aria-labelledby'), headingId);
    assert((await heading.innerText()).trim().length > 0, 'Status lane heading is empty');
    const cards = lane.locator('.board-card');
    assert((await cards.count()) > 0, 'Empty status lane was rendered');
    for (let cardIndex = 0; cardIndex < await cards.count(); cardIndex++) {
      const card = cards.nth(cardIndex);
      const cardHeadingId = await card.getAttribute('aria-labelledby');
      assert(cardHeadingId, 'Board card has no accessible heading');
      const cardHeading = page.locator('#' + cardHeadingId);
      assert.equal(await cardHeading.count(), 1);
      assert.equal(await cardHeading.getByRole('link').count(), 1);
    }
    await assertVisualOrder(cards, 'Cards in ' + (await heading.innerText()));
  }
  await assertVisualOrder(lanes, 'Status lanes');
  await page.screenshot({ path: site + '/workspace-board-desktop.png', fullPage: true });

  for (const width of [1440, 768, 390, 360, 320]) {
    await page.setViewportSize({ width, height: 900 });
    assert(
      await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      `Board overflow ${width}`
    );
    await assertVisualOrder(lanes, `Status lanes at ${width}px`);
    for (let index = 0; index < await lanes.count(); index++) {
      await assertVisualOrder(lanes.nth(index).locator('.board-card'), `Board cards at ${width}px`);
    }
    if (width === 390 || width === 320) {
      await page.screenshot({ path: site + `/workspace-board-mobile-${width}.png`, fullPage: true });
    }
  }

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.getByRole('link', { name: 'History', exact: true }).click();
  await page.locator('#queue-search').fill('WORK-0');
  await page.getByRole('button', { name: 'Search', exact: true }).click();
  await page.locator('tbody tr').filter({ hasText: 'WORK-0' }).waitFor();
  assert.equal(await page.locator('table').count(), 1);
  const historyRow = page.locator('tbody tr').first();
  await page.screenshot({ path: site + '/workspace-history-desktop.png', fullPage: true });
  for (const width of [1440, 768, 390, 360, 320]) {
    await page.setViewportSize({ width, height: width === 320 ? 568 : 900 });
    assert(
      await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      `History overflow ${width}px`
    );
    if (width <= 767) {
      const cells = await historyRow.locator('td').evaluateAll((elements) =>
        elements.map((element) => ({
          label: element.dataset.label,
          renderedLabel: getComputedStyle(element, '::before').content.replace(/^["']|["']$/g, ''),
        }))
      );
      assert(cells.length > 0 && cells.every((cell) => cell.label && cell.renderedLabel === cell.label),
        `History record labels are missing or out of sync at ${width}px`);
      assert.equal(await page.locator('.workspace-table tbody').evaluate((element) => getComputedStyle(element).display), 'grid');
    }
    if (width === 390 || width === 320) {
      await page.screenshot({ path: site + `/workspace-history-mobile-${width}.png`, fullPage: true });
    }
  }

  // The fixed mobile dock must remain reachable and the More panel must not cover it.
  await page.setViewportSize({ width: 320, height: 568 });
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  const dock = page.locator('.rt-dock');
  const dockBounds = await dock.boundingBox();
  assert.equal(await dock.evaluate((element) => getComputedStyle(element).position), 'fixed');
  assert(dockBounds && dockBounds.y + dockBounds.height >= 566, 'Mobile dock is not fixed to the viewport bottom');
  const dockTargets = await dock.locator('a').evaluateAll((elements) =>
    elements.map((element) => element.getBoundingClientRect().height)
  );
  assert(dockTargets.length === 4 && dockTargets.every((height) => height >= 44),
    'Mobile dock targets must remain at least 44px high');
  const contentPadding = await page.locator('#rt-main').evaluate((element) =>
    parseFloat(getComputedStyle(element).paddingBottom)
  );
  assert(contentPadding >= dockBounds.height, 'Page content does not reserve space above the fixed dock');

  await page.locator('#rt-more').click();
  assert(await page.locator('#rt-menu').evaluate((element) => element.open), 'More did not open the workspace menu');
  const menuBounds = await page.locator('.rt-menu-body').boundingBox();
  const currentDockBounds = await dock.boundingBox();
  assert(menuBounds && currentDockBounds && menuBounds.y >= 0 && menuBounds.y + menuBounds.height <= currentDockBounds.y,
    'More panel overlaps the fixed mobile dock');
  await page.screenshot({ path: site + '/workspace-mobile-dock-more-320.png', fullPage: true });
  await page.keyboard.press('Escape');
  assert(!(await page.locator('#rt-menu').evaluate((element) => element.open)), 'Escape did not close More');
  assert.equal(await page.evaluate(() => document.activeElement.id), 'rt-more',
    'Escape did not return focus to the More trigger');

  await page.setViewportSize({ width: 360, height: 900 });
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  const tallDockBounds = await dock.boundingBox();
  assert(tallDockBounds && tallDockBounds.y + tallDockBounds.height >= 898,
    'Mobile dock moved away from the viewport bottom after scrolling');

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.getByRole('link', { name: 'Open device', exact: true }).click();
  await page.getByRole('link', { name: 'Back to console' }).click();
  assert.equal(new URL(page.url()).searchParams.get('view'), 'history');
  assert.equal(new URL(page.url()).searchParams.get('q'), 'WORK-0');
  await page.locator('tbody tr').filter({ hasText: 'WORK-0' }).waitFor();

  // Each operational role has its own summary while retaining the same shell.
  await checkRoleOverview(
    'INTAKE_AGENT',
    'intake_agent.console@example.test',
    'Intake overview',
    'Saved intake drafts'
  );
  await checkRoleOverview(
    'TECHNICIAN',
    'technician.console@example.test',
    'My repair bench',
    'Assigned active devices'
  );
  await checkRoleOverview(
    'COURIER',
    'courier.console@example.test',
    'Transfer overview',
    'Open handovers'
  );
  await checkRoleOverview(
    'VIEWER',
    'viewer.console@example.test',
    'Branch snapshot',
    'Visible open repairs'
  );

  const cashContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  await allowLocalOnly(cashContext);
  const cash = await cashContext.newPage();
  cash.on('pageerror', (error) => errors.push(error.message));
  await login(cash, 'cashier.workspace@example.test', 'WorkspaceDemo2026!');
  assert.equal(
    await cash.locator('[data-role-overview]').getAttribute('data-role-overview'),
    'CASHIER'
  );
  assert(await cash.getByRole('heading', { name: 'Cash desk overview', exact: true }).isVisible());
  assert(await cash.locator('.rt-overview').getByText('Non-draft bill records').isVisible());
  await cash.screenshot({ path: site + '/workspace-overview-cashier.png', fullPage: true });

  await cash.locator('#overview-bill-search').fill(f.code);
  await cash.getByRole('button', { name: 'Find bill', exact: true }).click();
  await cash.locator('tbody tr').filter({ hasText: 'WORK-BILL' }).waitFor();
  assert.equal(new URL(cash.url()).searchParams.get('view'), 'cash');
  assert.equal((await cash.request.get(url + '/admin/device.php?id=' + f.device)).status(), 403);
  const cashRow = cash.locator('tbody tr').first();
  for (const width of [1440, 768, 390, 360, 320]) {
    await cash.setViewportSize({ width, height: 844 });
    assert(await cash.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      `Cash desk overflow ${width}px`);
    if (width <= 767) {
      const cells = await cashRow.locator('td').evaluateAll((elements) =>
        elements.map((element) => ({
          label: element.dataset.label,
          renderedLabel: getComputedStyle(element, '::before').content.replace(/^["']|["']$/g, ''),
        }))
      );
      assert(cells.length === 9 && cells.every((cell) => cell.label && cell.renderedLabel === cell.label),
        `Cash desk record labels are missing or out of sync at ${width}px`);
      const actionBounds = await cashRow.getByRole('link', { name: 'Open cash record' }).boundingBox();
      assert(actionBounds && actionBounds.height >= 44, 'Cash record action target is smaller than 44px');
    }
    if (width === 390 || width === 320) {
      await cash.screenshot({ path: site + `/workspace-cash-mobile-${width}.png`, fullPage: true });
    }
  }
  await cashContext.close();

  const visitorContext = await browser.newContext();
  await allowLocalOnly(visitorContext);
  const visitor = await visitorContext.newPage();
  await visitor.goto(url + '/track.php#code=' + f.code);
  await visitor.locator('#portal-pin').fill(f.pin === '0000' ? '1111' : '0000');
  await visitor.getByRole('button', { name: 'View repair' }).click();
  assert(await visitor.getByRole('alert').isVisible());
  await visitor.locator('#tracking-code').fill(f.code);
  await visitor.locator('#portal-pin').fill(f.pin);
  await visitor.getByRole('button', { name: 'View repair' }).click();
  assert(await visitor.getByRole('heading', { name: 'Your repair update' }).isVisible());

  const touchContext = await browser.newContext({
    hasTouch: true,
    isMobile: true,
    viewport: { width: 390, height: 844 },
    storageState: await context.storageState(),
  });
  await allowLocalOnly(touchContext);
  const touch = await touchContext.newPage();
  await touch.goto(url + '/admin/staff_home.php?view=queue');
  await touch.locator('.trend-point summary').first().tap();
  assert(
    await touch.locator('.trend-point').first().evaluate((element) => element.open)
  );

  // Check every date, including the first cell of the wrapped second row.
  for (const width of [320, 390, 700]) {
    await touch.setViewportSize({ width, height: 900 });
    for (const trendPoint of await touch.locator('.trend-point').all()) {
      await touch.locator('.trend-point').evaluateAll((points) =>
        points.forEach((element) => (element.open = false))
      );
      await trendPoint.locator('summary').tap();
      const bounds = await trendPoint.locator('.trend-tooltip').boundingBox();
      assert(
        bounds && bounds.x >= 0 && bounds.x + bounds.width <= width,
        'Trend tooltip leaves viewport'
      );
    }
  }

  // The same native disclosure behavior works with touch on role metric help.
  await touch.goto(url + '/admin/staff_home.php');
  const touchHelp = touch.locator('.rt-help-tip').first();
  await touchHelp.locator('summary').tap();
  assert(await touchHelp.evaluate((element) => element.open));

  assert.deepEqual(errors, []);
  console.log(
    'PASS: role overviews, scoped actions, navigation labels, help/trend tooltips, cashier scope and mobile layouts.'
  );
} finally {
  await browser.close();
}
