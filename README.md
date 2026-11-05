# Taylor's University SportsHub Web App

A campus sports platform for Taylor's University students, club managers, and administrators. Adapted and evolved from `26TUEventManagement`, SportsHub replaces fragmented WhatsApp announcements, manual spreadsheets, and unorganized bank receipt submissions with a single structured hub for physical club sports sessions.

---

## Features

### Student Experience
- **Domain-Restricted Authentication**: Only active Taylor's student emails (`@sd.taylors.edu.my`) can self-register. Session-based authentication with BCrypt password hashing, session regeneration, and CSRF protection.
- **Session Discovery & Filtering**: Search upcoming sessions by keyword (title, venue, club), sport type (e.g. Badminton, Basketball), and price tier (Free or Paid).
- **Session Detail & Dynamic Availability**: Live capacity counter showing finite capacity, currently occupied seats, and remaining places.
- **Atomic Capacity Reservation**:
  - Free sessions confirm immediately with zero financial friction.
  - Paid sessions lock finite capacity atomically via database row locks and start a **24-hour reservation countdown** (capped at session start time) for external payment and receipt upload.
- **DuitNow QR & Bank Transfer Instructions**: Instant snapshot of the organising club's official bank details (Bank name, account holder, account number, and simulated DuitNow QR code).
- **Receipt Upload & Review Tracking**: Secure receipt upload (PNG, JPG, WebP, PDF <= 5MB). Submitting evidence holds the seat past start time while the club manager verifies external settlement.
- **Resubmission Window**: If a receipt is rejected by a manager, the student receives the specific rejection reason and a fresh 24-hour window (capped at session start) to upload corrected evidence.
- **My Activities Dashboard**: Split view of active/upcoming sessions and concluded/historical sessions with Apple-inspired status badges.
- **Voluntary Withdrawal & Full Refund**:
  - Free and unpaid reservations cancel immediately before start time and release capacity.
  - Confirmed paid participants cancelling before start time are entitled to a 100% full external refund. The system privately collects the student's receiving bank details and queues the record into `Cancellation Pending` until the manager logs the transfer.
- **Static Inbox Preview**: Read-only prototype interface showing simulated automated notifications for confirmations, cancellations, and club announcements.

### Club Manager & Administrator Experience
- **Scoped Manager Workbench**: Club managers access operations strictly for clubs they are appointed to. Platform administrators (`@taylors.edu.my`) hold global oversight across all clubs.
- **Session Lifecycle Management**:
  - Create and save draft sessions or publish immediately.
  - **Fixed Published Terms**: In accordance with sports hub governance, published date, time, venue, price, and capacity remain immutable to protect enrolled students. Schedule changes require cancellation and replacement.
  - Close signup manually before start time while preserving existing 24-hour payment windows for reserved students.
  - Cancel sessions with a required public reason, which cascades all paid confirmed participants into `Cancellation Pending` for full refund logging.
- **Participant Roster**: Live roster showing student names, student emails, phone numbers, quoted prices, registration timestamps, and current state badges.
- **Receipt Verification**: Inspect uploaded payment slips in full resolution. Approve valid transfers or reject with a required reason.
- **Anti-Self-Approval Guardrail**: Per audit rules, club managers cannot approve their own payment receipts or record their own refunds; another appointed manager or admin must act.
- **External Refund Recording**: View student's private bank details, enter the refund amount and transfer reference, upload transfer screenshot evidence, and log the actor. The registration updates to `Cancelled` and frees capacity once the full amount is satisfied.

---

## Payment & Capacity State Machine

Capacity is strictly bounded. The following states **occupy** 1 place atomically:
1. `confirmed`: Free session signup or manager-approved paid registration.
2. `awaiting_payment`: Seat held under the 24-hour countdown timer.
3. `awaiting_approval`: Receipt submitted, holding seat during manager review.
4. `payment_rejected`: Receipt rejected with reason, holding seat during 24-hour resubmission window.
5. `cancellation_pending`: Paid confirmed spot cancelled before start, holding place until manager records the external refund.

The following states **release** the place:
- `cancelled`: Voluntary cancellation before payment, or after external refund completed.
- `expired`: Unpaid reservation where the 24-hour or session-start deadline passed without receipt upload.

---

## Tech Stack

- **Backend**: Native PHP 8.2+ (PDO MySQL, prepared statements, strict parameter binding, transaction locks)
- **Database**: MySQL 5.7+ / MariaDB 10.4+ (`InnoDB`, `utf8mb4_unicode_ci`)
- **Frontend**: Semantic HTML5, CSS3 (Taylor's crimson `#c8102e`, Apple fluid layouts, responsive squircle cards), Vanilla JS
- **Security**: CSRF tokens on all POST endpoints, strict `@sd.taylors.edu.my` domain filter, BCrypt password hashing, session cookie security (`HttpOnly`, `SameSite=Lax`)
- **Testing**: Native PHP CLI end-to-end integration test suite (`tests/test_e2e.php`)

---

## Getting Started

These instructions assume **XAMPP or MAMP is installed** with PHP 8.2+ and `pdo_mysql`.

### 1. Place the project in `htdocs`

Ensure the folder is located directly in your web server's document root named **`27TUSportsHub`**:
- **Windows (XAMPP)**: `C:\xampp\htdocs\MyProject\27TUSportsHub`
- **macOS (XAMPP)**: `/Applications/XAMPP/xamppfiles/htdocs/MyProject/27TUSportsHub`
- **macOS (MAMP)**: `/Applications/MAMP/htdocs/MyProject/27TUSportsHub`

### 2. Start Apache and MySQL

Open your XAMPP Control Panel and start **Apache** and **MySQL**.

### 3. Check Database Settings

Open [config/db.php](config/db.php). The default settings connect to local MySQL on port `3306`:

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'tu_sportshub');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);
```

### 4. Automatic Database Initialization

On first visit, the system acquires an advisory setup lock, creates `tu_sportshub`, builds all 6 tables from [database/schema.sql](database/schema.sql), and imports all seeded users, clubs, and sessions from [database/seed.sql](database/seed.sql). **No manual SQL import is needed.**

To initialize manually via CLI:
```powershell
Get-Content database/schema.sql | & "C:\xampp\mysql\bin\mysql.exe" -u root
Get-Content database/seed.sql | & "C:\xampp\mysql\bin\mysql.exe" -u root
```

### 5. Access the Web Application

Open your browser to:
[http://localhost/MyProject/27TUSportsHub/](http://localhost/MyProject/27TUSportsHub/)

---

## Seeded Demonstration Accounts

All accounts use the password: `Password123!`

| Role | Name | Email | Permissions & Seeded Data |
| :--- | :--- | :--- | :--- |
| **Club Manager** | Alex Wong | `alex@sd.taylors.edu.my` | Manager of Taylor's Badminton Club (Manager Workbench access) |
| **Student** | Liam Tan | `liam@sd.taylors.edu.my` | Registered for Free Sunday Open Play (`confirmed`) |
| **Student** | Chloe Lee | `chloe@sd.taylors.edu.my` | Registered for Paid Footwork Clinic (`awaiting_approval`) |
| **Student** | Danial Hakim | `danial@sd.taylors.edu.my` | Reserved place for Paid Footwork Clinic (`awaiting_payment`) |
| **Global Admin** | SportsHub Admin | `admin@taylors.edu.my` | Pre-seeded platform administrator with global oversight |

---

## Project Structure

```
.
├── .gitignore                   # Shared exclusions (uploads, logs, temp files)
├── .htaccess                    # Standalone Apache error document routes
├── 403.php                      # Access denied error view
├── 404.php                      # Page not found error view
├── 500.php                      # Internal server error view
├── README.md                    # System documentation and operational guide
├── cancel-registration.php      # Student voluntary withdrawal and refund bank details form
├── club.php                     # Public sports club profile with active sessions list
├── clubs.php                    # Public directory of campus sports clubs
├── index.php                    # SportsHub homepage with hero banner, stats, and session grid
├── inbox.php                    # Static prototype preview with synthetic notifications
├── login.php                    # Student and staff admin authentication
├── logout.php                   # CSRF-protected session termination
├── my-activities.php            # Student dashboard for active places and past session history
├── payment.php                  # 24h timer countdown, DuitNow QR, bank instructions, and receipt form
├── register.php                 # Strict Taylor's student account registration (@sd.taylors.edu.my)
├── reserve.php                  # Atomic capacity lock and session reservation handler
├── session-detail.php           # Comprehensive session overview with real-time seat counts
├── sessions.php                 # Public session discovery with keyword, sport, and price filters
├── upload-receipt.php           # Secure receipt file upload handler (PNG, JPG, WebP, PDF)
├── assets/
│   ├── css/
│   │   └── style.css            # Apple-calibrated design system & Taylor's red palette
│   └── uploads/
│       ├── .gitkeep             # Uploads placeholder
│       ├── receipts/            # Uploaded student payment slips
│       └── refunds/             # Uploaded manager refund evidence
├── config/
│   ├── app.php                  # Base path auto-detection and URL builder
│   ├── db.php                   # Database credentials and PDO singleton connection
│   └── setup.php                # Auto-installer with advisory concurrency locks
├── database/
│   ├── schema.sql               # Tables: users, clubs, club_managers, sessions, registrations, refund_records
│   └── seed.sql                 # Seed accounts, pilot clubs, synthetic sessions, and registrations
├── includes/
│   ├── auth.php                 # Authentication guards, CSRF helper, and club manager checks
│   ├── error.php                # Standalone error page renderer and exception catcher
│   ├── footer.php               # Shared global footer
│   ├── header.php               # Responsive Apple-style navigation header with user dropdown
│   └── helpers.php              # Seat calculation, status badge helpers, and expiry sweeps
├── manager/
│   ├── cancel-session.php       # Organizer session cancellation with cascade to refund queue
│   ├── close-signup.php         # Manual signup closure preserving active payment holds
│   ├── participants.php         # Scoped participant roster with receipt review buttons
│   ├── record-refund.php        # External bank refund recording with proof upload
│   ├── review-receipt.php       # Manager receipt inspection, approval, or rejection with reason
│   ├── session-edit.php         # Session creator and draft editor with immutable published terms
│   └── sessions.php             # Club manager operations dashboard
└── tests/
    ├── test_m3.php              # Public discovery and page rendering test
    ├── test_m4.php              # Atomic capacity and 24h reservation timer test
    ├── test_m5.php              # Manager scope, receipt approval, and refund test
    ├── test_m6.php              # Student activities and voluntary cancellation test
    └── test_e2e.php             # Full 8-stage automated integration test suite
```

---

## Automated Test Suite

To run the automated end-to-end integration and concurrency test suite:

```powershell
php tests/test_e2e.php
```

### Verified Test Assertions:
1. `Strict student email validation (@sd.taylors.edu.my)`
2. `Club manager scope isolation and admin authority`
3. `Multi-state atomic capacity calculations (confirmed, awaiting payment/approval/refund)`
4. `Overdue reservation expiry sweep`
5. `Receipt review approval and rejection state transitions`
6. `Paid voluntary cancellation and private bank details capture`
7. `External refund recording and seat release`
8. `Session cancellation cascade to cancellation_pending`

---

## License

Developed for Taylor's University SportsHub. All rights reserved.
