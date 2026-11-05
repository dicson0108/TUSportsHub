# Taylor's University SportsHub

SportsHub is a PHP web application for campus sports sessions. Students can discover sessions, reserve places, submit payment receipts, and track registrations. Club managers can publish sessions, review receipts, and record external refunds.

The application is configured for Taylor's University student email addresses. It includes demonstration data and prototype features; it is not a payment gateway.

## Features

### Students

- Browse clubs and published sessions without signing in.
- Search and filter sessions by keyword, sport, date, and price.
- Create a student account, sign in, and manage profile details.
- Join free sessions immediately or reserve a paid place.
- Upload payment evidence and view receipt decisions and submission history.
- Follow registration status, deadlines, cancellations, and refund progress in My Activities.
- Request cancellation before a session starts.

### Club managers and administrators

- Manage sessions and participants within authorised clubs.
- Create drafts, publish sessions, close signup, and cancel sessions with a reason.
- Review payment evidence and reject receipts with an explanation.
- Record partial or full refunds made outside the application.
- Access protected evidence and retained receipt history.

Manager permissions come from club appointments, not from an email address or club membership. Seeded administrators have cross-club authority. Users cannot assign themselves these roles through signup.

## Registration and payments

Free signup confirms immediately. A paid reservation holds one place until the earlier of 24 hours after reservation or session start. The participant must pay externally and upload evidence before that deadline.

A timely receipt holds the place during review, including after session start. A manager checks external settlement before approving or rejecting it. Rejection opens a new resubmission window capped at session start.

| Status | Holds a place | Meaning |
| --- | --- | --- |
| `awaiting_payment` | Yes, until expiry | Paid reservation awaiting evidence. |
| `awaiting_approval` | Yes | Evidence awaits a manager's decision. |
| `payment_rejected` | Yes, until expiry | Corrected evidence may be submitted within the remaining window. |
| `confirmed` | Yes | Free signup or an approved paid registration. |
| `cancellation_pending` | Yes | Cancellation awaits a full recorded external refund. |
| `expired` | No | The payment or resubmission deadline passed. |
| `cancelled` | No | Cancellation and any required refund recording are complete. |

Student cancellation closes at session start. Free and unpaid places can be released immediately; approved paid places require a full external refund. Cancellation while a receipt is under review requires a settlement decision first.

SportsHub records payment and refund evidence. It does not transfer money or verify bank settlement automatically. Registration confirmation does not record attendance.

## Requirements

- PHP 8.2 or later with `pdo_mysql`, `mbstring`, and `fileinfo` enabled.
- MySQL or MariaDB with InnoDB support.
- Apache configured to honour the repository's `.htaccess` files.
- Write permission for `assets/uploads/` when testing financial uploads.
- A modern browser.

XAMPP or MAMP can provide a local Apache/PHP/database environment. The application does not require Composer, npm, or a frontend build step to run. Browser tests have separate Node.js dependencies.

## Local setup

### 1. Get the source

Clone or download this repository into a directory served by Apache. For example:

```text
<apache-document-root>/sportshub/
```

These examples use [http://localhost/sportshub/](http://localhost/sportshub/). Substitute your own host, port, and directory prefix throughout the instructions.

### 2. Configure the database

Start Apache and your database server. Open [config/db.php](config/db.php) and set the database host, database name, username, and password for your local environment.

Use a dedicated development database. Initial setup needs permission to create the database and tables and load demonstration records. Existing installations also need the permissions required by the receipt-history migration.

The current PDO connection uses the database server's default port. For a non-default port, update the DSN in `get_db_connection()`; the declared `DB_PORT` constant is not currently passed to PDO.

### 3. Configure the URL prefix

Normal application links detect the installation prefix automatically. Apache's error-document paths in [.htaccess](.htaccess) must match your installation directory. For the example above, use:

```apache
ErrorDocument 403 /sportshub/403.php
ErrorDocument 404 /sportshub/404.php
ErrorDocument 500 /sportshub/500.php
```

For an installation at the domain root, use `/403.php`, `/404.php`, and `/500.php` instead.

Apache must also honour [assets/uploads/.htaccess](assets/uploads/.htaccess), which blocks direct access to financial uploads. Authorised access is provided through [evidence.php](evidence.php). PHP's built-in server does not apply these Apache rules.

### 4. Open the application

Visit your local application URL. On a fresh installation, [config/setup.php](config/setup.php) creates the schema, imports demonstration data, and applies the receipt-history migration. No manual SQL import is needed.

Do not manually rerun [database/seed.sql](database/seed.sql) against data you want to keep: it clears the seeded application tables.

Create a student account to explore the student journey. The configured signup domain is `@sd.taylors.edu.my`; this format check does not verify university identity. Local manager and administrator demonstration accounts are defined in the seed file. This guide intentionally does not reproduce account identities or credentials.

Use demonstration accounts and payment material only in an isolated development environment. Replace them and configure real operating procedures before public deployment.

## Using the application

| User | Typical workflow |
| --- | --- |
| Visitor | Open Explore Sessions or Sports Clubs, then view a session's details and availability. |
| Student | Sign up or sign in, join or reserve a session, submit evidence if required, and check My Activities for the next step. |
| Club manager | Open Manager Workspace, create or select a session, inspect participants, review receipts, and record external refunds where required. |
| Administrator | Use the same protected manager operations with cross-club authority. There is no separate general-purpose admin dashboard. |

Published session terms remain fixed, including title, club, sport, venue, date, times, price, and capacity. Managers may update descriptions and covers. Changed schedules or prices require cancellation and a replacement session with fresh signup.

Managers cannot approve their own payment or record their own refund; another authorised manager or administrator must act.

## Project layout

| Path | Purpose |
| --- | --- |
| `index.php`, `sessions.php`, `session-detail.php` | Home page and session discovery. |
| `clubs.php`, `club.php` | Club directory and profiles. |
| `register.php`, `login.php`, `profile.php` | Account registration, authentication, and profile settings. |
| `reserve.php`, `payment.php`, `upload-receipt.php`, `cancel-registration.php` | Registration and payment-evidence journey. |
| `my-activities.php` | The signed-in student's registration status and history. |
| `manager/` | Club-scoped session, participant, review, and refund operations. |
| `includes/` | Shared authentication, branding, navigation, error handling, and helpers. |
| `config/` | Application paths, database connection, setup, and migrations. |
| `database/` | Schema, demonstration seed data, and receipt-history migration. |
| `assets/css/`, `assets/js/`, `assets/images/` | Frontend styles, interactions, and imagery. |
| `assets/uploads/` | Private financial uploads, excluded from version control. |
| `tests/` | PHP checks, browser regressions, and disposable test fixtures. |

## Testing

Run commands from the repository root. Use a local development installation with Apache and the database running. PHP must be available on the command line.

### PHP suites

Run the end-to-end state/helper suite through the disposable-database runner:

```sh
php -d zend.assertions=1 -d assert.exception=1 tests/run-existing.php test_e2e.php
```

The runner creates and drops a separate test database, so the test database account needs permission for both operations. Keep assertions enabled and use the runner rather than executing the seed-dependent test files directly.

Other suites are `test_m3.php`, `test_m4.php`, `test_m5.php`, `test_m6.php`, and `test_receipt_history.php`. Replace the last filename in the command to run one of them.

These PHP suites cover public-page rendering, reservation/capacity helpers, manager scope, review and cancellation states, refunds, and receipt-history migration. Browser tests provide real HTTP-handler and concurrent-reservation coverage.

### Browser suites

Install Node.js, npm, and Google Chrome. Install the test dependencies outside the application checkout:

```sh
npm install --prefix ../sportshub-test-tools --no-save playwright @axe-core/playwright
```

Set `NODE_PATH` to that directory's `node_modules` and `SPORTSHUB_URL` to your running application URL.

PowerShell:

```powershell
$env:NODE_PATH = (Resolve-Path ../sportshub-test-tools/node_modules).Path
$env:SPORTSHUB_URL = 'http://localhost/sportshub'
node tests/noted-gaps.cjs
node tests/ux-journey.cjs
```

macOS or Linux shell:

```sh
export NODE_PATH="$PWD/../sportshub-test-tools/node_modules"
export SPORTSHUB_URL='http://localhost/sportshub'
node tests/noted-gaps.cjs
node tests/ux-journey.cjs
```

Run the browser suites sequentially; they share a disposable-fixture state file. They create generated test accounts and records and clean them up after execution. Browser requests can also run the application's normal overdue-reservation expiry checks.

- `noted-gaps.cjs` checks targeted handler, receipt-history, keyboard-navigation, responsive-layout, and styling regressions.
- `ux-journey.cjs` checks registration, upload, review, cancellation, refunds, access boundaries, and final-place concurrency. It also runs axe checks and writes local screenshots/results to `docs/ux-evidence/`.

Finite automated checks do not establish complete security, accessibility, usability, or concurrency coverage. Real bank transfers and real-world organiser workflows are not exercised by these suites.

## Current limitations

- Inbox is a static sample preview, without live messages or notifications.
- Membership applications, guest participation, member discounts, and some club statistics are presentation-only content.
- Forgot password is a placeholder; Remember me does not implement persistent login tokens.
- Reservation expiry is request-driven; no scheduled cleanup worker is included.
- Date filters include fixed demonstration months. Discovery can display already-started published sessions, although reservation handlers enforce the start boundary.
- The quoted amount and bank-instruction text are saved with a reservation; the QR image is not an immutable reservation snapshot or a verified payment destination.
- Receipt history is retained, but complete linked registration-attempt history is not implemented. Rebooking with existing financial evidence is blocked to preserve that evidence.
- Exceptional post-start withdrawal and late-discovered-transfer reconciliation have no dedicated workflow.

## Reporting issues and contributing

When reporting a problem, include the affected route, reproduction steps, expected and actual behaviour, and relevant PHP/browser versions. Remove names, account details, passwords, tokens, payment receipts, and other private information from reports, logs, and screenshots.

Keep changes focused, run the relevant suites, and describe the behaviour changed and how it was verified. Never commit real database credentials, financial evidence, production data, or local test-output folders.

## License

All rights reserved. No separate license file is currently included in this repository.
