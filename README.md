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

This guide assumes you use **XAMPP or MAMP** for local development. Use the package's Apache server, PHP 8.2 or later, and MySQL/MariaDB. For MAMP, select Apache rather than Nginx so the included `.htaccess` rules apply.

Ensure `pdo_mysql`, `mbstring`, and `fileinfo` are enabled in the PHP configuration used by your package. The application also needs a modern browser and write permission for `assets/uploads/` when testing financial uploads.

No separate PHP/database installation, Composer, npm, or frontend build step is needed to run the application. Node.js and npm are only needed for the optional browser test suites.

## Local setup with XAMPP or MAMP

### 1. Start the local servers

| Package | What to do |
| --- | --- |
| XAMPP | Open the XAMPP Control Panel or manager application and start **Apache** and **MySQL**. |
| MAMP | Open MAMP, select **Apache**, choose PHP 8.2 or later, and start the web and database servers. Note the web-server and MySQL ports shown in its settings. |

### 2. Place the project in the document root

Clone or download this repository inside your package's document root (`htdocs`). Any project folder name works; these examples use `sportshub`:

| Installation | Example project folder |
| --- | --- |
| XAMPP on Windows | `C:/xampp/htdocs/sportshub/` |
| MAMP on macOS | `/Applications/MAMP/htdocs/sportshub/` |
| Other XAMPP/MAMP installations | `<configured-document-root>/sportshub/` |

If you changed the installation location or document root, use the folder configured in your package instead. Avoid placing the project inside a second nested copy of the downloaded repository.

| Package settings | Example application URL |
| --- | --- |
| XAMPP with Apache on port 80 | [http://localhost/sportshub/](http://localhost/sportshub/) |
| MAMP with Apache on its default port 8888 | [http://localhost:8888/sportshub/](http://localhost:8888/sportshub/) |

Use the ports actually configured in your installation. See the [XAMPP Windows guide](https://www.apachefriends.org/faq_windows) and [MAMP port settings](https://documentation.mamp.info/en/MAMP-Mac/Reference/Settings/Ports/) for package-specific instructions.

### 3. Configure the database connection

Set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, and `DB_PORT` in the environment used by Apache and CLI PHP. Defaults in [config/db.php](config/db.php) suit a standard local XAMPP installation. Use your package's database credentials and a dedicated development database name; keep private values outside tracked source files. If you edit the defaults locally instead, do not commit your credentials.

XAMPP commonly uses MySQL port `3306`; MAMP's default MySQL port is `8889`. Confirm the actual port in your package settings. The web-server port in the browser URL is separate from this database port.

The connection reads `DB_PORT` directly. For MAMP's default database port, set it to `8889` in both Apache's and CLI PHP's environments. The application does not load `.env` files automatically.

Initial setup needs permission to create the database and tables and load demonstration records. Existing installations also need the permissions required by the receipt-history migration. No manual database creation or SQL import is needed when these permissions are available.

### 4. Check Apache path handling

Application links detect the installation prefix automatically. [.htaccess](.htaccess) uses a relative rewrite target for missing pages, so no project folder paths need editing. Enable Apache's `mod_rewrite` and allow `.htaccess` overrides for this directory to use the branded missing-page handler. Without rewriting, Apache handles missing URLs itself. Application permission/errors use the PHP error pages; errors raised directly by Apache use its configured responses.

For aliases or reverse proxies whose public URL differs from the document-root layout, set `APP_BASE_PATH` in the PHP environment to the public prefix (for example `/sportshub`, or `/` for the site root). CLI PHP otherwise defaults to the site root.

Keep [assets/uploads/.htaccess](assets/uploads/.htaccess) in place and ensure Apache honours it. It blocks direct access to financial uploads; authorised access is provided through [evidence.php](evidence.php).

### 5. Open SportsHub

Visit the application URL for your package from step 2. On first visit, [config/setup.php](config/setup.php) creates the schema, imports demonstration data, and applies the receipt-history migration.

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

Run commands from the project folder while XAMPP/MAMP's Apache and database servers are running. Use the PHP version bundled with your package, matching the version selected for the website.

Add your package's PHP executable directory to the terminal's `PATH` if `php` is not recognised. For XAMPP on Windows this is typically `<XAMPP-installation>/php/`; for MAMP on macOS, use the `bin` directory of the selected version under `<MAMP-installation>/bin/php/`. Run `php -v` to check the version before continuing.

### PHP suites

Run all helper, public-rendering, and receipt-migration checks:

```sh
php -d zend.assertions=1 -d assert.exception=1 -d assert.active=1 tests/run.php
```

The runner creates and drops a separate test database, so the test database account needs permission for both operations. It rebases fixture dates relative to the current time, without changing demonstration seed files. Assertions must be enabled; individual suites reject direct execution.

To run one suite, append `test_portability.php`, `test_helpers.php`, or `test_receipt_history.php` to that command.

`test_portability.php` checks root/nested installations, CLI paths, explicit URL prefixes and database environment overrides. `test_helpers.php` covers email validation, manager scope, CSRF helpers, every seat-occupying state, full/missing sessions, expiry, cancellation eligibility, and public rendering. `test_receipt_history.php` covers legacy evidence backfill, idempotency, and retained decisions. Workflow checks execute real HTTP handlers in the browser suites; the redundant SQL-only milestone and misleadingly named PHP end-to-end tests have been removed.

### Browser suites

These tests are optional and require Node.js, npm, and Google Chrome in addition to XAMPP/MAMP. Install the test dependencies outside the application checkout:

```sh
npm install --prefix ../sportshub-test-tools --no-save playwright @axe-core/playwright
```

Set `NODE_PATH` to that directory's `node_modules` and `SPORTSHUB_URL` to your running application URL.

PowerShell example for XAMPP on port 80:

```powershell
$env:NODE_PATH = (Resolve-Path ../sportshub-test-tools/node_modules).Path
$env:SPORTSHUB_URL = 'http://localhost/sportshub'
node tests/regressions.cjs
node tests/ux-journey.cjs
```

macOS shell example for MAMP on port 8888:

```sh
export NODE_PATH="$PWD/../sportshub-test-tools/node_modules"
export SPORTSHUB_URL='http://localhost:8888/sportshub'
node tests/regressions.cjs
node tests/ux-journey.cjs
```

`SPORTSHUB_URL` is required: set it to the URL you use in your browser, including your package's port and project folder. No developer-specific URL is assumed. The same commands work in a Linux shell with the appropriate XAMPP URL.

Run the browser suites sequentially; they share a disposable-fixture state file. They create generated test accounts, two clubs (including a manager from another club), and records, then clean them up even if browser launch fails. Use a local development installation: browser requests can also run the application's normal overdue-reservation expiry checks. If a process is forcibly terminated, run `php tests/ux-fixtures.php cleanup` before retrying. Set `PHP_BINARY` if the PHP executable is not on `PATH`; `BROWSER_CHANNEL` defaults to `chrome`.

- `regressions.cjs` checks receipt history, stale decisions, keyboard navigation, responsive layout, portable missing-page routing, immutable published terms, invalid/missing CSRF, wrong-club managers, self-review/refund denial, forged/oversized uploads, invalid refund amounts/proof, and financial rebooking restrictions. Rejected actions must leave database state unchanged; valid actions verify the handlers still work. Exact color/spacing comparisons have been removed.
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
