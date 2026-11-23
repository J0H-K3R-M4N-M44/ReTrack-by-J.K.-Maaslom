#!/usr/bin/env python3
"""One REAL browser-like intake -> manager -> technician -> customer -> cashier journey.

Runs only against the isolated localhost PHP preview and disposable repairtrack_edp.
The CLI helper provisions fictional staff and inspects read-only final state; NO test
helper inserts repair tickets, quote approvals, status events, bills or payments.
"""
import base64
import html
import http.cookiejar
import json
import os
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import uuid

BASE = os.environ.get('EDP_TEST_URL')
if BASE != 'http://127.0.0.1:8743' or os.environ.get('REPAIRTRACK_EDP_TEST_DB') != 'repairtrack_edp':
    raise SystemExit('Refusing non-disposable or nonlocal cross-role HTTP target')

FIXTURE = ['php', 'course-edp/tests/full-journey-fixture.php']

def helper(*arguments):
    return json.loads(subprocess.run(
        FIXTURE + list(arguments), check=True, capture_output=True, text=True,
        env=os.environ.copy(), timeout=15).stdout)

def browser():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def fetch(client, path, data=None, headers=None):
    request = urllib.request.Request(BASE + path, data=data, headers=headers or {},
                                     method='POST' if data is not None else 'GET')
    try:
        response = client.open(request, timeout=20)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.geturl(), response.read().decode('utf-8')

def form(client, path, values):
    return fetch(client, path, urllib.parse.urlencode(values).encode(),
                 {'Content-Type': 'application/x-www-form-urlencoded'})

def multipart(client, path, fields, filename, content):
    boundary = 'journey-' + uuid.uuid4().hex
    parts = []
    for key, value in fields.items():
        parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n'
                      f'{value}\r\n').encode())
    parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="signature"; '
                  f'filename="{filename}"\r\nContent-Type: image/png\r\n\r\n').encode()
                 + content + b'\r\n')
    parts.append((f'--{boundary}--\r\n').encode())
    return fetch(client, path, b''.join(parts),
                 {'Content-Type': f'multipart/form-data; boundary={boundary}'})

def csrf(page):
    found = re.search(r'name="csrf_token" value="([a-f0-9]{64})"', page)
    if not found:
        raise AssertionError('CSRF token was not rendered')
    return found.group(1)

def ok(condition, label):
    if not condition:
        raise AssertionError(label)
    print('PASS: '+label)

def account_login(name, path, accounts, heading):
    client = browser()
    status, _, page = fetch(client, path)
    ok(status == 200 and heading in page, name+' requires sign-in')
    status, _, page = form(client, path + '?page=login', {
        'action':'login','csrf_token':csrf(page),
        'email':accounts[name]['email'],'password':accounts[name]['password']
    })
    ok(status == 200 and heading not in page, name+' successfully signs in')
    return client

accounts = helper('create')
branch = str(accounts['branch_id'])
staff = accounts['staff']

# 1. An intake agent creates a two-device ticket through real POST handlers.
intake = account_login('intake','/',staff,'Academic workspace')
status, _, intake_form = fetch(intake, '/?page=intake')
key = re.search(r'name="draft_request_key" value="([a-f0-9]{64})"',intake_form)
ok(status == 200 and key is not None, 'new intake supplies an idempotent draft key')
fields = [
    ('action','create_draft'),('csrf_token',csrf(intake_form)),
    ('draft_request_key',key.group(1)),('branch_id',branch),
    ('device_type_code[]','LAPTOP'),('defect_code[]','NO_DISPLAY'),
    ('reported_symptoms[]','Blank screen after power-on'),('make[]','Example'),('model[]','Journey Laptop'),
    ('device_type_code[]','DESKTOP'),('defect_code[]','OVERHEATING'),
    ('reported_symptoms[]','Fans spin but machine overheats'),('make[]','Example'),('model[]','Journey Tower'),
]
status, draft_url, draft_page = form(intake, '/?page=intake', fields)
id_match = re.search(r'page=draft(?:&|&amp;)id=(\d+)', draft_url + draft_page)
ok(status == 200 and id_match is not None and 'Issue repair ticket' in draft_page,
   'intake creates two real draft devices')
draft_id = id_match.group(1)
status, retry_url, _ = form(intake,'/?page=intake',fields)
ok(status == 200 and f'id={draft_id}' in retry_url, 'duplicate draft POST reuses the same ticket')

signature = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL4WQAAAABJRU5ErkJggg==')
status, receipt_url, receipt = multipart(intake,f'/?page=draft&id={draft_id}', {
    'action':'issue','csrf_token':csrf(draft_page),'draft_id':draft_id,
    'customer_mode':'new','full_name':'Journey Fictional Customer',
    'phone':'09123456789','email':'journey@example.test','signed_terms':'yes',
},'journey.png',signature)
code = re.search(r'RT-[0-9A-F]{12}',receipt)
portal = re.search(r'<dd>([0-9A-F]{5})</dd>',receipt)
ok(status == 200 and 'Issue successful' in receipt and code is not None and portal is not None,
   'real intake issuance creates internal code and private tracking credentials')
code, portal_code = code.group(), portal.group(1)
state = helper('inspect',code)
ok(state['ticket']['ticket_state']=='OPEN' and len(state['devices'])==2,
   'read-only inspector confirms two issued devices and an open ticket')
device_ids = [str(d['ticket_device_id']) for d in state['devices']]
ok(all(d['status_code']=='RECEIVED' and int(d['event_count'])==1 for d in state['devices']),
   'both devices have initial received events, not fixture-inserted statuses')

# 2. A manager assigns both devices to the real technician; outsider cannot access them.
manager = account_login('manager','/manager.php',staff,'Manager sign in')
outsider = account_login('outsider','/manager.php',staff,'Manager sign in')
for id_ in device_ids:
    status, _, wrong = fetch(outsider, '/manager.php?page=device&id='+id_)
    ok(status==404 and code not in wrong,'other-branch manager cannot read journey repair '+id_)
    status, _, detail = fetch(manager,'/manager.php?page=device&id='+id_)
    ok(status==200 and code in detail and 'Unassigned' in detail,
       'authorized manager can review the unassigned repair '+id_)
    status, _, changed = form(manager,'/manager.php?page=device&id='+id_,{
        'action':'assign','csrf_token':csrf(detail),'device_id':id_,
        'technician_id':str(staff['technician']['id'])})
    ok(status==200 and 'Technician assignment saved' in changed,
       'manager assigns real device '+id_)

# 3. The technician carries out diagnosis and drafts two quotations.
tech = account_login('technician','/technician.php',staff,'Technician sign in')
status, _, queue = fetch(tech,'/technician.php?page=queue')
ok(status==200 and code in queue,'technician sees assigned journey ticket in queue')
for id_ in device_ids:
    route = '/technician.php?page=device&id='+id_
    status, _, page = fetch(tech,route)
    ok(status==200 and 'Repair progress' in page,'assigned technician can open device '+id_)
    status, _, updated = form(tech,route,{
        'action':'status','csrf_token':csrf(page),'device_id':id_,
        'next_status':'DIAGNOSING','public_summary':'Device assessment has begun.'})
    ok(status==200 and 'Diagnosing' in updated,'technician enters diagnosing '+id_)
    status, _, diagnosed = form(tech,route,{
        'action':'diagnosis','csrf_token':csrf(updated),'device_id':id_,
        'diagnosis':'PRIVATE_JOURNEY_DIAGNOSIS_ABC: hardware inspection completed.'})
    ok(status==200 and 'Private diagnosis saved' in diagnosed,
       'technician records internal diagnosis '+id_)
    status, _, drafted = form(tech,route,{
        'action':'quote','csrf_token':csrf(diagnosed),'device_id':id_,
        'labor':'250.00','parts':'50.00','other':'0.00'})
    ok(status==200 and 'Quotation draft saved' in drafted and '300.00' in drafted,
       'technician prepares unpublished 300-peso quotation '+id_)
    status, _, early = form(tech,route,{
        'action':'status','csrf_token':csrf(drafted),'device_id':id_,'next_status':'IN_PROGRESS'})
    ok(status==422, 'technician cannot start work without customer approval '+id_)

# 4. Manager publication is visible to customer, but diagnosis and drafts remain private.
for id_ in device_ids:
    route = '/manager.php?page=device&id='+id_
    status, _, page = fetch(manager,route)
    status, _, published = form(manager,route,{
        'action':'send_quote','csrf_token':csrf(page),'device_id':id_})
    ok(status==200 and 'Current quotation sent' in published,
       'manager publishes exact current technician quotation '+id_)

customer = browser()
status, _, public_login = fetch(customer,'/track.php')
ok(status==200 and 'Track your repaired device' in public_login,'customer cannot browse private repair before login')
status, _, public_page = form(customer,'/track.php',{
    'action':'login','csrf_token':csrf(public_login),
    'portal_code':portal_code,'portal_pin':'6789'})
ok(status==200 and public_page.count('Awaiting customer decision')==2,
   'customer sees two pending published quotations')
ok('PRIVATE_JOURNEY_DIAGNOSIS_ABC' not in public_page and 'journey@example.test' not in public_page,
   'public customer view does not expose technician diagnosis or private contact data')

for id_ in device_ids:
    route='/manager.php?page=device&id='+id_
    status, _, page=fetch(manager,route)
    status, _, approved=form(manager,route,{
        'action':'decision','csrf_token':csrf(page),'device_id':id_,
        'decision':'APPROVED','channel':'IN_PERSON',
        'reference':'FICTIONAL-JOURNEY-RECEIPT-'+id_,'confirmed':'yes'})
    ok(status==200 and 'Offline customer decision recorded' in approved,
       'manager records explicitly staff-attested offline decision '+id_)

# Cashier cannot bill while the two devices have not completed testing.
cash = account_login('cashier','/cashier.php',staff,'Cashier sign in')
status, _, early_cash = fetch(cash,'/cashier.php?ticket_code='+code)
ok(status==200 and code in early_cash,'cashier finds ticket before repair completion')
ticket_id = str(state['ticket']['ticket_id'])
status, _, early_bill = form(cash,f'/cashier.php?page=ticket&id={ticket_id}',{
    'action':'issue_bill','csrf_token':csrf(early_cash),'ticket_id':ticket_id})
ok(status==422 and 'Every device must be ready' in early_bill,
   'cashier cannot issue a bill before all repairs are ready')

# 5. Real technician status transitions, first-start DateTime, and readiness.
for idx,id_ in enumerate(device_ids):
    route='/technician.php?page=device&id='+id_
    for stage in ['IN_PROGRESS','TESTING','READY_FOR_PICKUP']:
        status, _, page = fetch(tech,route)
        status, _, result = form(tech,route,{
            'action':'status','csrf_token':csrf(page),'device_id':id_,
            'next_status':stage,'public_summary':'Journey: '+stage.replace('_',' ').lower()})
        ok(status==200 and 'Repair progress' in result,
           'technician advances device '+id_+' to '+stage)
    if idx==0:
        halfway = helper('inspect',code)
        first_started = halfway['devices'][0]['repair_started_at']
        ok(isinstance(first_started,str) and first_started,
           'first repair-start timestamp is present for the first device')

# 6. Cashier generates itemized 600-peso bill and collects two payments.
status, cashier_url, cash_page = fetch(cash,'/cashier.php?ticket_code='+code)
ok(status==200 and 'Repair devices' in cash_page and code in cash_page,
   'cashier finds completed work by internal Ticket Code')
ticket_id = str(state['ticket']['ticket_id'])
status, _, bill_page = form(cash,f'/cashier.php?page=ticket&id={ticket_id}',{
    'action':'issue_bill','csrf_token':csrf(cash_page),'ticket_id':ticket_id})
ok(status==200 and '600.00' in bill_page and 'Itemized bill issued' in bill_page,
   'cashier issues one 600-peso itemized bill after all approved repairs')
bill_id_match = re.search(r'name="bill_id" value="(\d+)"',bill_page)
ok(bill_id_match is not None,'payment form references the current issued bill')
bill_id = bill_id_match.group(1)
for amount in ['120.00','480.00']:
    key = uuid.uuid4().hex + uuid.uuid4().hex
    payment={
        'action':'payment','csrf_token':csrf(bill_page),'ticket_id':ticket_id,
        'bill_id':bill_id,'request_key':key,'amount':amount,
    }
    status, url, receipt = form(cash,f'/cashier.php?page=ticket&id={ticket_id}',payment)
    ok(status==200 and 'Cash receipt #' in receipt and amount in receipt,
       'cashier records real counter-cash payment '+amount)
    if amount=='120.00':
        status, _, unpaid_pickup = form(cash,f'/cashier.php?page=ticket&id={ticket_id}',{
            'action':'pickup','csrf_token':csrf(receipt),'ticket_id':ticket_id,
            'confirmed_phone':'09123456789','attested':'yes'})
        ok(status==422 and 'unpaid balance' in unpaid_pickup,
           'counter pickup cannot occur with a partial payment')
        status, second_url, second_receipt = form(cash,f'/cashier.php?page=ticket&id={ticket_id}',payment)
        ok(status==200 and second_url==url,
           'duplicate cash submission reuses original receipt')
    status, _, bill_page=fetch(cash,f'/cashier.php?page=ticket&id={ticket_id}')

status, _, picked_up = form(cash,f'/cashier.php?page=ticket&id={ticket_id}',{
    'action':'pickup','csrf_token':csrf(bill_page),'ticket_id':ticket_id,
    'confirmed_phone':'09123456789','attested':'yes'})
ok(status==200 and 'Counter pickup confirmed' in picked_up,
   'fully paid and ready repair is handed over through cashier form')

# 7. Customer and read-only DB checks confirm matching terminal state and no internal leakage.
status, _, closed_view = fetch(customer,'/track.php')
ok(status==200 and closed_view.count('Collected by customer')==2
   and 'PRIVATE_JOURNEY_DIAGNOSIS_ABC' not in closed_view
   and 'Cash receipt #' not in closed_view,
   'customer timeline shows both handovers without private records or payment details')
end = helper('inspect',code)
ok(end['ticket']['ticket_state']=='CLOSED' and end['ticket']['closed_at'] is not None,
   'database ticket is closed with a closure timestamp')
ok(len(end['devices'])==2 and all(d['status_code']=='COMPLETED'
       and d['custody_state']=='WITH_CUSTOMER'
       and d['return_handover_at'] is not None
       and d['repair_started_at'] is not None
       and int(d['event_count'])==7 for d in end['devices']),
   'every device has an intact seven-event history, first-start time and pickup custody')
ok(end['bill'] is not None and end['bill']['bill_status']=='ISSUED'
   and end['bill']['total_amount']=='600.00' and len(end['bill_items'])==2
   and sorted(i['amount'] for i in end['bill_items'])==['300.00','300.00'],
   'bill itemization matches two actual approved quotations')
ok(len(end['payments'])==2 and sum(round(float(p['amount'])*100)
   for p in end['payments'])==60000,
   'only two payments were recorded and their amounts settle the bill')
ok(all(d['quote_status']=='APPROVED' and d['quote_sent_at']
       for d in end['devices']),
   'completed repairs retain their customer-visible approved quotations')
print('PASS: full cross-role journey, using ONLY real HTTP repair actions, is consistent.')
