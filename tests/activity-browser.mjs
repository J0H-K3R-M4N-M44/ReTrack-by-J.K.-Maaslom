import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { chromium } from 'playwright-core';

const base = 'http://127.0.0.1:8742';
const password = readFileSync(process.env.ACTIVITY_FIXTURE, 'utf8').trim();
const browser = await chromium.launch({ channel: 'chrome' });

async function checkInbox(width, paymentsBefore) {
  const page = await browser.newPage({ viewport: { width, height: 900 } });
  page.setDefaultTimeout(12000);
  await page.route('**/*', route =>
    route.request().url().startsWith(base + '/') ? route.continue() : route.abort()
  );
  await page.goto(base + '/admin/login.php');
  await page.getByLabel('Email', { exact: true }).fill('cashier@sim.example.test');
  await page.locator('#password').fill(password);
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/staff_home.php');
  const badge = page.locator('header .rt-unread');
  assert.equal((await badge.innerText()).trim(), (paymentsBefore + 19) + ' unread');
  await badge.click();
  assert.equal(new URL(page.url()).searchParams.get('group'), 'payments');
  const payments = page.locator('option[value="payments"]');
  assert.equal(await payments.textContent(), 'Payments · ' + paymentsBefore + ' unread');
  const first = page.locator('.activity-list li').first();
  const reference = await first.locator('strong').innerText();
  await first.getByRole('button', { name: 'Mark read' }).click();
  await page.waitForURL('**/*view=inbox*');
  assert.equal((await badge.innerText()).trim(), (paymentsBefore + 18) + ' unread');
  assert.equal(await payments.textContent(), 'Payments · ' + (paymentsBefore - 1) + ' unread');
  assert.equal(await page.locator('option[value="pickups"]').textContent(), 'Pickups · 19 unread');
  assert.equal(await page.getByText(reference, { exact: true }).count(), 0);
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  await page.getByRole('combobox', { name: 'Activity view', exact: true }).selectOption('history');
  await page.getByRole('button', { name: 'Apply filters' }).click();
  const row = page.locator('.activity-list li').filter({ hasText: reference });
  assert.equal(await row.getByText('Read', { exact: true }).count(), 1);
  await page.close();
  console.log('Passed cashier inbox, header count and read history at ' + width + 'px');
}

try {
  await checkInbox(1440, 20);
  await checkInbox(360, 19);
} finally {
  await browser.close();
}
