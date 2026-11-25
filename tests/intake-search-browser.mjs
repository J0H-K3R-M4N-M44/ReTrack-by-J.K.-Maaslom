import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';

const base = 'http://127.0.0.1:8742';
const browser = await chromium.launch({ channel: 'chrome' });

async function checkIntakeFieldSizing(page, label) {
  const sizes = await page.locator(
    '#customer-search, #intake-entry input:not([type=hidden]):not([type=checkbox]):not([type=radio]), ' +
    '#intake-entry select, #intake-entry textarea'
  ).evaluateAll(elements => elements
    .filter(element => element.getClientRects().length > 0)
    .map(element => ({
      name: element.name || element.id,
      height: element.getBoundingClientRect().height,
    })));
  assert(sizes.length > 0, label + ': no visible form controls found');
  assert(sizes.every(item => item.height >= 44),
    label + ': form control below 44px: ' + JSON.stringify(sizes));
}

try {
  for (const javaScriptEnabled of [true, false]) {
    const page = await browser.newPage({ javaScriptEnabled });
    await page.route('**/*', route =>
      route.request().url().startsWith(base + '/') ? route.continue() : route.abort()
    );
    await page.goto(base + '/admin/login.php');
    await page.locator('#email').fill('admin@example.test');
    await page.locator('#password').fill('DemoRepair2026!');
    await page.getByRole('button', { name: 'Login', exact: true }).click();
    await page.waitForURL('**/dashboard.php');
    await page.goto(base + '/admin/intake_create.php');
    const search = page.locator('#customer-search');
    const customer = page.locator('#customer-id');
    const formKey = await page.locator('[name=form_key]').inputValue();
    for (const width of [390, 320]) {
      await page.setViewportSize({ width, height: 850 });
      await checkIntakeFieldSizing(page, 'Customer step at ' + width + 'px');
    }
    await page.setViewportSize({ width: 1280, height: 850 });
    await search.fill('Demo Customer 01');
    await search.press('Enter');
    await customer.selectOption('1');
    if (javaScriptEnabled) {
      await page.locator('[data-step-next]').click();
      for (const width of [390, 320]) {
        await page.setViewportSize({ width, height: 850 });
        await checkIntakeFieldSizing(page, 'Device step at ' + width + 'px');
      }
      await page.setViewportSize({ width: 1280, height: 850 });
    }
    await page.locator('[name=model]').fill('Customer laptop');
    await page.locator('[value=add-device]').click();
    await page.locator('[name="extra_devices[0][model]"]').fill('Second laptop');
    if (javaScriptEnabled) await page.locator('[data-step-back]').click();
    await search.fill('No matching person test');
    await page.getByRole('button', { name: 'Search', exact: true }).click();
    assert(await page.getByText('No matching customers. Try a different name or contact.').isVisible());
    assert.equal(await customer.inputValue(), '1');
    assert.equal(await page.locator('[name=form_key]').inputValue(), formKey);
    assert.equal(await page.locator('[name=model]').inputValue(), 'Customer laptop');
    assert.equal(await page.locator('[name="extra_devices[0][model]"]').inputValue(), 'Second laptop');
    assert.equal(new URL(page.url()).search, '');
    await page.close();
    console.log('Passed customer lookup with two unfinished devices, JS enabled: ' + javaScriptEnabled);
  }
} finally {
  await browser.close();
}
