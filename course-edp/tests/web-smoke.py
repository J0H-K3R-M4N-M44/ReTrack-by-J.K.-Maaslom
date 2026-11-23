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
    'action':'create_draft','csrf_token':'not-real','draft_request_key':'a'*64,'branch_id':str(fixture['branch_id']),
    'device_type_code[]':'LAPTOP','defect_code[]':'NO_DISPLAY','reported_symptoms[]':'No display','make[]':'Demo','model[]':'DemoBook'})
check(status == 403, 'draft POST without valid CSRF rejected')
create_payload = [
    ('action','create_draft'),('csrf_token',csrf),('draft_request_key',re.search(r'name="draft_request_key" value="([0-9a-f]{64})"',intake_html).group(1)),('branch_id',str(fixture['branch_id'])),
    ('device_type_code[]','LAPTOP'),('defect_code[]','NO_DISPLAY'),('reported_symptoms[]','No display'),('make[]','Example'),('model[]','Laptop One'),
    ('device_type_code[]','DESKTOP'),('defect_code[]','NO_POWER'),('reported_symptoms[]','Power issue'),('make[]','Example'),('model[]','Tower Two'),
]
status, dest, draft_html = form(anonymous, '?page=intake', create_payload)
match = re.search(r'page=draft(?:&|&amp;)id=(\d+)', dest + draft_html)
check(status == 200 and 'Draft #' in draft_html and match is not None,
      'two-device draft created through authenticated HTTP form')
id_ = match.group(1)
status, duplicate_url, _ = form(anonymous,'?page=intake',create_payload)
check(status == 200 and ('page=draft&id='+id_) in duplicate_url,
      'replayed draft-create POST resolves to the original ticket')
status, _, listing = fetch(anonymous, '?page=drafts')
check(status == 200 and ('?page=draft&amp;id='+id_) in listing,
      'My Drafts lists created records for recovery')
status, _, resumed = fetch(anonymous, '?page=draft&id='+id_)
check(status == 200 and 'Edit saved devices' in resumed,
      'draft can be reopened and corrected from a new request')
edit_fields = [
    ('action','edit_draft'),('csrf_token',token(resumed)),('draft_id',id_),
    ('device_type_code[]','LAPTOP'),('defect_code[]','NO_POWER'),
    ('reported_symptoms[]','Power button unresponsive'),('make[]','Example'),('model[]','Laptop One'),
    ('device_type_code[]','DESKTOP'),('defect_code[]','OVERHEATING'),
    ('reported_symptoms[]','Machine runs very hot'),('make[]','Example'),('model[]','Tower Two'),
]
status, _, edited = form(anonymous,'?page=draft&id='+id_,edit_fields)
check(status == 200 and 'Power button unresponsive' in edited,
      'saved device data and defect choice can be corrected before issuance')
csrf = token(edited)
fields = {
    'action':'issue','csrf_token':csrf,'draft_id':id_,'customer_mode':'new',
    'full_name':'Fictional Classroom Customer','phone':'09123456789',
    'email':'fictional.customer@example.test','signed_terms':'yes',
}
status, _, invalid_html = multipart(anonymous, '?page=draft&id='+id_, fields, 'fake.png', b'not a PNG')
check(status == 422 and 'valid PNG' in invalid_html,
      'invalid signature bytes rejected before issuance')
png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL4WQAAAABJRU5ErkJggg==')
status, _, empty = multipart(anonymous,'?page=draft&id='+id_,fields,'empty.png',b'')
check(status == 422 and 'image' in empty.lower(), 'zero-byte image upload is rejected')
status, _, too_big = multipart(anonymous,'?page=draft&id='+id_,fields,'large.png',png+b'x'*(5*1024*1024))
check(status == 422 and 'image' in too_big.lower(), 'image larger than 5 MiB is rejected')
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
    ('action','create_draft'),('csrf_token',token(new_intake)),('draft_request_key',re.search(r'name="draft_request_key" value="([0-9a-f]{64})"',new_intake).group(1)),
    ('branch_id',str(fixture['branch_id'])),('device_type_code[]','PRINTER'),('defect_code[]','OTHER'),
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
    'confirm_phone':'09123456789',
    'signed_terms':'yes',
}
bad_fields = dict(return_fields,confirm_phone='00000000000')
status, _, wrong_phone = multipart(anonymous,'?page=draft&id='+second_id,
                                 bad_fields,'return.png',png)
check(status == 422 and 'full recorded phone' in wrong_phone,
      'returning customer must confirm the complete recorded phone number')
status, _, return_receipt = multipart(anonymous,'?page=draft&id='+second_id,
                                     return_fields,'return.png',png)
check(status == 200 and 'Issue successful' in return_receipt,
      'returning customer can receive a second verified ticket')
other = browser()
_, _, other_login = fetch(other,'?page=login')
status, _, other_intake = form(other,'?page=login',{
    'action':'login','csrf_token':token(other_login),
    'email':fixture['other_email'],'password':fixture['other_password']})
check(status == 200 and 'Create a repair draft' in other_intake,
      'another branch staff member can authenticate for their own branch')
status, _, forbidden = fetch(other,'?page=draft&id='+id_)
check(status == 404 and 'Draft #' not in forbidden,
      'staff from another branch cannot read a saved draft')
status, _, forbidden_post = form(other,'?page=draft&id='+id_,{
    'action':'abandon_draft','csrf_token':token(forbidden),'draft_id':id_})
check(status == 404, 'cross-branch draft modification is rejected')
# Deactivation after login invalidates the active session on its next request.
subprocess.run(['php','course-edp/tests/web-fixture.php','revoke',fixture['other_email']],
               check=True, capture_output=True,env=os.environ.copy())
status, _, revoked = fetch(other,'?page=intake')
check(status == 200 and 'Academic workspace' in revoked,
      'disabled staff account cannot retain its active session')
# Creating a new customer with a phone already used at this branch is rejected.
status, _, third_form = fetch(anonymous,'?page=intake')
third_payload = [
    ('action','create_draft'),('csrf_token',token(third_form)),
    ('draft_request_key',re.search(r'name="draft_request_key" value="([0-9a-f]{64})"',third_form).group(1)),
    ('branch_id',str(fixture['branch_id'])),
    ('device_type_code[]','MONITOR'),('defect_code[]','NO_DISPLAY'),
    ('reported_symptoms[]','Display flickers'),('make[]','Example'),('model[]','DemoMonitor'),
]
status, third_dest, third_page = form(anonymous,'?page=intake',third_payload)
third_match = re.search(r'page=draft(?:&|&amp;)id=(\d+)',third_dest)
check(status == 200 and third_match is not None,'another draft can be saved')
third_id = third_match.group(1)
duplicate_fields = {
    'action':'issue','csrf_token':token(third_page),'draft_id':third_id,
    'customer_mode':'new','full_name':'Duplicate Customer','phone':'09123456789',
    'signed_terms':'yes',
}
status, _, duplicate = multipart(anonymous,'?page=draft&id='+third_id,
                                  duplicate_fields,'third.png',png)
check(status == 422 and 'returning customer' in duplicate,
      'duplicate registered contact prompts returning-customer verification')
status, _, abandoned = form(anonymous,'?page=draft&id='+third_id,{
    'action':'abandon_draft','csrf_token':token(duplicate),'draft_id':third_id})
check(status == 200 and 'My Drafts' in abandoned,
      'unissued draft can be deliberately abandoned')
status, _, unavailable = fetch(anonymous,'?page=draft&id='+third_id)
check(status == 404 and 'Draft #' not in unavailable,
      'abandoned draft cannot be issued or reopened')
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
