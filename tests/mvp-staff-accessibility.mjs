import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { chromium } from 'playwright-core';

const base = 'http://127.0.0.1:8742';
const password = readFileSync(process.env.ACTIVITY_FIXTURE, 'utf8').trim();
const browser = await chromium.launch({ channel: 'chrome' });

async function signedIn(js) {
  const context = await browser.newContext({
    javaScriptEnabled: js,
    viewport: { width: 390, height: 850 },
    reducedMotion: 'reduce',
  });
  await context.route('**/*', route =>
    route.request().url().startsWith(base + '/') ? route.continue() : route.abort());
  const page = await context.newPage();
  page.setDefaultTimeout(12000);
  await page.goto(base + '/admin/login.php');
  await page.getByLabel('Email', { exact: true }).fill('cashier@sim.example.test');
  await page.locator('#password').fill(password);
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/staff_home.php');
  return { context, page };
}

try {
  const active = await signedIn(true);
  const page = active.page;
  assert.equal(await page.getByRole('navigation', { name: 'Mobile shortcuts' }).count(), 1);
  assert.equal(await page.getByRole('navigation', { name: 'All workspace pages without JavaScript' }).count(), 0);
  const skip = page.getByRole('link', { name: 'Skip to content' });
  await page.keyboard.press('Tab');
  assert.equal(await skip.evaluate(el => document.activeElement === el), true,
    'Staff skip link must be first in the keyboard sequence.');
  await page.keyboard.press('Enter');
  assert.equal(await page.evaluate(() => document.activeElement.id), 'rt-main',
    'Skip link must focus the staff main landmark.');

  const more = page.getByRole('navigation', { name: 'Mobile shortcuts' })
    .getByRole('link', { name: /More/ });
  await more.click();
  assert.equal(await page.locator('#rt-menu').evaluate(el => el.open), true,
    'Mobile More failed to open the page menu.');
  assert.equal(await page.locator('#rt-search').evaluate(el => document.activeElement === el), true,
    'More should focus the destination search.');
  await page.locator('#rt-search').fill('cash');
  const navigation = page.getByRole('navigation', { name: 'All destinations' });
  assert(await navigation.getByRole('link', { name: 'Cash desk' }).isVisible());
  assert.equal(await navigation.getByRole('link', { name: 'Learning center' }).isVisible(), false);
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('#rt-menu').evaluate(el => el.open), false,
    'Escape did not dismiss menu.');
  assert.equal(await page.locator('#rt-menu summary').evaluate(el => document.activeElement === el), true,
    'Escape must restore focus to menu summary.');
  await active.context.close();

  const nojs = await signedIn(false);
  await nojs.page.goto(base + '/admin/staff_home.php');
  const fallback = nojs.page.getByRole('navigation', {
    name: 'All workspace pages without JavaScript',
  });
  assert(await fallback.isVisible(), 'No-JS phone is missing complete navigation.');
  assert(await fallback.getByRole('link', { name: 'Cash desk' }).isVisible());
  assert(await fallback.getByRole('link', { name: 'Learning center' }).isVisible());
  const entries = await fallback.locator('a').evaluateAll(links =>
    links.map(link => ({
      h: link.getBoundingClientRect().height,
      label: link.textContent.trim(),
    })));
  assert(entries.length >= 4 && entries.every(entry => entry.h >= 44),
    'No-JS destinations must be real 44px touch targets.');
  for (const width of [320, 390, 700]) {
    await nojs.page.setViewportSize({ width, height: 850 });
    const overflow = await nojs.page.evaluate(() =>
      document.documentElement.scrollWidth - document.documentElement.clientWidth);
    assert(overflow <= 1, 'No-JS workspace overflow at ' + width + ': ' + overflow);
    assert(await fallback.isVisible());
  }
  await nojs.page.setViewportSize({ width: 390, height: 850 });
  await fallback.getByRole('link', { name: 'Cash desk' }).click();
  assert.equal(new URL(nojs.page.url()).searchParams.get('view'), 'cash');
  await nojs.context.close();

  console.log('PASS: staff skip-link focus, mobile More/search/Escape focus restore, and no-JS 320/390/700px authorized navigation.');
} finally {
  await browser.close();
}
