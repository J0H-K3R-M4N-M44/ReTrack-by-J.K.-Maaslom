import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH,
  channel: process.env.CHROME_PATH ? undefined : 'chrome',
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

try {
  for (const width of [390, 1440]) {
    const page = await browser.newPage({ viewport: { width, height: 900 } });
    await page.goto(pathToFileURL(resolve('demo-prototype/index.html')).href);
    await page.evaluate(() => {
      state.settings.photosRequired = true;
      saveDemo();
      setRole('Cashier');
      go('intake');
    });
    const form = page.locator('[data-form=intake]');
    const next = () => form.getByRole('button', { name: 'Continue', exact: true }).click();
    await form.getByLabel('Customer name').fill('Jo Reyes');
    await form.getByLabel('Phone or email').fill('x');
    await next();
    assert.equal(await form.getByLabel('Phone or email').evaluate(el => el.validity.customError), true);
    await form.getByLabel('Phone or email').fill('0917-123-4567');
    await next();
    for (const serial of ['AS-101', 'AS-102']) {
      await form.getByLabel('Device brand and model').fill('Acer Aspire');
      await form.getByLabel('Serial number').fill(serial);
      await form.getByLabel('Reported problem').fill('No power');
      await form.getByLabel('Visible condition').fill('Scratched case');
      await form.getByLabel('Accessories').fill('Charger');
      await form.getByRole('button', { name: 'Back to customer' }).click();
      await next();
      assert.equal(await form.getByLabel('Serial number').inputValue(), serial);
      if (serial === 'AS-101') {
        await form.getByLabel('Condition photo').fill('first.jpg');
        await form.getByRole('checkbox').check();
      }
      await next();
      if (serial === 'AS-101') await form.getByRole('button', { name: 'Add another device' }).click();
    }
    await form.getByRole('button', { name: 'Save intake draft' }).click();
    await page.getByRole('button', { name: 'Review & issue' }).click();
    const dialog = page.locator('dialog');
    const devices = dialog.locator('fieldset');
    assert.equal(await devices.count(), 2);
    assert.match(await devices.nth(1).innerText(), /AS-102/);
    const issue = dialog.getByRole('button', { name: 'Issue demo tracking code' });
    await issue.click();
    assert.equal(await page.evaluate(() => ticket().issued), false);
    await devices.nth(1).getByLabel('Condition photo').fill('second.jpg');
    await issue.click();
    assert.equal(await page.evaluate(() => ticket().issued), false);
    await devices.nth(1).getByRole('checkbox').check();
    await issue.click();
    await dialog.waitFor({ state: "hidden" });
    assert.deepEqual(await page.evaluate(() => ticket().devices.map(d => [d.serial, d.photo, d.ack])),
      [['AS-101', 'first.jpg', true], ['AS-102', 'second.jpg', true]]);
    assert.equal(await page.evaluate(() => ticket().issued), true);
    await page.close();
  }
  console.log('Passed contact validation, Back on both devices, draft save and per-device issuance at both widths.');
} finally {
  await browser.close();
}
