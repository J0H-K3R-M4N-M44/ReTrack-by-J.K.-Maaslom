#!/usr/bin/env python3
"""Browser-like HTTP acceptance without third-party Python packages.

Run ONLY against an isolated PHP server backed by disposable repairtrack_edp.
Fixture credentials are generated for this test and are never logged.
"""
import base64
import http.cookiejar
import json
import os
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import uuid

BASE = os.environ.get('EDP_TEST_URL', 'http://127.0.0.1:8743')
if BASE != 'http://127.0.0.1:8743':
    raise SystemExit('Refusing nonlocal academic test URL')

fixture = json.loads(subprocess.run(
    ['php', 'course-edp/tests/web-fixture.php'], check=True, capture_output=True,
    text=True, env=os.environ.copy()).stdout)


def browser():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def fetch(client, path, data=None, headers=None):
    request = urllib.request.Request(
        BASE + path,
        data=data,
        headers=headers or {},
        method='POST' if data is not None else 'GET',
    )
    try:
        result = client.open(request, timeout=10)
    except urllib.error.HTTPError as error:
        result = error
    with result:
        return result.status, result.geturl(), result.read().decode('utf-8')


def form(client, path, fields):
    body = urllib.parse.urlencode(fields).encode()
    return fetch(client, path, body, {'Content-Type': 'application/x-www-form-urlencoded'})


def token(html):
    match = re.search(r'name="csrf_token" value="([0-9a-f]{64})"', html)
    if not match:
        raise AssertionError('CSRF token missing from form')
    return match.group(1)


def check(condition, name):
    if not condition:
        raise AssertionError(name)
    print('PASS: ' + name)


def multipart(client, path, fields, filename, filebytes):
    boundary = 'edp-' + uuid.uuid4().hex
    parts = []
    for key, value in fields.items():
        parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n'
                      f'{value}\r\n').encode())
    parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="signature"; '
                  f'filename="{filename}"\r\nContent-Type: image/png\r\n\r\n').encode()
                 + filebytes + b'\r\n')
    parts.append((f'--{boundary}--\r\n').encode())
    return fetch(client, path, b''.join(parts), {'Content-Type': f'multipart/form-data; boundary={boundary}'})


anonymous = browser()
status, dest, login_html = fetch(anonymous, '/')
check(status == 200 and 'page=login' in dest and 'Academic workspace' in login_html,
      'unauthenticated intake redirects to sign-in')
csrf = token(login_html)
status, _, _ = form(anonymous, '?page=login', {
    'action': 'login', 'csrf_token': 'wrong', 'email': fixture['email'],
    'password': fixture['password']})
check(status == 403, 'bad CSRF rejected before login')
status, _, intake_html = form(anonymous, '?page=login', {
    'action': 'login', 'csrf_token': csrf, 'email': fixture['email'],
    'password': fixture['password']})
check(status == 200 and 'Create a repair draft' in intake_html,
      'fictional intake staff login establishes authenticated session')
csrf = token(intake_html)
status, _, _ = form(anonymous, '?page=intake', {
    'action':'create_draft','csrf_token':'not-real','branch_id':str(fixture['branch_id']),
    'device_type_code[]':'LAPTOP','reported_symptoms[]':'No display','make[]':'Demo','model[]':'DemoBook'})
check(status == 403, 'draft POST without valid CSRF rejected')
status, dest, draft_html = form(anonymous, '?page=intake', [
    ('action','create_draft'),('csrf_token',csrf),('branch_id',str(fixture['branch_id'])),
    ('device_type_code[]','LAPTOP'),('reported_symptoms[]','No display'),('make[]','Example'),('model[]','Laptop One'),
    ('device_type_code[]','DESKTOP'),('reported_symptoms[]','Power issue'),('make[]','Example'),('model[]','Tower Two'),
])
match = re.search(r'page=draft(?:&|&amp;)id=(\d+)', dest + draft_html)
check(status == 200 and 'Issue draft' in draft_html and match is not None,
      'two-device draft created through authenticated HTTP form')
id_ = match.group(1)
csrf = token(draft_html)
fields = {
    'action':'issue','csrf_token':csrf,'draft_id':id_,'customer_mode':'new',
    'full_name':'Fictional Classroom Customer','phone':'09123456789',
    'email':'fictional.customer@example.test','signed_terms':'yes',
}
status, _, invalid_html = multipart(anonymous, '?page=draft&id='+id_, fields, 'fake.png', b'not a PNG')
check(status == 422 and 'valid PNG' in invalid_html,
      'invalid signature bytes rejected before issuance')
png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL4WQAAAABJRU5ErkJggg==')
status, dest, receipt = multipart(anonymous, '?page=draft&id='+id_, fields, 'signed.png', png)
check(status == 200 and 'Issue successful' in receipt and 'page=receipt' in dest,
      'signed-image intake issued through protected HTTP endpoint')
check(re.search(r'RT-[A-F0-9]{12}', receipt) and
      re.search(r'<dd>[A-F0-9]{5}</dd>', receipt) and '6789' not in receipt,
      'internal Ticket Code and short customer code shown without PIN')
status, _, second = multipart(anonymous, '?page=draft&id='+id_, fields, 'signed.png', png)
check(status == 404 and 'Issue successful' not in second,
      'issued draft cannot be reissued')
status, _, repeat = fetch(anonymous, '?page=receipt')
check(status == 200 and 'Receipt no longer available' in repeat,
      'one-time confirmation is not redisplayed')
# A second ticket can safely reuse the same customer through branch-scoped lookup.
status, _, new_intake = fetch(anonymous, '?page=intake')
status, new_draft_url, new_draft = form(anonymous, '?page=intake', [
    ('action','create_draft'),('csrf_token',token(new_intake)),
    ('branch_id',str(fixture['branch_id'])),('device_type_code[]','PRINTER'),
    ('reported_symptoms[]','Paper jam'),('make[]','Example'),('model[]','Printer One'),
])
next_id = re.search(r'page=draft(?:&|&amp;)id=(\d+)',new_draft_url)
check(status == 200 and next_id is not None, 'second draft created for returning customer')
second_id = next_id.group(1)
q = urllib.parse.quote('Fictional Classroom')
status, _, searched = fetch(anonymous, '?page=draft&id='+second_id+'&q='+q)
returning = re.search(r'<option value="(\d+)">Fictional Classroom Customer',searched)
check(status == 200 and returning is not None, 'branch-scoped returning customer search is usable')
return_fields = {
    'action':'issue','csrf_token':token(searched),'draft_id':second_id,
    'customer_mode':'existing','customer_id':returning.group(1),
    'signed_terms':'yes',
}
status, _, return_receipt = multipart(anonymous,'?page=draft&id='+second_id,
                                     return_fields,'return.png',png)
check(status == 200 and 'Issue successful' in return_receipt,
      'returning customer can receive a second verified ticket')
csrf = token(return_receipt)
status, _, out = form(anonymous, '?page=intake', {'action':'logout','csrf_token':csrf})
check(status == 200 and 'Academic workspace' in out,
      'POST logout ends session and returns to login')
status, _, protected = fetch(anonymous, '?page=intake')
check(status == 200 and 'Academic workspace' in protected,
      'signed-out session cannot open intake')

cashier = browser()
status, _, html = fetch(cashier, '?page=login')
status, _, response = form(cashier, '?page=login', {
    'action':'login','csrf_token':token(html),'email':fixture['cashier_email'],
    'password':fixture['cashier_password']})
check(status == 401 and 'Invalid staff credentials' in response,
      'cashier account cannot authenticate to intake-only academic preview')
print('All isolated academic web smoke checks passed.')
