import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright-core';

// Use only a disposable local site and database; these checks create/edit drafts.
const url = process.env.REPAIRTRACK_TEST_URL;
const site = process.env.REPAIRTRACK_TEST_SITE;
assert(url && ['localhost', '127.0.0.1'].includes(new URL(url).hostname));
assert(site);
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH || process.cwd() + '/qa/chromium',
  args: ['--no-sandbox'],
  env: { ...process.env, LD_LIBRARY_PATH: process.cwd() + '/qa' },
});
const errors = [];
async function setup(options = {}) {
  const context = await browser.newContext(options);
  await context.route('**/*', (route) =>
    route.request().url().startsWith(url) ? route.continue() : route.abort()
  );
  const page = await context.newPage();
  page.on('pageerror', (error) => errors.push(error.message));
  return page;
}
async function login(page) {
  await page.goto(url + '/admin/login.php');
  await page.locator('[name=email]').fill('admin@example.test');
  await page.locator('[name=password]').fill('DemoRepair2026!');
  const start = Date.now();
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/admin/dashboard.php');
  assert(Date.now() - start < 4500, 'No artificial five-second login delay');
}
async function fillCustomer(page, name) {
  await page.locator('[name=customer_name]').fill(name);
  await page.locator('[name=contact_value]').fill('0917-876-5432');
}
async function fillDevice(page) {
  await page.locator('[name=device_type]').selectOption('Laptop');
  await page.locator('[name=make]').fill('Lenovo');
  await page.locator('[name=model]').fill('Demo model');
  await page.locator('[name=reported_symptoms]').fill('No power');
  await page.locator('[name=condition_notes]').fill('No visible damage');
}
async function formData(page) {
  return page
    .locator('form')
    .filter({ has: page.locator('[name=confirm_review]') })
    .evaluate((form) => Object.fromEntries(new FormData(form)));
}
try {
  const page = await setup();
  await login(page);
  await page.getByRole('link', { name: 'Repair workbench' }).click();
  assert(new URL(page.url()).searchParams.get('tab') === 'workbench');
  assert(!(await page.locator('.analytics-grid').isVisible()), 'Overview hidden on workbench');
  await page.reload();
  assert(!(await page.locator('.analytics-grid').isVisible()));
  await page.getByRole('link', { name: 'Customer roster' }).click();
  await page.getByRole('link', { name: 'Next', exact: true }).click();
  assert((await page.locator('.list-pager').last().textContent()).includes('13–24'));
  await page.locator('[name=q]').fill('%notfound_');
  await page.getByRole('button', { name: 'Search', exact: true }).click();
  assert((await page.locator('.list-pager').last().textContent()).includes('0–0'));
  await page.goto(url + '/admin/intake_create.php');
  await fillCustomer(page, 'Browser Review Customer');
  await page.locator('[name=contact_value]').press('Enter');
  assert(await page.getByRole('heading', { name: 'What device needs service?' }).isVisible());
  await fillDevice(page);
  await page.getByRole('button', { name: 'Review details' }).click();
  assert(await page.getByRole('heading', { name: 'Review with the customer' }).isVisible());
  const body = await formData(page);
  const tampered = await page.request.post(url + '/admin/intake_create.php', {
    form: { ...body, intent: 'save', confirm_review: '1', condition_notes: 'Tampered' },
  });
  assert((await tampered.text()).includes('Review the current details'));
  await page.locator('[name=confirm_review]').check();
  await page.getByRole('button', { name: 'Save intake draft' }).click();
  await page.waitForURL('**/dashboard.php?tab=workbench');
  const replay = await page.request.post(url + '/admin/intake_create.php', {
    form: { ...body, intent: 'save', confirm_review: '1' },
  });
  assert.equal(replay.status(), 409);
  const draft = page
    .locator('.status-list > div')
    .filter({ hasText: 'Browser Review Customer' })
    .getByRole('link');
  const draftLink = await draft.getAttribute('href');
  await draft.click();
  await page.getByRole('button', { name: 'Continue to device' }).click();
  await page.locator('[name=condition_notes]').fill('Updated condition');
  await page.getByRole('button', { name: 'Review details' }).click();
  await page.locator('[name=confirm_review]').check();
  await page.getByRole('button', { name: 'Save intake draft' }).click();
  await page.waitForURL('**/dashboard.php?tab=workbench');
  await page.goto(url + '/admin/' + draftLink);
  assert.equal(await page.locator('[name=condition_notes]').inputValue(), 'Updated condition');
  await page.setViewportSize({ width: 390, height: 844 });
  assert(
    await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
    'Mobile intake fits'
  );
  await page.screenshot({ path: site + '/intake-mobile.png', fullPage: true });
  assert.equal((await page.request.get(url + '/admin/logout.php')).status(), 405);
  assert.equal(
    (await page.request.post(url + '/admin/logout.php', { form: { csrf_token: 'bad' } })).status(),
    403
  );
  await page.goto(url + '/admin/dashboard.php');
  await page.getByRole('button', { name: 'Log out', exact: true }).click();
  await page.waitForURL('**/admin/login.php');
  await page.goto(url + '/admin/dashboard.php');
  assert(page.url().endsWith('/admin/login.php'));

  const noJS = await setup({ javaScriptEnabled: false });
  await login(noJS);
  await noJS.getByRole('link', { name: 'Repair workbench' }).click();
  assert(!(await noJS.locator('.analytics-grid').isVisible()));
  await noJS.goto(url + '/admin/intake_create.php');
  await fillCustomer(noJS, 'No JavaScript Customer');
  await fillDevice(noJS);
  await noJS.locator('[name=contact_value]').fill('not a phone');
  await noJS.getByRole('button', { name: 'Review details' }).click();
  assert((await noJS.locator('.intake-errors').textContent()).includes('7 to 15 digits'));
  await noJS.locator('[name=contact_value]').fill('0917-876-5432');
  await noJS.getByRole('button', { name: 'Review details' }).click();
  await noJS.locator('[name=confirm_review]').check();
  await noJS.getByRole('button', { name: 'Save intake draft' }).click();
  await noJS.waitForURL('**/dashboard.php?tab=workbench');
  await noJS.goto(url + '/index.php');
  assert(await noJS.locator('.reveal').first().isVisible());

  const configPath = site + '/config/config.php';
  const config = fs.readFileSync(configPath, 'utf8');
  // A real query error must not still display "data loaded".
  const dataPath = site + '/app/dashboard_data.php';
  const data = fs.readFileSync(dataPath, 'utf8');
  try {
    fs.writeFileSync(
      dataPath,
      data.replace(
        '    $metrics = [];',
        "    throw new RuntimeException('Injected query failure');\n    $metrics = [];"
      )
    );
    await noJS.goto(url + '/admin/dashboard.php');
    assert((await noJS.locator('.system-status').textContent()).includes('unavailable'));
  } finally {
    fs.writeFileSync(dataPath, data);
  }
  await page.goto(url + '/admin/forgot_password.php');
  await page.locator('[name=email]').fill('admin@example.test');
  await page.locator('button[type=submit]').click();
  assert((await page.locator('[role=alert]').textContent()).includes('could not process'));
  // Capture recovery mail locally; never contact an external mail provider.
  try {
    fs.writeFileSync(
      configPath,
      config.replace(
        "'password'=>''",
        "'password'=>'','app_url'=>'" + url + "','mail_from'=>'repairtrack@example.test'"
      )
    );
    await page.goto(url + '/admin/forgot_password.php');
    await page.locator('[name=email]').fill('admin@example.test');
    await page.locator('button[type=submit]').click();
    assert((await page.locator('[role=status]').textContent()).includes('has been requested'));
    // The earlier backend token falls inside the one-minute request window.
    // Use the newly-created CLI administrator for an independent delivery test.
    await page.goto(url + '/admin/forgot_password.php');
    await page.locator('[name=email]').fill('cli-admin@example.test');
    await page.locator('button[type=submit]').click();
    const mail = fs.readFileSync(process.env.REPAIRTRACK_MAIL_LOG, 'utf8');
    assert(mail.includes('/admin/reset_password.php?token='), 'Mail handed to local sink');
    const resetUrl = mail.match(/http[^\s]+reset_password\.php\?token=[a-f0-9]{64}/)[0];
    await page.goto(resetUrl);
    await page.locator('[name=password]').fill('Replacement2026!');
    await page.locator('[name=confirm_password]').fill('Replacement2026!');
    await page.getByRole('button', { name: 'Update password' }).click();
    assert((await page.locator('[role=status]').textContent()).includes('has been updated'));
  } finally {
    fs.writeFileSync(configPath, config);
  }
  const csrfFailure = await page.request.post(url + '/admin/login.php', {
    form: { csrf_token: 'bad', email: 'admin@example.test', password: 'DemoRepair2026!' },
  });
  assert.equal(csrfFailure.status(), 403);
  // Age the disposable PHP session file to exercise real idle expiration.
  const cookie = (await noJS.context().cookies()).find(
    (cookie) => cookie.name === 'repairtrack_admin'
  );
  const sessionPath = process.env.REPAIRTRACK_SESSION_DIR + '/sess_' + cookie.value;
  const session = fs.readFileSync(sessionPath, 'utf8');
  fs.writeFileSync(sessionPath, session.replace(/last_activity\|i:\d+;/, 'last_activity|i:1;'));
  await noJS.goto(url + '/admin/dashboard.php');
  assert(noJS.url().endsWith('/admin/login.php'), 'Idle session expires');
  assert.deepEqual(errors, []);
  console.log(
    'PASS: real login, tabs, paging, review, tamper/replay rejection, draft edits, mobile, logout, no-JS, query/config failures, recovery mail handoff/reset, CSRF and idle expiry.'
  );
} finally {
  await browser.close();
}
