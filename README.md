# Appolo Medical Portal

PHP + MySQL patient portal. The refreshed portal uses a shared responsive layout
and a single entry point, `index.php`. No Node build or Composer install is needed.

## Run locally

1. Start MySQL in XAMPP.
2. On a new database, import `database/online_medical_protal.sql` in phpMyAdmin.
   If you already imported it, no further schema changes are needed.
3. In the project folder run `php -S localhost:8000`.
4. Keep that terminal running and open http://localhost:8000/.

`html/index.php` redirects to the new homepage for existing bookmarks. You do not
need to move the project into `htdocs`. Alternatively, Apache can serve the whole
project from its document root, including from a subfolder.

PHP requires the `mysqli` extension and mysqlnd. The installed PHP 7.4 can run this
code; use a maintained PHP release before deploying. Database defaults are
`127.0.0.1:3306`, database `online_medical_protal`, user `root`, empty password.
Optional environment variables: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`,
`DB_PASSWORD`. The portal uses Asia/Colombo time.

## Accounts and workflows

- **Patients:** register, sign in, search doctors by name/speciality/location, save
  favourites, book/edit/cancel future appointments, and edit their profile.
- **Doctors:** sign in to see their own patient appointments and edit their profile.
- **Administrators:** see appointment/patient/doctor counts, view appointments, and
  add doctor listings with sign-in accounts. Create the first administrator locally
  with `php scripts/create-admin.php`. There are no default passwords or demo records.

New passwords use `password_hash` and `password_verify`. Existing MD5, SHA-1 and
plaintext passwords from the original project are accepted by the new login and
upgraded after a successful sign-in. Generated `id`/`name` database columns support
legacy column naming. Appointment ownership is checked on reads and changes;
forms use CSRF tokens, prepared queries, and escaped output.

The directory stays empty until an administrator adds doctors. Consultations use
date/session preferences, not individual time slots. A past date is shown as a
past visit; this does not mean attendance or payment has been confirmed.

## Project structure

- `index.php`: page routing, page data and shared views.
- `app/bootstrap.php`: database helpers, session, validation and form actions.
- `assets/portal.css`, `assets/portal.js`: responsive design and interactions.
- `database/`: reconstructed schema and compatibility notes.
- `tests/`: local integration checks; see below.
- `html/`, `php/`, older `css/` and `js/`: retained legacy implementation.

## Verification

Run `php -l index.php` and `php -l app/bootstrap.php` for syntax checks.
With a running PHP server and MySQL, run:

```powershell
python tests/integration.py http://localhost:8000
```

The integration test creates uniquely named synthetic accounts and appointments
and removes only its own records in a `finally` block. Use a local development
database. It covers authentication, legacy-password migration, search, favourites,
booking/editing/cancellation, profile updates, role isolation, CSRF rejection,
validation and admin-created doctor login.

## Remaining work

The refreshed portal deliberately does not collect payment card details or upload
medical documents. The legacy lab and payment pages remain on disk and have not
been modernized. The older payment handler stores card numbers/CVVs and legacy
handlers contain SQL injection and access-control weaknesses. Do not deploy the
legacy directories or use real patient/payment data. Before production, restrict
the web document root to reviewed routes, implement a payment provider and private
file storage, and add rate limiting, password reset, audit logging, backups and
an appointment availability/status model. Browser refresh after a successful form
submission is safe because the new actions redirect after saving.

Fonts are optional Google Fonts with system fallbacks; existing local images are
reused. No third-party JavaScript is required.
