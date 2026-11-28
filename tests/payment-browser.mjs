import assert from 'node:assert/strict';
import { mkdirSync, readFileSync } from 'node:fs';
import { chromium } from 'playwright-core';

const base = 'http://127.0.0.1:8742';
const fixture = JSON.parse(readFileSync(process.env.PICKUP_FIXTURE, 'utf8'));
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH,
  channel: process.env.CHROME_PATH ? undefined : 'chrome',
});

async function login(role, width) {
  const page = await browser.newPage({ viewport: { width, height: 900 } });
  page.setDefaultTimeout(12000);
  await page.route('**/*', route => {
    return route.request().url().startsWith(base + '/') ? route.continue() : route.abort();
  });
  await page.goto(base + '/admin/login.php');
  await page.locator('#email').fill(fixture[role]);
  await page.locator('#password').fill(fixture.password);
  await press(page, 'Login');
  await page.waitForURL(['admin','valuation_reviewer'].includes(role)
    ? '**/dashboard.php' : '**/staff_home.php');
  return page;
}

async function press(page, name) {
  await page.getByRole('button', { name, exact: true }).click();
}

async function checkWidth(page) {
  await page.waitForLoadState('load');
  const overflow = await page.evaluate(() => {
    if (document.documentElement.scrollWidth <= innerWidth) return null;
    return [...document.querySelectorAll('body *')]
      .filter(element => element.getBoundingClientRect().right > innerWidth)
      .map(element => element.tagName + '.' + element.className);
  });
  assert.equal(overflow, null, 'Page exceeds viewport: ' + page.url() + ' ' + (overflow || []).join(', '));
}

async function checkRole(role, destination) {
  const page = await login(role, 1440);
  if (role === 'admin') {
    // Admin now starts in the shared overview; navigate directly to the requested workspace.
    await page.getByRole('link', { name: destination, exact: true }).click();
    await page.locator('#work-list').waitFor();
  }
  const screenshots = process.env.RUNNER_TEMP + '/workspace-screens';
  mkdirSync(screenshots, { recursive: true });
  for (const width of [1440, 360, 320]) {
    const height = width === 320 ? 568 : 900;
    await page.setViewportSize({ width, height });
    await checkWidth(page);
    let nav;
    if (width <= 767) {
      const dock = page.locator('.rt-dock');
      const dockLayout = await dock.evaluate(element => {
        const rect = element.getBoundingClientRect();
        const links = [...element.querySelectorAll('a')].map(link => {
          const box = link.getBoundingClientRect();
          const label = link.querySelector('span');
          return {
            height: box.height,
            left: box.left,
            right: box.right,
            labelFits: !label || label.scrollWidth <= label.clientWidth + 1,
          };
        });
        return {
          position: getComputedStyle(element).position,
          bottomGap: innerHeight - rect.bottom,
          linkCount: links.length,
          links,
        };
      });
      assert.equal(dockLayout.position, 'fixed', 'Mobile shortcuts are not fixed');
      assert(Math.abs(dockLayout.bottomGap) <= 1, 'Mobile dock is not flush with the viewport bottom');
      assert.equal(dockLayout.linkCount, 4, 'Mobile dock must keep three shortcuts and More');
      assert(dockLayout.links.every(link =>
        link.height >= 44 && link.left >= -1 && link.right <= width + 1 && link.labelFits),
        'Mobile dock target is clipped, too small, or its label overflows');

      const clearance = await page.evaluate(() => {
        window.scrollTo(0, document.documentElement.scrollHeight);
        const main = document.querySelector('#rt-main');
        const dock = document.querySelector('.rt-dock');
        const lastContent = main?.lastElementChild;
        return {
          contentBottom: lastContent?.getBoundingClientRect().bottom ?? 0,
          dockTop: dock.getBoundingClientRect().top,
        };
      });
      assert(clearance.contentBottom <= clearance.dockTop + 1,
        'Last workspace content is obscured by the fixed mobile dock');

      await page.locator('#rt-more').click();
      assert.equal(await page.locator('#rt-menu').evaluate(menu => menu.open), true,
        'More did not open the workspace menu');
      assert.equal(await page.locator('#rt-search').evaluate(element => document.activeElement === element),
        true, 'More did not move focus into page search');
      const menuClearance = await page.evaluate(() => ({
        menuBottom: document.querySelector('.rt-menu-body').getBoundingClientRect().bottom,
        dockTop: document.querySelector('.rt-dock').getBoundingClientRect().top,
      }));
      assert(menuClearance.menuBottom <= menuClearance.dockTop + 1,
        'Workspace menu extends behind the fixed dock on a short screen');
      await page.screenshot({ path: screenshots + '/' + role + '-dock-menu-' + width + '.png', fullPage: false });
      await page.keyboard.press('Escape');
      assert.equal(await page.locator('#rt-menu').evaluate(menu => menu.open), false);
      assert.equal(await page.locator('#rt-more').evaluate(element => document.activeElement === element),
        true, 'Escape did not restore focus to the More trigger');
      await page.locator('#rt-more').click();
      nav = page.locator('#rt-menu nav');
    } else {
      nav = page.locator('.rt-side');
    }
    const cashLink = page.locator('#rt-menu nav a[href$="view=cash"]');
    assert.equal(await cashLink.count(), ['admin', 'manager', 'cashier'].includes(role) ? 1 : 0);
    await nav.getByRole('link', { name: destination, exact: true }).click();
    await page.locator('#work-list').waitFor();
    await checkWidth(page);
    await page.screenshot({ path: screenshots + '/' + role + '-' + width + '.png' });
  }
  await page.context().close();
}

try {
  const manager = await login('manager', 1440);
  // Exercise shelf quarantine and a verified count through the real manager UI.
  await manager.goto(base + '/admin/inventory.php?branch=' + fixture.branch);
  await manager.getByRole('heading', { name: 'Branch inventory' }).waitFor();
  await checkWidth(manager);
  await manager.setViewportSize({ width: 360, height: 900 });
  await checkWidth(manager);
  await manager.setViewportSize({ width: 1440, height: 900 });
  const battery = manager.locator('article.dashboard-card').filter({ hasText: 'Quarantine test battery' });
  await battery.locator('summary').filter({ hasText: 'Quarantine damaged stock' }).click();
  const quarantine = battery.locator('form').filter({ has: manager.locator('button[value=quarantine]') });
  await quarantine.locator('[name=quantity]').fill('1');
  await quarantine.locator('[name=reason]').fill('Damaged battery inspected and isolated in quarantine shelf.');
  await quarantine.locator('[name=confirm]').check();
  await press(manager,'Quarantine damaged items');
  await manager.getByRole('status').getByText('Damaged stock isolated', { exact: false }).waitFor();
  const afterQuarantine = manager.locator('article.dashboard-card').filter({ hasText: 'Quarantine test battery' });
  await afterQuarantine.getByText('2.000', { exact: false }).first().waitFor();
  await afterQuarantine.locator('summary').filter({ hasText: 'Record a verified shelf count' }).click();
  const recount = afterQuarantine.locator('form').filter({ has: manager.locator('button[value=count]') });
  await recount.locator('[name=quantity]').fill('3');
  await recount.locator('[name=reason]').fill('Recount found one additional reusable battery on the shelf.');
  await recount.locator('[name=confirm]').check();
  await press(manager,'Save verified count');
  await manager.getByRole('status').getByText('Verified shelf count recorded', { exact: false }).waitFor();
  // Quarantine recorder cannot approve their own restock. A distinct admin reviews it.
  assert.equal(await manager.locator('form').filter({
    has: manager.locator('input[name=quarantine_id]')
  }).count(),0,'Quarantine creator can approve their own decision.');
  const admin = await login('admin',1440);
  await admin.goto(base + '/admin/inventory.php?branch=' + fixture.branch);
  const disposition = admin.locator('form').filter({
    has: admin.locator('input[name=quarantine_id]')
  }).first();
  await disposition.locator('[name=decision]').selectOption('RELEASE');
  await disposition.locator('[name=reason]').fill(
    'Second-person inspection confirmed the previously isolated battery is reusable.');
  await disposition.locator('[name=confirm]').check();
  await press(admin,'Record disposition');
  await admin.getByRole('status').getByText('Independent quarantine disposition recorded').waitFor();
  // Five units of newly counted stock must wait for second-person approval.
  await manager.goto(base + '/admin/inventory.php?branch=' + fixture.branch);
  const nextBattery = manager.locator('article.dashboard-card').filter({ hasText: 'Quarantine test battery' });
  await nextBattery.locator('summary').filter({ hasText: 'Record a verified shelf count' }).click();
  const highCount = nextBattery.locator('form').filter({ has: manager.locator('button[value=count]') });
  await highCount.locator('[name=quantity]').fill('9');
  await highCount.locator('[name=reason]').fill('Repeated physical count verified five additional parts at this branch.');
  await highCount.locator('[name=confirm]').check();
  await press(manager,'Save verified count');
  await manager.getByRole('status').getByText('Large count difference queued', {exact:false}).waitFor();
  await admin.goto(base + '/admin/inventory.php?branch=' + fixture.branch);
  const countReview = admin.locator('form').filter({
    has: admin.locator('input[name=review_id]')
  }).first();
  await countReview.locator('[name=decision]').selectOption('approve');
  await countReview.locator('[name=reason]').fill(
    'Independent recount verified the additional usable batteries before approval.');
  await countReview.locator('[name=confirm]').check();
  await press(admin,'Record count decision');
  await admin.getByRole('status').getByText('Count review decision recorded').waitFor();
  await admin.getByText('9.000', {exact:false}).first().waitFor();
  // Cross-branch parts move out at dispatch and into the destination only on receipt.
  await manager.goto(base + '/admin/parts_transfers.php?branch=' + fixture.branch);
  await manager.getByRole('heading', { name: 'Physical parts transfers' }).waitFor();
  await checkWidth(manager);
  await manager.locator('summary').filter({ hasText: 'New parts manifest' }).click();
  const send = manager.locator('details').filter({ hasText: 'New parts manifest' }).locator('form');
  await send.locator('[name=destination]').selectOption(String(fixture.transfer_branch));
  await send.locator('[name=sku_lines]').fill(fixture.transfer_sku + ',2');
  await send.locator('[name=note]').fill('Sealed and counted physical battery shipment at sending branch.');
  await send.locator('[name=confirm]').check();
  await press(manager,'Record parts dispatch');
  await manager.getByRole('status').getByText('Parts dispatched under manifest', {exact:false}).waitFor();
  await admin.goto(base + '/admin/parts_transfers.php?branch=' + fixture.transfer_branch);
  await admin.getByRole('heading', { name: 'Physical parts transfers' }).waitFor();
  await admin.setViewportSize({width:360,height:900});
  await checkWidth(admin);
  const item = admin.locator('article.dashboard-card').filter({hasText:fixture.transfer_sku});
  await item.locator('summary').filter({hasText:'Confirm receipt or report shortage'}).click();
  const firstReceipt = item.locator('form').filter({has: admin.locator('button[value=receive]')});
  await firstReceipt.locator('[name=amount]').fill('1');
  await firstReceipt.locator('[name=note]').fill('One unit received; second expected unit missing from sealed box.');
  await firstReceipt.locator('[name=confirm]').check();
  await press(admin,'Record physical receipt');
  await admin.getByRole('status').getByText('Physical receipt recorded', {exact:false}).waitFor();
  await admin.getByText('Status EXCEPTION', {exact:false}).first().waitFor();
  const remaining = admin.locator('article.dashboard-card').filter({hasText:fixture.transfer_sku});
  await remaining.locator('summary').filter({hasText:'Confirm receipt or report shortage'}).click();
  const late = remaining.locator('form').filter({has: admin.locator('button[value=receive]')});
  await late.locator('[name=amount]').fill('1');
  await late.locator('[name=note]').fill('Missing sealed battery arrived and was physically counted.');
  await late.locator('[name=confirm]').check();
  await press(admin,'Record physical receipt');
  await admin.getByText('Status RECEIVED', {exact:false}).first().waitFor();
  // A second shipment is independently investigated; no missing part is received
  // or credited merely because the source branch accepts an operational loss.
  await manager.goto(base + '/admin/parts_transfers.php?branch=' + fixture.branch);
  await manager.locator('summary').filter({hasText:'New parts manifest'}).click();
  const lossDispatch = manager.locator('details').filter({hasText:'New parts manifest'}).locator('form');
  await lossDispatch.locator('[name=destination]').selectOption(String(fixture.transfer_branch));
  await lossDispatch.locator('[name=sku_lines]').fill(fixture.transfer_sku + ',1');
  await lossDispatch.locator('[name=note]').fill('Second independently counted battery shipment with signed seal reference.');
  await lossDispatch.locator('[name=confirm]').check();
  await press(manager,'Record parts dispatch');
  await manager.getByRole('status').getByText('Parts dispatched under manifest',{exact:false}).waitFor();
  await admin.goto(base + '/admin/parts_transfers.php?branch=' + fixture.transfer_branch);
  const missingItem = admin.locator('article.dashboard-card').filter({hasText:fixture.transfer_sku}).first();
  await missingItem.locator('summary').filter({hasText:'Confirm receipt or report shortage'}).click();
  const missingForm = missingItem.locator('form').filter({has:admin.locator('button[value=missing]')});
  await missingForm.locator('[name=note]').fill('Courier and receiving manager verified a missing sealed battery case.');
  await missingForm.locator('[name=confirm]').check();
  await missingForm.locator('button[value=missing]').click();
  await admin.getByRole('status').getByText('Missing-part discrepancy recorded',{exact:false}).waitFor();
  const investigationItem = admin.locator('article.dashboard-card').filter({hasText:fixture.transfer_sku}).first();
  await investigationItem.locator('summary').filter({hasText:'Open formal loss investigation'}).click();
  const investigation = investigationItem.locator('form').filter({has:admin.locator('button[value=report-loss]')});
  await investigation.locator('[name=evidence_reference]').fill('SEAL-'+fixture.transfer_sku+'-INCIDENT');
  await investigation.locator('[name=reason]').fill(
    'Checked signed dispatch sheet, shipment seal and receiver count; battery remains missing.');
  await investigation.locator('[name=confirm]').check();
  await investigation.locator('button[value=report-loss]').click();
  await admin.getByRole('status').getByText('Formal loss investigation recorded',{exact:false}).waitFor();
  const verifiedPng = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFklEQVR4nGP8//8/AwMDEwMDAwMDAwAkBgMB/DXemwAAAABJRU5ErkJggg==',
    'base64'
  );
  const evidenceItem = admin.locator('article.dashboard-card').filter({hasText:fixture.transfer_sku}).first();
  await evidenceItem.locator('summary').filter({hasText:'Attach private loss evidence'}).click();
  const evidenceForm = evidenceItem.locator('form').filter({
    has: admin.locator('button[value=attach-evidence]')
  });
  // Browser-provided MIME/extension cannot bypass server-side file validation.
  await evidenceForm.locator('[name=evidence]').setInputFiles({
    name:'false.png',mimeType:'image/png',buffer:Buffer.from('<svg>not an image</svg>')
  });
  await evidenceForm.locator('button[value=attach-evidence]').click();
  await admin.getByRole('alert').getByText('Only verified PNG/JPEG', {exact:false}).waitFor();
  const retryItem = admin.locator('article.dashboard-card').filter({hasText:fixture.transfer_sku}).first();
  await retryItem.locator('summary').filter({hasText:'Attach private loss evidence'}).click();
  const retryForm = retryItem.locator('form').filter({
    has: admin.locator('button[value=attach-evidence]')
  });
  await retryForm.locator('[name=evidence]').setInputFiles({
    name:'signed-count.png',mimeType:'image/png',buffer:verifiedPng
  });
  await retryForm.locator('button[value=attach-evidence]').click();
  await admin.getByRole('status').getByText('Private inspection evidence attached', {exact:false}).waitFor();
  const lossEvidenceUrl = await admin.getByRole('link', {name:'signed-count.png'}).first().getAttribute('href');
  assert(lossEvidenceUrl?.startsWith('stock_evidence.php?id='));
  const authorizedDownload = await admin.context().request.get(base+'/admin/'+lossEvidenceUrl);
  assert.equal(authorizedDownload.status(),200);
  assert.match(authorizedDownload.headers()['content-disposition']||'',/attachment;/);
  assert.equal(authorizedDownload.headers()['cache-control']?.includes('no-store'),true);
  const reviewer = await login('loss_reviewer',1440);
  const reviewerDownload = await reviewer.context().request.get(base+'/admin/'+lossEvidenceUrl);
  assert.equal(reviewerDownload.status(),200,'Origin reviewer cannot inspect destination evidence.');
  await reviewer.goto(base + '/admin/parts_transfers.php?branch=' + fixture.branch);
  const reviewItem = reviewer.locator('article.dashboard-card').filter({hasText:fixture.transfer_sku}).first();
  await reviewItem.locator('summary').filter({hasText:'Independently review missing-stock case'}).click();
  const decision = reviewItem.locator('form').filter({has:reviewer.locator('button[value=decide-loss]')});
  await decision.locator('[name=decision]').selectOption('approve');
  await decision.locator('[name=reason]').fill(
    'Independently verified origin manifest, destination incident report and sealed shipment discrepancy.');
  await decision.locator('[name=confirm]').check();
  await decision.locator('button[value=decide-loss]').click();
  await reviewer.getByRole('status').getByText('Independent loss decision recorded',{exact:false}).waitFor();
  await reviewer.getByText('Status LOSS_CLOSED',{exact:false}).first().waitFor();
  // Cost evidence must first be independently approved at the source branch.
  await manager.goto(base + '/admin/acquisition_costs.php?branch=' + fixture.branch);
  await manager.getByRole('heading',{name:'Verified supplier acquisition costs'}).waitFor();
  await manager.locator('summary').filter({hasText:'Propose documented cost'}).click();
  const sourceCost = manager.locator('form').filter({
    has:manager.locator('button[value=propose-cost]')
  });
  await sourceCost.locator('[name=sku]').fill(fixture.transfer_sku);
  await sourceCost.locator('[name=unit_cost]').fill('245.75');
  await sourceCost.locator('[name=source_type]').selectOption('SUPPLIER_INVOICE');
  await sourceCost.locator('[name=supplier_name]').fill('Test Regional Parts Supplier');
  await sourceCost.locator('[name=document_reference]').fill('BROWSER-SUPPLIER-INV-'+fixture.transfer_sku);
  await sourceCost.locator('[name=document_date]').fill('2026-05-18');
  await sourceCost.locator('[name=reason]').fill(
    'Reviewed original battery supplier invoice and matched purchase price to exact SKU.');
  await sourceCost.locator('[name=confirm]').check();
  await sourceCost.locator('button[value=propose-cost]').click();
  await manager.getByRole('status').getByText('Supplier cost submitted',{exact:false}).waitFor();
  const financeAdmin = await login('valuation_reviewer',1440);
  await financeAdmin.goto(base + '/admin/acquisition_costs.php?branch=' + fixture.branch);
  await financeAdmin.setViewportSize({width:360,height:900});
  await checkWidth(financeAdmin);
  const reviewCost = financeAdmin.locator('article.dashboard-card').filter({
    hasText:fixture.transfer_sku
  }).first();
  await reviewCost.locator('summary').filter({
    hasText:'Independently approve or reject supplier cost'
  }).click();
  const costDecision=reviewCost.locator('form').filter({
    has:financeAdmin.locator('button[value=decide-cost]')
  });
  await costDecision.locator('[name=decision]').selectOption('approve');
  await costDecision.locator('[name=reason]').fill(
    'Independently verified supplier identity, exact battery SKU, invoice and original acquisition cost.');
  await costDecision.locator('[name=confirm]').check();
  await costDecision.locator('button[value=decide-cost]').click();
  await financeAdmin.getByRole('status').getByText('Independent supplier-cost decision recorded',{exact:false}).waitFor();
  await admin.goto(base + '/admin/parts_transfers.php?branch=' + fixture.transfer_branch);
  const pricedItem = admin.locator('article.dashboard-card').filter({hasText:fixture.transfer_sku}).first();
  await pricedItem.locator('summary').filter({
    hasText:'Value loss using independently verified supplier cost'
  }).click();
  const priceForm = pricedItem.locator('form').filter({
    has:admin.locator('input[name=cost_record_id]')
  });
  await priceForm.locator('[name=reason]').fill(
    'Matched the independently approved source-branch purchase cost with the missing battery.');
  await priceForm.locator('[name=confirm]').check();
  await priceForm.locator('button[value=propose-valuation]').click();
  await admin.getByRole('status').getByText('PHP financial exposure submitted',{exact:false}).waitFor();
  await financeAdmin.goto(base + '/admin/parts_transfers.php?branch=' + fixture.branch);
  const financialItem = financeAdmin.locator('article.dashboard-card').filter({
    hasText:fixture.transfer_sku
  }).first();
  await financialItem.locator('summary').filter({hasText:'Independently approve PHP loss valuation'}).click();
  const financeForm = financialItem.locator('form').filter({
    has:financeAdmin.locator('button[value=decide-valuation]')
  });
  await financeForm.locator('[name=decision]').selectOption('approve');
  await financeForm.locator('[name=reason]').fill(
    'Independently confirmed supplier acquisition cost, invoice ID, currency and outstanding physical quantity.');
  await financeForm.locator('[name=confirm]').check();
  await financeForm.locator('button[value=decide-valuation]').click();
  await financeAdmin.getByRole('status').getByText('Independent PHP valuation decision recorded',{exact:false}).waitFor();
  await financeAdmin.getByText('Current approved exposure',{exact:false}).first().waitFor();
  // Recover the previously approved lost battery. It stays out of usable
  // inventory until a separate source-branch manager approves inspection.
  await admin.goto(base + '/admin/parts_transfers.php?branch=' + fixture.transfer_branch);
  await checkWidth(admin);
  const recoveryItem = admin.locator('article.dashboard-card').filter({
    hasText: fixture.transfer_sku
  }).first();
  await recoveryItem.locator('summary').filter({
    hasText:'Report parts recovered after approved loss'
  }).click();
  const recoveryForm = recoveryItem.locator('form').filter({
    has: admin.locator('button[value=report-recovery]')
  });
  await recoveryForm.locator('[name=quantity]').fill('1');
  await recoveryForm.locator('[name=evidence_reference]').fill('SIGNED-RECOVERY-'+fixture.transfer_sku);
  await recoveryForm.locator('[name=reason]').fill(
    'The missing sealed battery was found intact and held in the inspected receiving bin.');
  await recoveryForm.locator('[name=confirm]').check();
  await recoveryForm.locator('button[value=report-recovery]').click();
  await admin.getByRole('status').getByText('Recovered parts held pending',{exact:false}).waitFor();
  const recoveryEvidenceItem = admin.locator('article.dashboard-card').filter({hasText:fixture.transfer_sku}).first();
  await recoveryEvidenceItem.locator('summary').filter({hasText:'Attach private recovery evidence'}).click();
  const recoveryEvidenceForm = recoveryEvidenceItem.locator('form').filter({
    has: admin.locator('button[value=attach-evidence]')
  });
  await recoveryEvidenceForm.locator('[name=evidence]').setInputFiles({
    name:'recovered-condition.png',mimeType:'image/png',buffer:verifiedPng
  });
  await recoveryEvidenceForm.locator('button[value=attach-evidence]').click();
  await admin.getByRole('status').getByText('Private inspection evidence attached',{exact:false}).waitFor();
  const recoveryEvidenceUrl = await admin.getByRole('link',{name:'recovered-condition.png'})
    .first().getAttribute('href');
  assert(recoveryEvidenceUrl?.startsWith('stock_evidence.php?id='));
  await reviewer.goto(base + '/admin/parts_transfers.php?branch=' + fixture.branch);
  const reviewerRecoveryDownload = await reviewer.context().request.get(base+'/admin/'+recoveryEvidenceUrl);
  assert.equal(reviewerRecoveryDownload.status(),200);
  const reviewRecoveryItem = reviewer.locator('article.dashboard-card').filter({
    hasText: fixture.transfer_sku
  }).first();
  await reviewRecoveryItem.locator('summary').filter({
    hasText:'Independently review recovered parts'
  }).click();
  const approveRecovery = reviewRecoveryItem.locator('form').filter({
    has: reviewer.locator('button[value=decide-recovery]')
  });
  await approveRecovery.locator('[name=decision]').selectOption('approve');
  await approveRecovery.locator('[name=reason]').fill(
    'Inspected original loss reference and signed count, confirmed recovered battery usable.');
  await approveRecovery.locator('[name=confirm]').check();
  await approveRecovery.locator('button[value=decide-recovery]').click();
  await reviewer.getByRole('status').getByText('Independent recovery decision saved',{exact:false}).waitFor();
  await reviewer.getByText('Status RECEIVED',{exact:false}).first().waitFor();
  // The original monetary approval must be marked stale, not silently reduced.
  await admin.goto(base + '/admin/parts_transfers.php?branch=' + fixture.transfer_branch);
  await admin.getByText('Reassessment needed:',{exact:false}).first().waitFor();
  const revisedItem=admin.locator('article.dashboard-card').filter({hasText:fixture.transfer_sku}).first();
  await revisedItem.locator('summary').filter({
    hasText:'Value loss using independently verified supplier cost'
  }).click();
  const revisedForm=revisedItem.locator('form').filter({
    has:admin.locator('input[name=cost_record_id]')
  });
  await revisedForm.locator('[name=reason]').fill(
    'Full physical recovery makes the remaining financial exposure zero while retaining original purchase evidence.');
  await revisedForm.locator('[name=confirm]').check();
  await revisedForm.locator('button[value=propose-valuation]').click();
  await admin.getByRole('status').getByText('PHP financial exposure submitted',{exact:false}).waitFor();
  await financeAdmin.goto(base + '/admin/parts_transfers.php?branch=' + fixture.branch);
  const zeroItem=financeAdmin.locator('article.dashboard-card').filter({
    hasText:fixture.transfer_sku
  }).first();
  await zeroItem.locator('summary').filter({hasText:'Independently approve PHP loss valuation'}).click();
  const zeroForm=zeroItem.locator('form').filter({
    has:financeAdmin.locator('button[value=decide-valuation]')
  });
  await zeroForm.locator('[name=decision]').selectOption('approve');
  await zeroForm.locator('[name=reason]').fill(
    'Confirmed the complete recovered quantity, receiving movements and original supplier cost evidence.');
  await zeroForm.locator('[name=confirm]').check();
  await zeroForm.locator('button[value=decide-valuation]').click();
  await financeAdmin.getByRole('status').getByText('Independent PHP valuation decision recorded',{exact:false}).waitFor();
  await financeAdmin.getByText('Latest approved exposure:',{exact:false}).first().waitFor();
  await financeAdmin.getByText('PHP 0.00',{exact:false}).first().waitFor();
  // Full monthly shelf stocktaking is audit-only, not a stock count adjustment.
  // A manager physically counts all source-branch SKUs and a different ADMIN
  // must sign a zero-variance reconciliation.
  await manager.goto(base+'/admin/stocktaking.php?branch='+fixture.branch);
  await manager.setViewportSize({width:360,height:900});
  await manager.getByRole('heading',{name:'Monthly branch stocktaking'}).waitFor();
  await checkWidth(manager);
  await manager.locator('textarea[name=reason]').first().fill(
    'Monthly warehouse review of every usable shelf and inactive SKU.');
  await manager.locator('input[name=confirm]').first().check();
  await manager.locator('button[value=open]').click();
  await manager.getByRole('status').getByText('Monthly stocktake opened',{exact:false}).waitFor();
  await manager.setViewportSize({width:1440,height:900});
  let physicallyCounted=0;
  for (let index=0; index<20; index++) {
    const uncounted=manager.locator('article.dashboard-card').filter({
      has:manager.locator('summary').filter({hasText:'Record physical SKU count'})
    }).first();
    if (await uncounted.count()===0) break;
    await uncounted.locator('summary').filter({hasText:'Record physical SKU count'}).click();
    const countForm=uncounted.locator('form').filter({
      has:manager.locator('button[value=count]')
    });
    const recordedShelf=await countForm.locator('[name=expected_on_hand]').inputValue();
    await countForm.locator('[name=counted_quantity]').fill(recordedShelf);
    await countForm.locator('[name=reason]').fill(
      'Physically verified usable units against the current shelf and reservation log.');
    await countForm.locator('[name=confirm]').check();
    await countForm.locator('button[value=count]').click();
    await manager.getByRole('status').getByText('Physical shelf count recorded',{exact:false}).waitFor();
    physicallyCounted++;
  }
  assert(physicallyCounted>=2,'Monthly stocktake did not count source-branch SKUs.');
  await manager.locator('summary').filter({
    hasText:'Submit all physical counts for independent review'
  }).click();
  const submitStocktake=manager.locator('form').filter({
    has:manager.locator('button[value=submit]')
  });
  await submitStocktake.locator('[name=reason]').fill(
    'Every physical shelf counted and reconciled against the current warehouse ledger.');
  await submitStocktake.locator('[name=confirm]').check();
  await submitStocktake.locator('button[value=submit]').click();
  await manager.getByRole('status').getByText('Stocktake submitted',{exact:false}).waitFor();
  await financeAdmin.goto(base+'/admin/stocktaking.php?branch='+fixture.branch);
  const signoffSection=financeAdmin.locator('section.dashboard-card').filter({
    has:financeAdmin.locator('summary').filter({
      hasText:'Independent sign-off or return for recount'
    })
  }).first();
  await signoffSection.locator('summary').filter({
    hasText:'Independent sign-off or return for recount'
  }).click();
  const finalSignoff=signoffSection.locator('form').filter({
    has:financeAdmin.locator('button[value=decide]')
  });
  await finalSignoff.locator('[name=decision]').selectOption('signoff');
  await finalSignoff.locator('[name=reason]').fill(
    'Independently inspected counts, current reservations and lack of any outstanding variances.');
  await finalSignoff.locator('[name=confirm]').check();
  await finalSignoff.locator('button[value=decide]').click();
  await financeAdmin.getByRole('status').getByText('Independent stocktake decision recorded',{exact:false}).waitFor();
  await financeAdmin.getByText('SIGNED_OFF',{exact:false}).first().waitFor();
  await financeAdmin.context().close();
  await reviewer.context().close();
  await admin.context().close();
  await manager.goto(base + '/admin/staff_home.php');
  const sidebar = await manager.locator('.rt-side').boundingBox();
  const main = await manager.locator('#rt-main').boundingBox();
  assert(main.x >= sidebar.x + sidebar.width, 'Sidebar covers the queue');
  await manager.locator('.rt-side').getByRole('link', { name: 'Status board' }).click();
  await manager.setViewportSize({ width: 360, height: 900 });
  const preview = manager.locator('.board-preview').first();
  const summary = preview.locator('summary');
  await summary.focus();
  await summary.press('Space');
  await preview.getByText(fixture.ticket, { exact: true }).waitFor();
  await checkWidth(manager);
  await summary.press('Enter');
  assert.equal(await preview.locator('dl').isVisible(), false);
  await summary.click();
  await preview.locator('dl').waitFor();
  await manager.setViewportSize({ width: 1440, height: 900 });
  await manager.locator('.rt-side').getByRole('link', { name: 'Repair queue' }).click();
  await manager.getByRole('link', { name: 'Open device' }).click();
  await manager.getByRole('link', {name:'Open estimate'}).click();
  await manager.locator('summary').filter({hasText:'Prepare a revised estimate'}).click();
  await manager.locator('[name=labor_description]').fill('Diagnosis');
  await manager.locator('[name=labor_amount]').fill('350');
  await manager.locator('[name=part_id]').selectOption(String(fixture.part));
  await manager.locator('[name=parts_amount]').fill('2400');
  await press(manager,'Review draft');
  await press(manager,'Confirm and save');
  await manager.locator('[name=confirm]').check();
  await press(manager,'Review sharing');
  await press(manager,'Confirm and save');
  await manager.locator('[name=decision]').selectOption('APPROVED');
  await manager.locator('[name=customer_name]').fill('Pickup Test');
  await manager.locator('[name=reference]').fill('Customer agreed at counter');
  await manager.locator('[name=confirm]').check();
  await press(manager,'Review response');
  await press(manager,'Confirm and save');
  await press(manager,'Reserve quoted quantity');
  await manager.getByText('Reserved for this device', {exact:false}).waitFor();
  const deviceUrl = base + '/admin/device.php?id=' + fixture.device;
  const tech = await login('technician',360);
  async function stage(status) {
    await tech.goto(deviceUrl);
    const repairFieldSizes = await tech.locator(
      '#repair-status, #repair-note, #public-message'
    ).evaluateAll(elements => elements
      .filter(element => element.getClientRects().length > 0)
      .map(element => ({
        name: element.id,
        height: element.getBoundingClientRect().height,
      })));
    assert(repairFieldSizes.length === 3 &&
      repairFieldSizes.every(item => item.height >= 44),
      'Technician repair form control below 44px: ' + JSON.stringify(repairFieldSizes));
    await tech.locator('select[name=status]').selectOption(status);
    await press(tech,'Review progress');
    await press(tech,'Confirm change');
  }
  await stage('IN_PROGRESS');
  await tech.getByRole('link',{name:'Open estimate'}).click();
  await press(tech,'Record installation');
  await tech.getByText('available · Installed', {exact:false}).waitFor();
  // Manager verifies physical restock; technician must install the replacement before testing.
  const quoteUrl = base + '/admin/quote.php?id=' + fixture.device;
  await manager.goto(quoteUrl);
  const reusableReturn = manager.locator('form').filter({ has: manager.locator('[name=stock_action][value=return]') });
  await reusableReturn.locator('[name=return_reason]').fill('Removed and inspected reusable SSD before final testing.');
  await reusableReturn.locator('[name=restock_confirm]').check();
  await press(manager,'Record part return to stock');
  await manager.getByText('Awaiting reservation', {exact:false}).waitFor();
  await press(manager,'Reserve quoted quantity');
  await manager.getByText('Reserved for this device', {exact:false}).waitFor();
  await tech.goto(quoteUrl);
  await press(tech,'Record installation');
  await tech.getByText('available · Installed', {exact:false}).waitFor();
  await stage('TESTING');
  await stage('READY_FOR_PICKUP');
  await tech.context().close();
  await manager.goto(deviceUrl);
  await manager.getByRole('link', { name: 'Open billing and handover' }).click();
  await manager.locator('[name=confirm]').check();
  const reviewScreens = process.env.RUNNER_TEMP + '/workspace-screens';
  mkdirSync(reviewScreens, { recursive: true });
  await press(manager, 'Review bill issuance');
  await manager.screenshot({
    path: reviewScreens + '/manager-bill-issuance-review-1440.png',
    fullPage: false,
  });
  await press(manager, 'Issue final bill');
  await manager.getByRole('heading', { name: 'Record counter cash' }).waitFor();

  const cashier = await login('cashier', 360);
  const forbidden = await cashier.goto(base + '/admin/inventory.php');
  assert.equal(forbidden.status(), 403, 'Cashier gained access to branch inventory.');
  const partsForbidden = await cashier.goto(base + '/admin/parts_transfers.php');
  assert.equal(partsForbidden.status(),403,'Cashier gained access to physical parts transfers.');
  const evidenceForbidden = await cashier.goto(base+'/admin/'+lossEvidenceUrl);
  assert.equal(evidenceForbidden.status(),403,'Cashier downloaded confidential inventory evidence.');
  await cashier.goto(base + '/admin/staff_home.php?view=cash');
  await checkWidth(cashier);
  await cashier.locator('#rt-more').click();
  await cashier.locator('#rt-search').fill('cash');
  await cashier.locator('#rt-menu nav').getByRole('link', { name: 'Cash desk' }).click();
  await cashier.locator('#queue-search').fill(fixture.ticket);
  await press(cashier, 'Search');
  await cashier.getByRole('heading', { name: 'Cash desk bill lookup' }).waitFor();
  const cashierList = cashier.locator('#work-list .workspace-table');
  assert.equal(await cashierList.count(), 1, 'Cashier search should show its bill table');
  const screenshotDirectory = process.env.RUNNER_TEMP + '/workspace-screens';
  mkdirSync(screenshotDirectory, { recursive: true });
  for (const width of [360, 320]) {
    await cashier.setViewportSize({ width, height: 900 });
    await checkWidth(cashier);
    const cashLayout = await cashierList.evaluate(table => {
      const rows = [...table.tBodies[0].rows];
      const headers = [...table.tHead.rows[0].cells].map(cell => cell.textContent.trim());
      const labelsMatch = rows.length > 0 && rows.every(row => {
        const labels = [...row.cells].map(cell => cell.dataset.label || '');
        return headers.length === labels.length &&
          headers.every((header, index) => header === labels[index]);
      });
      return {
        rowDisplay: getComputedStyle(rows[0]).display,
        minWidth: getComputedStyle(table).minWidth,
        role: table.getAttribute('role'),
        headerRoles: [...table.tHead.rows[0].cells].every(cell => cell.getAttribute('role') === 'columnheader'),
        rowRoles: rows.every(row => row.getAttribute('role') === 'row'),
        cellRoles: rows.every(row => [...row.cells].every(cell => cell.getAttribute('role') === 'cell')),
        labelsMatch,
        wrapperFits: table.parentElement.scrollWidth <= table.parentElement.clientWidth + 1,
      };
    });
    assert.equal(cashLayout.role, 'table');
    assert(cashLayout.headerRoles && cashLayout.rowRoles && cashLayout.cellRoles && cashLayout.labelsMatch,
      'Cash desk mobile cards lost table roles or matching field labels');
    assert(cashLayout.wrapperFits, 'Cashier bill cards require horizontal scrolling');
    assert.equal(cashLayout.rowDisplay, 'grid', 'Cashier bill row did not become a stacked card');
    assert.equal(cashLayout.minWidth, '0px', 'Cashier bill table kept a desktop minimum width');
    if (width === 360) {
      const cashRow = cashierList.locator('tbody tr').first();
      await cashRow.evaluate(row => {
        window.scrollBy(0, row.getBoundingClientRect().top - 56);
      });
      await cashier.screenshot({
        path: screenshotDirectory + '/cashier-cash-list-360-top.png',
        fullPage: false,
      });
      const bottomClearance = await cashRow.evaluate(row => {
        const dock = document.querySelector('.rt-dock');
        window.scrollBy(0, row.getBoundingClientRect().bottom -
          (dock.getBoundingClientRect().top - 12));
        return {
          rowBottom: row.getBoundingClientRect().bottom,
          dockTop: dock.getBoundingClientRect().top,
        };
      });
      assert(bottomClearance.rowBottom <= bottomClearance.dockTop - 8,
        'Cashier bill card cannot be scrolled fully above the fixed dock');
      await cashier.screenshot({
        path: screenshotDirectory + '/cashier-cash-list-360-bottom.png',
        fullPage: false,
      });
    }
  }
  await cashier.setViewportSize({ width: 360, height: 900 });
  await cashier.getByRole('link', { name: 'Open cash record' }).click();
  await cashier.getByRole('heading', { name: 'Record counter cash' }).waitFor();
  await cashier.waitForLoadState('load');
  await checkWidth(cashier);
  assert.equal(await cashier.locator('#cash-amount').inputValue(), '2750.00');
  assert(
    (await cashier.locator('#cash-amount').evaluate(element =>
      element.getBoundingClientRect().height)) >= 44,
    'Cash amount field is below the 44px staff form baseline.'
  );
  await cashier.locator('[name=confirm]').check();
  await press(cashier, 'Review payment');
  await cashier.screenshot({
    path: screenshotDirectory + '/cashier-payment-review-360.png',
    fullPage: false,
  });
  await press(cashier, 'Confirm cash received');
  await cashier.locator('#recipient').fill('Pickup Test');
  await cashier.locator('[name=condition_confirmed]').check();
  await press(cashier, 'Review handover');
  await cashier.screenshot({
    path: screenshotDirectory + '/cashier-handover-review-360.png',
    fullPage: false,
  });
  await press(cashier, 'Confirm handover and close ticket');
  await cashier.getByRole('heading', { name: 'Ticket closed' }).waitFor();
  await checkWidth(cashier);
  console.log('PASS: manager billing at 1440px, cashier search, payment and pickup at 360px.');
  for (const [role, destination] of Object.entries({
    admin: 'Repair queue', manager: 'Repair queue', intake_agent: 'Saved drafts',
    technician: 'Status board', courier: 'History', cashier: 'Cash desk',
    viewer: 'Status board',
  })) {
    await checkRole(role, destination);
    console.log('PASS: desktop and mobile navigation for ' + role);
  }
} finally {
  await browser.close();
}
