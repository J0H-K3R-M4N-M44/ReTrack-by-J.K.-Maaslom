import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright-core';
const url = process.env.REPAIRTRACK_TEST_URL;
const site = process.env.REPAIRTRACK_TEST_SITE;
assert(url && ['localhost', '127.0.0.1'].includes(new URL(url).hostname));
const fixture = JSON.parse(fs.readFileSync(site + '/tests/public-fixture.json'));
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
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto(url + '/track.php#code=' + fixture.code);
  assert.equal(await page.locator('#tracking-code').inputValue(), fixture.code);
  assert.equal(new URL(page.url()).hash, '');
  await page.getByRole('button', { name: 'View repair' }).click();
  await page.getByRole('heading', { name: 'Your repair update' }).waitFor();
  assert(await page.getByRole('heading', { name: 'Current shared estimate' }).isVisible());
  assert((await page.locator('.public-estimate').innerText()).includes('99.00'));
  assert(!(await page.locator('.public-estimate').innerText()).includes('325.35'));
  assert(await page.getByRole('heading', { name: 'Next step' }).first().isVisible());
  assert.equal(await page.locator('.public-device-card').count(), fixture.device_count,
    'Customer portal must keep each device in its own card');
  assert(await page.getByRole('heading', { name: 'Devices on this ticket' }).isVisible());
  assert((await page.locator('.public-device-card').first().locator('.public-device-label').innerText())
    .includes('Device 1 of ' + fixture.device_count));
  const secondDeviceCard = page.locator(
    '.public-device-card[data-item-code="' + fixture.second_item_code + '"]'
  );
  assert.equal(await secondDeviceCard.count(), 1,
    'Additional device is not shown as its own card');
  assert((await secondDeviceCard.locator('.public-device-label').innerText())
    .includes('Device ' + fixture.device_count + ' of ' + fixture.device_count));
  const longMessage = secondDeviceCard.locator('.public-message');
  assert((await longMessage.innerText()).length > 500,
    'Long customer-visible update was truncated');
  const timelineEntry = page.locator('.public-timeline-entry').first();
  assert((await timelineEntry.innerText()).includes(fixture.second_item_code),
    'Timeline update is not identified with its device');
  const timestamp = timelineEntry.locator('time');
  assert(await timestamp.getAttribute('datetime'),
    'Timeline timestamp is missing machine-readable datetime');
  assert(!Number.isNaN(Date.parse(await timestamp.getAttribute('datetime'))),
    'Timeline datetime value is invalid');
  assert((await timestamp.getAttribute('datetime')).endsWith('+08:00'),
    'Timeline timestamp must retain the Philippine time offset');
  assert.equal(await page.locator(
    '.public-device-card button, .public-device-card form, .public-device-card input, ' +
    '.public-device-card select, .public-device-card textarea'
  ).count(), 0, 'Device details must remain read-only for customers');
  assert(await page.getByRole('heading', { name: 'Need help with this repair?' }).isVisible());
  const branchContact = page.locator('.public-contact-card');
  const branchPhone = branchContact.locator('a[href^="tel:"]');
  if (await branchPhone.count()) {
    assert.match(await branchPhone.getAttribute('href'), /^tel:\+?[0-9]+$/,
      'Branch phone link is not a safe tap-to-call number');
    assert((await branchPhone.boundingBox()).height >= 44,
      'Branch phone link is too small for touch');
  } else {
    assert((await branchContact.innerText()).includes('phone number is not listed'),
      'Missing branch phone must have a clear contact fallback');
  }
  assert(!(await page.content()).includes('PRIVATE_SENTINEL'));
  assert.equal(
    await page.locator('script:not([src])').count(),
    0,
    'Customer message became executable'
  );
  for (const width of [1440, 768, 390, 320]) {
    await page.setViewportSize({ width, height: 900 });
    assert(
      await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      `Tracking overflow at ${width}`
    );
    assert(
      await secondDeviceCard.locator('.public-message')
        .evaluate(element => element.scrollWidth <= element.clientWidth + 1),
      `Long customer update overflows at ${width}`
    );
    assert(
      await page.locator('.public-timeline time').first().isVisible(),
      `Timeline timestamp is hidden at ${width}`
    );
  }
  await page.screenshot({ path: site + '/public-tracking-mobile.png', fullPage: true });
  await page.getByRole('button', { name: 'Close tracking' }).click();
  assert(await page.locator('#tracking-code').isVisible());
  const nojs = await browser.newContext({
    javaScriptEnabled: false,
    viewport: { width: 390, height: 844 },
  });
  const visitor = await nojs.newPage();
  await visitor.goto(url + '/track.php');
  await visitor.locator('#tracking-code').fill(fixture.code);
  await visitor.getByRole('button', { name: 'View repair' }).click();
  assert(await visitor.getByRole('heading', { name: 'Your repair update' }).isVisible());
  assert((await visitor.locator('.public-estimate').innerText()).includes('99.00'));
  await page.goto(url + '/admin/login.php');
  await page.locator('[name=email]').fill('manager.console@example.test');
  await page.locator('[name=password]').fill(['ConsoleDemo', '2026!'].join(''));
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/staff_home.php');
  await page.locator('#queue-status').selectOption('DIAGNOSING');
  await page.getByRole('button', { name: 'Search', exact: true }).click();
  assert.equal(new URL(page.url()).searchParams.get('status'), 'DIAGNOSING');
  for (const width of [1440, 390, 320]) {
    await page.setViewportSize({ width, height: 900 });
    assert(
      await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      `Console overflow at ${width}`
    );
  }
  await page.locator('#queue-status').selectOption('');
  await page.getByRole('button', { name: 'Search', exact: true }).click();
  await page.waitForURL(currentUrl => new URL(currentUrl).searchParams.get('status') !== 'DIAGNOSING');
  await page.setViewportSize({ width: 390, height: 900 });
  const mobileQueue = await page.locator('.workspace-table').evaluate(table => ({
    rowDisplay: getComputedStyle(table.tBodies[0].rows[0]).display,
    tableMinWidth: getComputedStyle(table).minWidth,
    tableRole: table.getAttribute('role'),
    rowRoles: [...table.tBodies[0].rows].every(row => row.getAttribute('role') === 'row'),
    cellRoles: [...table.tBodies[0].rows].every(row =>
      [...row.cells].every(cell => cell.getAttribute('role') === 'cell')),
    headerRoles: [...table.tHead.rows].every(row =>
      [...row.cells].every(cell => cell.getAttribute('role') === 'columnheader')),
    allCellsHaveLabels: [...table.tBodies[0].rows].every(row =>
      [...row.cells].every(cell => Boolean(cell.dataset.label))),
    wrapperFits: table.parentElement.scrollWidth <= table.parentElement.clientWidth + 1,
  }));
  assert.equal(mobileQueue.rowDisplay, 'grid', 'Mobile queue row did not become a record card');
  assert.equal(mobileQueue.tableMinWidth, '0px', 'Mobile queue retained a wide table minimum');
  assert.equal(mobileQueue.tableRole, 'table', 'Mobile queue must retain table semantics');
  assert(mobileQueue.rowRoles && mobileQueue.cellRoles && mobileQueue.headerRoles,
    'Mobile queue must retain row, cell and column-header roles');
  assert(mobileQueue.allCellsHaveLabels, 'Mobile queue cards are missing visible field labels');
  assert(mobileQueue.wrapperFits, 'Mobile queue still requires horizontal scrolling');

  // Verify that actual staff form controls follow the selected theme, not legacy defaults.
  await page.locator('#rt-menu summary').click();
  await page.locator('[data-rt-setting="theme"]').selectOption('linen');
  await page.locator('#rt-menu summary').click();
  const themedSearch = await page.locator('#queue-search').evaluate(element => ({
    background: getComputedStyle(element).backgroundColor,
    color: getComputedStyle(element).color,
    border: getComputedStyle(element).borderTopColor,
  }));
  assert.deepEqual(themedSearch, {
    background: 'rgb(255, 253, 249)',
    color: 'rgb(20, 32, 43)',
    border: 'rgb(131, 145, 151)',
  }, 'Staff queue search field does not follow Linen theme tokens');
  const themedFilter = await page.locator('#queue-status').evaluate(element => ({
    background: getComputedStyle(element).backgroundColor,
    color: getComputedStyle(element).color,
  }));
  assert.deepEqual(themedFilter, {
    background: 'rgb(255, 253, 249)',
    color: 'rgb(20, 32, 43)',
  }, 'Staff queue filter does not follow Linen theme tokens');

  // The status board must use the same active theme tokens as the queue.
  await page.goto(url + '/admin/staff_home.php?view=board');
  for (const width of [1440, 768, 390, 320]) {
    await page.setViewportSize({ width, height: 900 });
    assert(
      await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      'Status board overflows at ' + width + 'px'
    );
  }
  const boardPalette = await page.locator('.board-lane').first().evaluate(lane => {
    const card = lane.querySelector('.board-card');
    const small = lane.querySelector('h3 small');
    if (!card || !small) return null;
    const laneStyle = getComputedStyle(lane);
    const cardStyle = getComputedStyle(card);
    return {
      laneBackground: laneStyle.backgroundColor,
      laneText: laneStyle.color,
      laneBorder: laneStyle.borderTopColor,
      laneSecondaryText: getComputedStyle(small).color,
      cardBackground: cardStyle.backgroundColor,
      cardText: cardStyle.color,
      cardBorder: cardStyle.borderTopColor,
    };
  });
  assert.deepEqual(boardPalette, {
    laneBackground: 'rgb(255, 253, 249)',
    laneText: 'rgb(20, 32, 43)',
    laneBorder: 'rgb(131, 145, 151)',
    laneSecondaryText: 'rgb(69, 87, 99)',
    cardBackground: 'rgb(230, 239, 237)',
    cardText: 'rgb(20, 32, 43)',
    cardBorder: 'rgb(131, 145, 151)',
  }, 'Status-board lane and card surfaces must follow Linen theme tokens');

  const boardReadingOrder = await page.locator('.status-board').evaluate(board => {
    const lanes = [...board.querySelectorAll(':scope > .board-lane')];
    return {
      label: board.getAttribute('aria-label'),
      lanes: lanes.map(lane => {
        const heading = lane.querySelector(':scope > h3');
        const cards = [...lane.querySelectorAll(':scope > .board-card')];
        return {
          role: lane.getAttribute('role'),
          labelledBy: lane.getAttribute('aria-labelledby'),
          headingId: heading?.id || '',
          cards: cards.map(card => {
            const cardHeading = card.querySelector(':scope > h4');
            return {
              labelledBy: card.getAttribute('aria-labelledby'),
              headingId: cardHeading?.id || '',
              headingTag: cardHeading?.tagName || '',
              linkCount: cardHeading?.querySelectorAll(':scope > a').length || 0,
              firstChildTag: card.firstElementChild?.tagName || '',
            };
          }),
        };
      }),
    };
  });
  assert.equal(boardReadingOrder.label, 'Repair devices grouped by status');
  assert(boardReadingOrder.lanes.length > 0, 'Status board has no labelled status groups');
  for (const lane of boardReadingOrder.lanes) {
    assert.equal(lane.role, 'group');
    assert(lane.headingId && lane.labelledBy === lane.headingId,
      'Status lane is not labelled by its heading');
    assert(lane.cards.length > 0, 'Status lane has no repair cards');
    for (const card of lane.cards) {
      assert(card.headingId && card.labelledBy === card.headingId,
        'Repair card is not labelled by its device heading');
      assert.equal(card.headingTag, 'H4');
      assert.equal(card.linkCount, 1);
      assert.equal(card.firstChildTag, 'H4',
        'Repair card reading order must start with its named device link');
    }
  }

  await page.setViewportSize({ width: 320, height: 900 });
  const mobileLanePositions = await page.locator('.board-lane').evaluateAll(lanes =>
    lanes.map(lane => lane.getBoundingClientRect().top)
  );
  assert(mobileLanePositions.every((top, index) =>
    index === 0 || top >= mobileLanePositions[index - 1]),
    'Mobile status lanes do not follow their document reading order');

  await page.setViewportSize({ width: 390, height: 900 });
  await page.screenshot({ path: site + '/mvp-board-mobile.png', fullPage: true });

  await page.goto(url + '/admin/staff_home.php?view=history&q=' + encodeURIComponent(fixture.history_item_code));
  await page.getByRole('heading', { name: 'Repair history' }).waitFor();
  const historyTable = page.locator('#work-list .workspace-table');
  assert.equal(await historyTable.count(), 1);
  for (const width of [1440, 768, 390, 320]) {
    await page.setViewportSize({ width, height: 900 });
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      'History page overflows at ' + width + 'px');
    const historyLayout = await historyTable.evaluate(table => {
      const row = table.tBodies[0].rows[0];
      const headers = [...table.tHead.rows[0].cells].map(cell => cell.textContent.trim());
      const labels = [...row.cells].map(cell => cell.dataset.label || '');
      return {
        rowDisplay: getComputedStyle(row).display,
        minWidth: getComputedStyle(table).minWidth,
        role: table.getAttribute('role'),
        headerRoles: [...table.tHead.rows[0].cells].every(cell => cell.getAttribute('role') === 'columnheader'),
        rowRole: row.getAttribute('role'),
        cellRoles: [...row.cells].every(cell => cell.getAttribute('role') === 'cell'),
        labelsMatch: headers.length === labels.length && headers.every((header, index) => header === labels[index]),
        wrapperFits: table.parentElement.scrollWidth <= table.parentElement.clientWidth + 1,
      };
    });
    assert.equal(historyLayout.role, 'table');
    assert.equal(historyLayout.rowRole, 'row');
    assert(historyLayout.headerRoles && historyLayout.cellRoles && historyLayout.labelsMatch,
      'Repair history cards do not preserve matching table labels and roles');
    if (width <= 767) {
      assert.equal(historyLayout.rowDisplay, 'grid');
      assert.equal(historyLayout.minWidth, '0px');
      assert(historyLayout.wrapperFits, 'Mobile repair history requires horizontal scrolling');
    }
  }
  await page.setViewportSize({ width: 390, height: 900 });
  await page.screenshot({ path: site + '/mvp-history-mobile.png', fullPage: true });

  await page.goto(url + '/admin/staff_home.php');

  await page.screenshot({ path: site + '/mvp-queue-mobile.png', fullPage: true });
  await page.goto(url + '/admin/ticket.php?id=' + fixture.ticket);
  assert.equal(
    (
      await page.request.post(url + '/admin/ticket.php?id=' + fixture.ticket, {
        form: { csrf_token: 'invalid', confirm_share: '1' },
      })
    ).status(),
    403
  );
  await page.locator('[name=confirm_share]').check();
  await page.getByRole('button', { name: 'Create tracking code and PIN' }).click();
  assert.equal(
    await page.locator('#private-code').count(),
    1,
    await page.locator('main').innerText()
  );
  assert.match(await page.locator('#private-code').inputValue(), /^[A-F0-9]{5}$/);
  await visitor.reload();
  assert(
    await visitor.locator('#tracking-code').isVisible(),
    'Existing session survived replacement'
  );
  await page.reload();
  assert.equal(
    await page.locator('#private-code').count(),
    0,
    'Private code was not one-time display'
  );
  assert.deepEqual(errors, []);
  console.log(
    'PASS: customer flow, multi-device cards, long updates, Philippine timestamps, read-only device details, escaped updates, access revocation, CSRF, filters and mobile layout.'
  );
} finally {
  await browser.close();
}
