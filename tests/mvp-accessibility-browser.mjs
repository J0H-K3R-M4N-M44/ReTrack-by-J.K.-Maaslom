import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';

// Focused interaction smoke test; not a replacement for screen-reader UAT.
const base = process.env.REPAIRTRACK_TEST_URL;
assert(base && ['127.0.0.1', 'localhost'].includes(new URL(base).hostname));
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH,
  channel: process.env.CHROME_PATH ? undefined : 'chrome',
});
try {
  const context = await browser.newContext({
    viewport: { width: 320, height: 850 },
    reducedMotion: 'reduce',
  });
  await context.route('**/*', route =>
    route.request().url().startsWith(base + '/') ? route.continue() : route.abort()
  );
  const page = await context.newPage();
  page.setDefaultTimeout(12000);
  await page.goto(base + '/track.php');
  assert.equal(await page.getByRole('main').count(), 1, 'Missing main landmark');
  const skip = page.getByRole('link', { name: 'Skip to content' });
  await page.keyboard.press('Tab');
  assert.equal(await skip.evaluate(el => document.activeElement === el), true,
    'Skip link is not keyboard-first');
  assert(await skip.isVisible(), 'Focused skip link is invisible');
  await page.keyboard.press('Enter');
  assert.equal(await page.evaluate(() => document.activeElement.id), 'main',
    'Skip link did not focus main content');

  for (const id of ['tracking-code', 'portal-pin']) {
    const field = page.locator('#' + id);
    const checks = await field.evaluate(element => ({
      labels: element.labels?.length ?? 0,
      help: (element.getAttribute('aria-describedby') ?? '').split(' ')
        .filter(Boolean).every(name => document.getElementById(name) !== null),
      height: element.getBoundingClientRect().height,
    }));
    assert(checks.labels >= 1 && checks.help,
      'Missing accessible name/help for ' + id);
    assert(checks.height >= 44, id + ' touch target is under 44px');
  }
  assert.equal(await page.locator('#tracking-form button[type=submit]').count(), 1,
    'Tracking form lacks explicit submit');
  for (const width of [320, 390, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await page.reload();
    const overflow = await page.evaluate(() =>
      document.documentElement.scrollWidth - document.documentElement.clientWidth);
    assert(overflow <= 1, 'Public tracking overflow at ' + width + ': ' + overflow);
    if (width <= 600) {
      const mobileNav = await page.locator('.public-header nav').evaluate(nav => ({
        flexWrap: getComputedStyle(nav).flexWrap,
        linksStayOnOneLine: [...nav.querySelectorAll('a')].every(link =>
          getComputedStyle(link).whiteSpace === 'nowrap'),
        navFitsViewport: nav.getBoundingClientRect().right <= innerWidth,
        allLinksFitWithoutScrolling: nav.scrollWidth <= nav.clientWidth + 1,
      }));
      assert.equal(mobileNav.flexWrap, 'nowrap', 'Customer navigation should stay on one compact row');
      assert(mobileNav.linksStayOnOneLine && mobileNav.navFitsViewport &&
        mobileNav.allLinksFitWithoutScrolling,
        'Customer navigation links should remain fully visible without horizontal clipping');
    }
    const targets = await page.locator(
      '.public-header a, .public-card details summary, .public-button'
    ).evaluateAll(elements => elements.map(element => ({
      label: element.textContent?.trim().slice(0, 40),
      height: element.getBoundingClientRect().height,
    })));
    assert(targets.every(target => target.height >= 44),
      'Customer tap target under 44px at ' + width + ': ' + JSON.stringify(targets));
  }
  const animations = await page.locator('.public-card').first()
    .evaluate(element => getComputedStyle(element).animationName);
  assert.equal(animations, 'none', 'Reduced-motion mode unexpectedly animates tracking');

  // Test browser-native required-field behavior, without spending PIN attempts.
  await page.getByRole('button', { name: 'View repair' }).click();
  assert(await page.locator('#tracking-code').evaluate(element => element.validity.valueMissing),
    'Empty tracking code was submitted');
  const noJs = await browser.newContext({
    javaScriptEnabled: false,
    viewport: { width: 390, height: 844 },
    reducedMotion: 'reduce',
  });
  const simple = await noJs.newPage();
  await simple.goto(base + '/track.php');
  assert(await simple.getByRole('button', { name: 'View repair' }).isVisible());
  assert.equal(await simple.locator('label[for=tracking-code]').count(), 1);
  await noJs.close();
  await context.close();
  console.log('PASS: customer keyboard skip, input names/help, 44px targets, reduced motion, no-JS and 320–1440px.');
} finally {
  await browser.close();
}
