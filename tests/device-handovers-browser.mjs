import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {chromium} from 'playwright-core';

const base = 'http://127.0.0.1:8742';
const fixture = JSON.parse(readFileSync(process.env.HANDOVER_BROWSER_FIXTURE));
const browser = await chromium.launch({channel:'chrome'});
async function login(email, width) {
  const page = await browser.newPage({viewport:{width,height:900}});
  await page.route('**/*', r => r.request().url().startsWith(base + '/') ? r.continue() : r.abort());
  await page.goto(base + '/admin/login.php');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(fixture.password);
  await page.getByRole('button',{name:'Login',exact:true}).click();
  await page.waitForURL('**/staff_home.php');
  await page.goto(base + '/admin/transfers.php');
  return page;
}
try {
  const sender = await login('manager@sim.example.test',1440);
  await sender.getByText('Dispatch devices',{exact:true}).click();
  await sender.locator('[name=origin]').selectOption(String(fixture.origin));
  await sender.locator('[name=destination]').selectOption(String(fixture.destination));
  await sender.locator('[name=codes]').fill('SIM-RT-003-01\nSIM-RT-004-01');
  await sender.getByRole('button',{name:'Confirm dispatch'}).click();
  await sender.getByRole('status').waitFor();
  const manifest = await sender.locator('[name=q]').inputValue();
  assert.match(manifest,/^TR-[A-F0-9]{12}$/);
  assert.equal(await sender.getByRole('button',{name:'Confirm device received'}).count(),0);
  const receiver = await login('receiver@example.test',360);
  await receiver.locator('[name=q]').fill(manifest);
  await receiver.getByRole('button',{name:'Apply filters'}).click();
  const card = n => receiver.locator('section').filter({has:receiver.getByRole('heading',{name:'SIM-RT-00'+n+'-01'})});
  await card(3).getByRole('button',{name:'Confirm device received'}).click();
  await card(4).locator('[name=note]').fill('Not in this parcel');
  await card(4).getByRole('button',{name:'Report device missing'}).click();
  await card(4).getByText('Not in this parcel').waitFor();
  await card(4).locator('[name=note]').fill('Found in second parcel');
  await card(4).getByRole('button',{name:'Confirm found and received'}).click();
  await receiver.getByText('No matching transfer items.').waitFor();
  await receiver.locator('[name=view]').selectOption('history');
  await receiver.getByRole('button',{name:'Apply filters'}).click();
  await card(4).getByText('Found in second parcel').waitFor();
  assert(await card(3).isVisible());
  assert(await receiver.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
} finally {
  await browser.close();
}
