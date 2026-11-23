#!/usr/bin/env python3
"""Real Chromium viewport, keyboard and interaction acceptance on DISPOSABLE localhost MySQL.

Chrome runs against the actual PHP pages, not screenshots of a mock-up. Screenshots
are artifacts; do not use real customer data or expose this server publicly.
"""
import json
import os
from pathlib import Path
import shutil
import subprocess

from playwright.sync_api import sync_playwright, expect

BASE = os.environ.get('EDP_TEST_URL', 'http://127.0.0.1:8743')
if BASE != 'http://127.0.0.1:8743':
    raise SystemExit('Refusing nonlocal browser acceptance URL')
if os.environ.get('REPAIRTRACK_EDP_TEST_DB') != 'repairtrack_edp':
    raise SystemExit('Refusing non-disposable academic database')

# Reuse the actual end-to-end journey's fictional staff to avoid unnecessary
# sign-ins against the deliberately strict single-IP authentication limiter.
handoff = os.environ.get('EDP_BROWSER_JOURNEY_MANIFEST')
journey = None
if handoff:
    manifest = Path(handoff)
    if not manifest.is_file():
        raise RuntimeError('Required private fictional journey handoff is missing')
    journey = json.loads(manifest.read_text(encoding='utf-8'))
    credentials = journey['staff']
else:
    credentials = json.loads(subprocess.run(
        ['php', 'course-edp/tests/full-journey-fixture.php', 'create'],
        capture_output=True, text=True, check=True, env=os.environ.copy()).stdout)['staff']
output = Path(os.environ.get('EDP_BROWSER_OUTPUT', '/tmp/repairtrack-browser-evidence'))
output.mkdir(parents=True, exist_ok=True)


def check(condition, description):
    if not condition:
        raise AssertionError(description)
    print('PASS: ' + description)


def fits_viewport(page, name, width):
    metrics = page.evaluate('''() => ({
        documentWidth: document.documentElement.scrollWidth,
        bodyWidth: document.body.scrollWidth,
        viewportWidth: document.documentElement.clientWidth,
        requiredWithoutLabels: [...document.querySelectorAll('input[required],select[required],textarea[required]')]
          .filter(e => !e.labels || e.labels.length === 0).map(e => e.name)
    })''')
    check(max(metrics['documentWidth'], metrics['bodyWidth']) <= metrics['viewportWidth'] + 2,
          f'{name} at {width}px has no horizontal overflow: {metrics}')
    check(not metrics['requiredWithoutLabels'],
          f'{name} at {width}px labels required input controls')


def screenshot_viewports(page, name):
    for width, height in [(375, 812), (768, 1024), (1366, 900)]:
        page.set_viewport_size({'width': width, 'height': height})
        page.wait_for_timeout(80)
        fits_viewport(page, name, width)
        page.screenshot(path=str(output / f'{name}-{width}.png'), full_page=True,
                        animations='disabled')


with sync_playwright() as playwright:
    chrome = os.environ.get('EDP_CHROME_EXECUTABLE') or shutil.which('google-chrome') or shutil.which('chromium')
    if not chrome:
        raise RuntimeError('Chrome/Chromium missing; browser acceptance cannot be skipped silently')
    browser = playwright.chromium.launch(executable_path=chrome, headless=True,
                                         args=['--no-sandbox'])
    try:
        roles = [
            ('intake', 'index.php?page=login', 'Create a repair draft'),
            ('manager', 'manager.php?page=login', 'Manager queue'),
            ('technician', 'technician.php?page=login', 'My repair queue'),
            ('cashier', 'cashier.php?page=login', 'Cashier desk'),
        ]
        for role, url, after_heading in roles:
            context = browser.new_context(viewport={'width': 1366, 'height': 900},
                timezone_id='Asia/Manila', accept_downloads=True)
            page = context.new_page()
            response = page.goto(BASE + '/' + url, wait_until='networkidle')
            check(response is not None and response.status == 200 and page.locator('h1').count(),
                  f'{role} sign-in page renders in real Chromium')
            screenshot_viewports(page, role+'-login')
            page.set_viewport_size({'width': 1366, 'height': 900})
            user = credentials[role]
            page.get_by_label('Email', exact=True).fill(user['email'])
            page.get_by_label('Password', exact=True).fill(user['password'])
            page.locator('form button[type="submit"]').first.click()
            page.get_by_role('heading', name=after_heading).wait_for(timeout=12000)
            screenshot_viewports(page, role+'-home')
            # These screenshots use the SAME authenticated staff sessions as
            # the sign-in checks; no extra logins or weakened security limits.
            if journey and role == 'manager':
                response = page.goto(BASE+'/manager.php?page=device&id='+journey['device_ids'][0],
                                     wait_until='networkidle')
                check(response is not None and response.status == 200
                      and page.get_by_role('heading', name='Quotation review').count() == 1
                      and journey['ticket_code'] in page.locator('main').inner_text()
                      and 'APPROVED' in page.locator('main').inner_text(),
                      'manager review shows the actual approved fictional quote')
                screenshot_viewports(page, 'populated-manager-quote')
            if journey and role == 'technician':
                response = page.goto(BASE+'/technician.php?page=device&id='+journey['device_ids'][0],
                                     wait_until='networkidle')
                check(response is not None and response.status == 200
                      and journey['ticket_code'] in page.locator('main').inner_text()
                      and 'PRIVATE_JOURNEY_DIAGNOSIS_ABC' in page.locator('main').inner_text(),
                      'technician repair detail retains the real private fictional diagnosis')
                screenshot_viewports(page, 'populated-technician-detail')
                page.goto(BASE+'/technician.php?view=history', wait_until='networkidle')
                check(journey['ticket_code'] in page.locator('main').inner_text(),
                      'technician history includes completed fictional repair')
                screenshot_viewports(page, 'populated-technician-history')
            if journey and role == 'cashier':
                response = page.goto(BASE+'/cashier.php?page=ticket&id='+journey['ticket_id'],
                                     wait_until='networkidle')
                check(response is not None and response.status == 200
                      and journey['ticket_code'] in page.locator('main').inner_text()
                      and '600.00' in page.locator('main').inner_text(),
                      'cashier populated bill matches approved fictional total')
                check(page.locator('a[href*="page=receipt"]').count() == 2,
                      'cashier shows the two actual recorded cash receipts')
                screenshot_viewports(page, 'populated-cashier-ticket')
                page.locator('a[href*="page=receipt"]').first.click()
                check(page.get_by_role('heading', name='Cash receipt #', exact=False).count() == 1
                      and journey['ticket_code'] in page.locator('main').inner_text(),
                      'cashier receipt displays fictional ticket payment')
                screenshot_viewports(page, 'populated-cashier-receipt')
            if role == 'technician':
                page.set_viewport_size({'width': 1366, 'height': 900})
                page.get_by_role('link', name='EDP Lab Tools').click()
                page.get_by_role('heading', name='File events & API integration').wait_for()
                screenshot_viewports(page, 'edp-lab')
                # Demonstrate actual JS events without opening an OS file chooser in CI.
                page.evaluate('''() => {
                    const handle = {
                      getFile: async () => new File(['Opened from real file event'], 'exercise.txt', {type:'text/plain'}),
                      createWritable: async () => ({
                        write: async (data) => { window.__savedNotepadContent = data; },
                        close: async () => {}
                      })
                    };
                    window.showOpenFilePicker = async () => [handle];
                    window.showSaveFilePicker = async () => handle;
                }''')
                page.locator('#note-open').click()
                expect(page.locator('#note-editor')).to_have_value('Opened from real file event', timeout=7000)
                check(page.locator('#note-editor').input_value() == 'Opened from real file event',
                      'Notepad open click imports UTF-8 text via browser File API')
                page.locator('#note-editor').fill('Edited classroom note')
                page.locator('#note-save').click()
                expect(page.locator('#note-feedback')).to_contain_text('Saved exercise.txt', timeout=7000)
                check(page.evaluate('window.__savedNotepadContent') == 'Edited classroom note',
                      'Notepad save click invokes file writer with revised text')
                year = page.locator('#holiday-year').input_value()
                page.route('**/holiday-api.php?year=*', lambda route: route.fulfill(
                    status=200, content_type='application/json', body=json.dumps({'ok': True, 'data': {
                        'year': int(year), 'country': 'PH', 'holidays': [
                            {'date': f'{year}-12-31', 'name': 'Fictional Fixture Holiday'}]}})))
                page.locator('#holidays-load').click()
                page.get_by_text('Fictional Fixture Holiday').wait_for()
                check('Fictional Fixture Holiday' in page.locator('#holiday-results').inner_text(),
                      'API button fetches JSON and renders safely using DOM nodes')
                check('Fictional Fixture Holiday' not in page.locator('#note-editor').input_value(),
                      'API display does not overwrite Notepad text')
            context.close()

        # Customer tracker has no staff login and must fit mobile on its own.
        context = browser.new_context(viewport={'width': 375,'height':812})
        page = context.new_page()
        response = page.goto(BASE+'/track.php', wait_until='networkidle')
        check(response is not None and response.status==200 and
              page.get_by_role('heading',name='Track your repaired device').count()==1,
              'customer tracking login is accessible without a staff session')
        screenshot_viewports(page,'customer-login')
        unauthorized = context.request.get(BASE+'/holiday-api.php?year=2026', max_redirects=0)
        check(unauthorized.status==401 and unauthorized.json().get('ok') is False,
              'external API adapter denies unauthenticated requests')
        response = context.request.get(BASE+'/lab.php', max_redirects=0)
        check(response.status==303 and 'technician.php?page=login' in response.headers.get('location',''),
              'Notepad lab requires an authenticated technician')
        if journey:
            page.get_by_label('Tracking code').fill(journey['portal_code'])
            page.get_by_label('Portal PIN').fill(journey['portal_pin'])
            page.get_by_role('button', name='View repair progress').click()
            page.get_by_role('heading', name='Repair progress', exact=True).wait_for(timeout=12000)
            rendered = page.locator('main').inner_text()
            check(journey['ticket_code'] in rendered and rendered.count('Collected by customer') == 2,
                  'customer portal displays both actual completed fictional devices')
            check('PRIVATE_JOURNEY_DIAGNOSIS_ABC' not in rendered
                  and 'journey@example.test' not in rendered
                  and 'Cash receipt #' not in rendered,
                  'customer portal excludes fictional private diagnosis and cashier receipts')
            screenshot_viewports(page, 'populated-customer-timeline')
        else:
            print('NOTE: populated pages not checked without a private journey manifest')
        context.close()

        print('PASS: real Chromium HCI and EDP lab acceptance completed')
    finally:
        browser.close()
