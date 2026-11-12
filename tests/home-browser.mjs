import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright-core';
const url = process.env.REPAIRTRACK_TEST_URL;
const site = process.env.REPAIRTRACK_TEST_SITE;
assert(url && ['localhost', '127.0.0.1'].includes(new URL(url).hostname));
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH || process.cwd() + '/qa/chromium',
  args: ['--no-sandbox'],
  env: { ...process.env, LD_LIBRARY_PATH: process.cwd() + '/qa' },
});
async function pageFor(options = {}) {
  const context = await browser.newContext(options);
  await context.route('**/*', (r) =>
    r.request().url().startsWith(url) ? r.continue() : r.abort()
  );
  return context.newPage();
}
try {
  const page = await pageFor({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
  await page.goto(url + '/index.php');
  assert(
    await page.getByRole('heading', { name: 'A few details make check-in easier.' }).isVisible()
  );
  await page.getByText('Can I track a repair online yet?', { exact: true }).click();
  assert(
    await page.getByText('Public code/QR tracking is not available', { exact: false }).isVisible()
  );
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  assert(await page.locator('#site-nav').isVisible());
  await page.screenshot({ path: site + '/home-mobile.png', fullPage: true });
  const motion = await pageFor({
    reducedMotion: 'reduce',
    viewport: { width: 1440, height: 1000 },
  });
  let videos = 0;
  motion.on('request', (r) => {
    if (r.url().endsWith('.mp4')) videos++;
  });
  await motion.goto(url + '/index.php');
  assert.equal(videos, 0);
  await motion.screenshot({ path: site + '/home-desktop.png', fullPage: true });
  console.log('PASS: homepage FAQ, no-JS mobile navigation and reduced-motion video.');
} finally {
  await browser.close();
}
