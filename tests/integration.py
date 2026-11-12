"""Local integration checks. Creates and cleans up only unique synthetic fixtures."""
import datetime
import http.cookiejar
import hashlib
import json
import pathlib
import re
import secrets
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8000').rstrip('/')
ROOT = pathlib.Path(__file__).resolve().parent.parent
TOKEN = secrets.token_hex(8)
PREFIX = 'qa_' + TOKEN
PASSWORD = 'Test-' + TOKEN
passed = 0


def fixture(mode):
    proc = subprocess.run(['php', str(ROOT / 'tests/fixture.php'), mode, TOKEN], capture_output=True, text=True, check=True)
    return json.loads(proc.stdout) if proc.stdout else None


def check(condition, message):
    global passed
    if not condition:
        raise AssertionError(message)
    passed += 1
    print('PASS:', message)


class Browser:
    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def get(self, page='home', **params):
        return self.request(BASE + '/index.php?' + urllib.parse.urlencode(dict(page=page, **params)))

    def request(self, url, data=None):
        request = urllib.request.Request(url, data=urllib.parse.urlencode(data).encode() if data is not None else None)
        try:
            response = self.opener.open(request, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        text = response.read().decode('utf-8')
        check(not re.search(r'(Fatal error|Parse error|Warning:|Notice:)', text), 'response has no PHP errors')
        return response.status, text, response.url

    def post(self, page, data, **params):
        status, text, url = self.get(page, **params)
        token = re.search(r'name="csrf" value="([a-f0-9]+)"', text)
        assert token, 'CSRF token missing'
        return self.request(url, dict(csrf=token.group(1), **data))

    def login(self, suffix, role='user'):
        return self.post('login', dict(action='login', username=PREFIX + suffix, password=PASSWORD, role=role))


try:
    ids = fixture('setup')
    guest = Browser()
    status, text, _ = guest.get()
    check(status == 200 and 'Your health,' in text, 'root homepage renders')
    status, text, _ = guest.get('doctors', q='QA Doctor ' + TOKEN, speciality='QA Cardiology', location='QA Colombo')
    check('QA Doctor ' + TOKEN in text, 'combined doctor filters work')
    check('&lt;script&gt;qa_test&lt;/script&gt;' in text and '<script>qa_test</script>' not in text, 'doctor content is escaped')
    status, text, _ = guest.request(BASE + '/index.php?page=login', dict(action='login', csrf='invalid'))
    check(status == 419, 'invalid CSRF token is rejected')
    status, text, url = guest.get('appointments')
    check('page=login' in url, 'guests cannot read appointments')
    status, text, url = guest.post('login', dict(action='login', username=PREFIX + '_md5', password=hashlib.md5(PASSWORD.encode()).hexdigest(), role='user'))
    check('incorrect' in text, 'a legacy hash cannot itself be used as a password')
    for legacy in ['md5', 'sha1', 'plain']:
        old = Browser()
        status, text, url = old.login('_' + legacy)
        check('page=appointments' in url, legacy + ' account can sign in')
    hashes = fixture('inspect')
    check(all(hashes.values()), 'legacy passwords are upgraded to modern hashes')

    patient = Browser()
    registration = dict(action='register', full_name='QA Patient', username=PREFIX + '_patient', email=PREFIX + '_patient@example.test', password=PASSWORD, confirm_password=PASSWORD, phone_number='0771234567', location='QA Colombo')
    status, text, url = patient.post('register', registration)
    check('page=login' in url and 'Your account is ready' in text, 'patient registration succeeds')
    status, text, url = patient.post('register', registration)
    check('already in use' in text, 'duplicate registration is rejected')
    status, text, url = patient.post('login', dict(action='login', username="' OR 1=1 --", password=PASSWORD, role='user'))
    check('incorrect' in text, 'SQL injection cannot bypass login')
    status, text, url = patient.login('_patient')
    check('page=appointments' in url and 'QA Patient' in text, 'new patient can sign in')
    status, text, _ = patient.post('doctors', dict(action='favourite', doctor_id=ids['doctor']))
    status, text, _ = patient.get('favourites')
    check('QA Doctor ' + TOKEN in text, 'favourite doctor is saved')
    future = (datetime.date.today() + datetime.timedelta(days=7)).isoformat()
    booking = dict(action='book', doctor_id=ids['doctor'], appointment_name='QA Visit ' + TOKEN, phone_number='0771234567', date=future, session='Morning')
    status, text, _ = patient.post('book', dict(booking, date='2020-01-01'), doctor=ids['doctor'])
    check('future appointment date' in text, 'past appointment dates are rejected')
    status, text, url = patient.post('book', booking, doctor=ids['doctor'])
    check('page=appointments' in url and 'QA Visit ' + TOKEN in text, 'appointment booking succeeds')
    match = re.search(r'page=book&amp;edit=(\d+)', text)
    assert match, 'Edit link missing'
    appointment_id = int(match.group(1))
    status, text, _ = patient.post('book', booking, doctor=ids['doctor'])
    check('already have an appointment' in text, 'duplicate booking is rejected')

    attacker = Browser()
    attacker.login('_md5')
    status, text, _ = attacker.get('appointments')
    check('QA Visit ' + TOKEN not in text, 'patients cannot see another patient appointments')
    status, text, _ = attacker.post('appointments', dict(action='cancel', appointment_id=appointment_id))
    check('cannot be cancelled' in text, 'patients cannot cancel another patient appointment')
    status, text, _ = attacker.post('book', dict(booking, action='edit_appointment', appointment_id=appointment_id), edit=appointment_id)
    check('cannot be changed' in text, 'patients cannot edit another patient appointment')

    status, text, _ = patient.post('book', dict(booking, action='edit_appointment', appointment_id=appointment_id, session='Evening'), edit=appointment_id)
    check('Evening' in text and 'updated' in text, 'owned appointment can be edited')
    doctor = Browser()
    status, text, url = doctor.login('_doctor', 'doctor')
    check('page=dashboard' in url and 'QA Visit ' + TOKEN in text, 'doctor dashboard shows their patient appointment')
    status, text, _ = doctor.post('dashboard', dict(action='add_doctor'))
    check(status == 403, 'doctor cannot use administrator actions')
    status, text, _ = patient.post('profile', dict(action='profile', full_name='QA Updated', email=PREFIX + '_patient@example.test', phone_number='0770000000', location='QA Galle'))
    check('QA Updated' in text and 'QA Galle' in text, 'profile edits persist')

    admin = Browser()
    status, text, url = admin.login('_admin', 'admin')
    check('page=dashboard' in url and 'Registered patients' in text, 'administrator dashboard renders')
    status, text, _ = admin.post('dashboard', dict(action='add_doctor', full_name='QA New Doctor ' + TOKEN, username=PREFIX + '_newdoctor', email=PREFIX + '_newdoctor@example.test', password=PASSWORD, speciality='General medicine', location='QA Kandy', gender='Male', price='2000.00', description='Synthetic test doctor'))
    check('Doctor added.' in text, 'administrator can create a doctor')
    newdoctor = Browser()
    status, text, url = newdoctor.login('_newdoctor', 'doctor')
    check('page=dashboard' in url, 'admin-created doctor can sign in')
    check('QA Visit ' + TOKEN not in text, 'doctor cannot see another doctor appointments')
    status, text, _ = patient.post('appointments', dict(action='cancel', appointment_id=appointment_id))
    check('cancelled' in text and 'QA Visit ' + TOKEN not in text, 'owned appointment can be cancelled')
    status, text, _ = patient.post('favourites', dict(action='favourite', doctor_id=ids['doctor'], return_page='favourites'))
    check('QA Doctor ' + TOKEN not in text, 'favourite can be removed')
    status, text, _ = patient.post('profile', dict(action='logout'))
    status, text, url = patient.get('profile')
    check('page=login' in url, 'sign out clears access')
    print(f'\n{passed} checks passed.')
finally:
    fixture('cleanup')
