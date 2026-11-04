# Taylor's University SportsHub (Web Application)

> The centralized club sports and session management platform for Taylor's University students and club organizers.

---

## 🏸 Overview

Taylor's SportsHub replaces fragmented campus announcements, spreadsheet signups, and manual receipt collection with a unified sports session reservation platform. 

Organizers (Club Managers) publish sports sessions, manage participant rosters, review payment receipts, and record external refunds. Students discover free and paid sessions, reserve seats atomically, upload payment slips, and manage their activity registrations.

---

## 🌟 Core Features & Governing Scope

1. **Strict Student Authentication & Role Scoping**:
   - Student self-registration requires an active Taylor's student email (`@sd.taylors.edu.my`).
   - Staff administrator accounts (`@taylors.edu.my`) are pre-seeded and cannot be created via public registration.
   - Club Manager appointments are club-scoped (`club_managers` mapping). A manager of one club cannot manage another club.
   - Anti-self-approval rule: Managers cannot approve their own payment or log their own refund.

2. **Atomic Capacity & Multi-State Reservation**:
   - Atomic database locks prevent overbooking under concurrent requests.
   - **Capacity-Holding States**: `confirmed`, `awaiting_payment`, `awaiting_approval`, `payment_rejected`, `cancellation_pending` all occupy finite seats.
   - Free sessions confirm immediately upon registration.
   - Paid sessions initiate a 24-hour reservation window (capped at session start) with DuitNow QR and bank transfer instructions.

3. **Receipt Verification & Resubmission Window**:
   - Timely receipt upload transitions status to `awaiting_approval`, holding the seat past start during manager review.
   - Managers approve valid receipts (-> `confirmed`) or reject invalid submissions with a mandatory reason (-> `payment_rejected`), granting a new 24h window (capped at start) to resubmit.
   - Background and query-time sweeps automatically expire overdue unpaid reservations.

4. **Voluntary Cancellation & External Refund Logging**:
   - Cancellations before session start are self-service.
   - Unpaid or free registrations cancel immediately, freeing capacity.
   - Paid confirmed registrations cancelled before start receive full refund entitlement (`cancellation_pending`).
   - Student submits private receiving bank details. Club manager records external bank transfer reference and proof screenshot, transitioning status to `cancelled` and releasing capacity once fully refunded.
   - Session cancellation by an organizer automatically queues confirmed paid registrations into `cancellation_pending` for full refund.

5. **Inbox Prototype Preview**:
   - Explicitly marked synthetic static demonstration showing sample notifications for confirmations, cancellations, and club announcements.

---

## 🛠️ Technology Stack

- **Backend**: Native PHP 8.2+ (No heavy frameworks, high performance, clean modular architecture)
- **Database**: MySQL 5.7+ / MariaDB 10.4+ via PDO with prepared statements and transactions
- **Frontend**: Vanilla HTML5, CSS3 with Apple-calibrated styling and Taylor's University crimson branding (`#c8102e`)
- **Security**: CSRF tokens on all POST mutations, `htmlspecialchars` output escaping, BCrypt password hashing, session regeneration

---

## 🚀 Setup & Installation (XAMPP)

1. **Place Code in XAMPP `htdocs`**:
   ```
   C:/xampp/htdocs/MyProject/27TUSportsHub
   ```

2. **Start Apache & MySQL**:
   Open XAMPP Control Panel and start **Apache** and **MySQL**.

3. **Database Initialization**:
   The database connection auto-initializes on first page load! Alternatively, import manually via MySQL CLI or phpMyAdmin:
   ```powershell
   Get-Content database/schema.sql | & "C:\xampp\mysql\bin\mysql.exe" -u root
   Get-Content database/seed.sql | & "C:\xampp\mysql\bin\mysql.exe" -u root
   ```

4. **Access Application**:
   Navigate to [http://localhost/MyProject/27TUSportsHub/](http://localhost/MyProject/27TUSportsHub/) in your browser.

---

## 👥 Seeded Demonstration Accounts

All accounts are pre-seeded with password: `Password123!`

| Role | Name | Email | Managed Club / Permissions |
| :--- | :--- | :--- | :--- |
| **Club Manager** | Alex Wong | `alex@sd.taylors.edu.my` | Taylor's Badminton Club (Manager Workbench) |
| **Student** | Liam Tan | `liam@sd.taylors.edu.my` | Registered for Free Sunday Open Play (`confirmed`) |
| **Student** | Chloe Lee | `chloe@sd.taylors.edu.my` | Registered for Paid Footwork Clinic (`awaiting_approval`) |
| **Student** | Danial Hakim | `danial@sd.taylors.edu.my` | Reserved spot for Paid Footwork Clinic (`awaiting_payment`) |
| **Global Admin** | SportsHub Admin | `admin@taylors.edu.my` | Global platform administrator |

---

## 🧪 Automated End-to-End Test Suite

Run the full end-to-end integration and concurrency test suite via PHP CLI:

```powershell
php tests/test_e2e.php
```

All 8 test suites verify:
- Strict student email domain enforcement
- Scoped club manager access & admin privileges
- Multi-state capacity seat calculations
- Overdue reservation expiry sweep
- Manager receipt approval and rejection state transitions
- Participant voluntary cancellation and bank detail capture
- External refund logging and seat release
- Organiser session cancellation refund cascading
