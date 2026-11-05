# Taylor's SportsHub MVP

Updated: 5 November 2026 (Asia/Kuala_Lumpur).

This document describes the current PHP application and its retained MVP rules. It replaces the earlier free-only baseline and incorporates the approved paid-session scope. Historical design and discovery records remain supporting context; the gaps listed here distinguish implemented behavior from unimplemented requirements.

## Purpose and users

SportsHub gives Taylor's students a place to discover club sports sessions, reserve a place, submit payment evidence where required, and follow their registration. Club organisers manage published sessions, participant records, receipt decisions, and external refund records.

The project serves an assessment demonstration and a proposed small pilot. The underlying problem, real organiser workload, and student usability have not been validated with representative users. Assessment compliance cannot be established without the actual rubric.

Primary actors:

- Visitor: browses clubs and published session information.
- Student: self-registers with an `@sd.taylors.edu.my` address, signs in, joins or reserves individual sessions, manages own profile, and accesses own registration/evidence.
- Club manager: a student with a separate appointment in `club_managers`; manages only appointed clubs. Appointment does not depend on membership or participation.
- Setup administrator: seeded account with authority across clubs. There is no public role assignment or broad administrator dashboard.

The demo seeds Badminton, Basketball, and Pickleball clubs. Alex Wong is appointed to Badminton; the other seeded clubs have no appointed manager. A one-club pilot remains a proposed operating arrangement, rather than a limit enforced by the application.

## Product and technical boundary

- Individual free and paid sports sessions, with one MYR price and finite capacity per session.
- Native PHP, HTML, CSS, JavaScript, PDO MySQL/MariaDB, and Apache/XAMPP; no frontend or backend framework.
- Automatic database/schema setup and receipt-history migration through the setup code. Synthetic seed content supports local demonstrations.
- Malaysia local time: PHP uses `Asia/Kuala_Lumpur`; database connections use `+08:00`.
- External bank/DuitNow payments and refunds. The website records evidence and organiser decisions; it does not execute or verify money transfers.
- Responsive student and manager interfaces; shared branding, form feedback, and registration-button interactions.

## Implemented requirements

| ID | Current capability | Key behavior / verification target |
| --- | --- | --- |
| M1 | Public discovery and club profiles | Search sessions by keyword, sport, date and free/paid price; sort by session date or confirmed-registration popularity; paginate results. Show session cover, club, venue, price, time, capacity and signup state. Draft details are restricted to authorised managers. |
| M2 | Authentication and profile | Student-domain signup, unique email, password hashing, session regeneration, logout with CSRF, active-account checks, profile and password updates. Email syntax does not establish verified university identity. Users cannot self-assign manager/admin authority. |
| M3 | Free joining | A logged-in student joins an available future published session and receives a confirmed registration immediately. Full, closed, started and cancelled sessions reject new reservations. |
| M4 | Paid reservation | Reserve a place, save the quoted amount and bank-instruction text, and set a payment deadline at the earlier of 24 hours from reservation or session start. Show payment instructions and receipt upload. |
| M5 | Capacity and retries | Serialise reservation decisions under a session lock and count active holds. One registration row exists per student/session. Repeated active signup returns the existing registration. Financial rebooking is restricted to preserve existing evidence/refund history. |
| M6 | Receipt submission and history | Accept JPG, PNG, WebP or PDF up to 5 MB within the permitted payment/resubmission window. Timely evidence holds the place during review, including after session start. Keep receipt attempts, decisions, rejection reasons and available reviewer metadata. |
| M7 | Manager receipt decisions | Appointed managers/admin inspect evidence, approve or reject the current receipt, and supply a reason for rejection. Review locks/rechecks current state and receipt identity. Managers cannot approve their own payment. |
| M8 | Student registration status | My Activities and payment details show authoritative status, next step, rejection reason, deadline, cancellation reason, refund progress and retained evidence. Access is restricted to the student or authorised club managers/admin. |
| M9 | Student withdrawal | Before start, free/unpaid/rejected registrations cancel immediately. Paid confirmed registrations request a full external refund and collect private recipient details. Under-review cancellation remains held pending settlement review. |
| M10 | Refund recording | Authorised managers record external refund amount, reason/reference, actor and time; proof upload is optional. Positive amounts cannot exceed the remaining entitlement. Partial records leave cancellation pending; full recorded refund completes cancellation. Self-refund recording is blocked. |
| M11 | Session management | Appointed managers create/edit drafts, publish, close signup, cancel with a reason, and inspect scoped participants. Once published, title, owning club, sport, venue, date, times, price and capacity remain fixed; description and cover remain editable. Schedule/price changes require cancellation and replacement with fresh signup. |
| M12 | Organiser cancellation | Cancelled sessions reject new signup and uploads. Free/unpaid/rejected registrations cancel; confirmed paid registrations require full refund; uploaded evidence remains awaiting settlement review where necessary. Preserve cancellation reason and financial resolution separately. |
| M13 | Static inbox | Inbox is a read-only sample preview with synthetic messages. Registration/payment actions do not deliver messages or maintain real unread/read state. |
| M14 | Navigation and recovery | Shared desktop/mobile navigation, keyboard-operated account menu and drawer, one account-header separator, and no redundant mobile signed-in line. Standalone 403/404/500 recovery pages use shared branding without requiring database-backed navigation. |

## Registration and capacity contract

| Registration status | Occupies a place | Meaning |
| --- | --- | --- |
| `awaiting_payment` | Yes, until expiry | Paid reservation awaiting timely evidence. |
| `awaiting_approval` | Yes | Uploaded evidence awaits an external-settlement decision; remains held after start. |
| `payment_rejected` | Yes, until expiry | Correction/resubmission allowed until the earlier of 24 hours after rejection or session start. Rejection after start provides no further upload opportunity. |
| `confirmed` | Yes | Free signup or manager-approved paid registration. Confirmation is not attendance. |
| `cancellation_pending` | Yes | Paid cancellation awaits full recorded external refund. |
| `expired` | No | Payment/resubmission deadline passed without eligible evidence. |
| `cancelled` | No | Cancellation completed, including any required full recorded refund. |

Expiry is checked during relevant web requests. There is no scheduled cleanup worker; stale unpaid/rejected rows may remain until a request triggers expiry. Manual signup closure stops new reservations without cancelling existing payment windows. Session cancellation blocks uploads immediately.

## Payment, cancellation and history rules

- Save the quoted amount and bank-instruction text when a paid reservation begins. Receipt upload is evidence, not automatic settlement confirmation.
- A receipt awaiting review requires a manager decision even if cancellation was requested. Confirmed money leads to refund pending; a verified no-transfer decision can complete cancellation.
- Student self-service withdrawal ends at session start. No dedicated manager workflow for exceptional post-start withdrawal is implemented.
- Refund recipient details and evidence are available only through authorised registration/manager paths. Direct receipt/refund upload URLs are blocked by Apache rules; authorised evidence is served through `evidence.php`.
- Replacement receipts retain earlier files/decisions through `payment_receipts`. Legacy migration can preserve known evidence but cannot reconstruct previously overwritten files or unknown reviewer identities/times.
- Rebooking a registration with retained receipt/refund evidence is blocked instead of overwriting financial history. A complete linked registration-attempt history is not implemented.

## UI behavior

- Taylor's crimson identity, shared logo renderer, system typography and responsive layouts.
- Session search keeps its pill-shaped primary button. Reset filters and Most popular share the same link-button design and at least 44px target height.
- Editable text/date/time/number inputs, textareas and selects share sessions-style hover/focus feedback: neutral unfocused fields, white focused field, red border and soft halo without a second outer outline. Preserve disabled, readonly and explicit validation-error states.
- Dropdown option menus use native browser/platform behavior. File pickers, checkboxes and radio controls retain their own interaction patterns.
- Registration actions share hover/press feedback and reduced-motion handling. The existing high-contrast focus limitation remains deferred at the user's request.

## Security and reliability measures

The implementation uses prepared queries, output escaping in server templates, password hashing, session regeneration, HttpOnly/SameSite session-cookie settings, CSRF checks on protected writes, ownership/club-scope checks, transaction locks, and MIME/size checks for financial uploads. These are implemented controls, not a claim of a completed independent security audit.

Manager review and refund actions block self-approval/self-refund recording. Global exception handling logs errors and renders a generic 500 response; individual transaction handlers have differing levels of local recovery. Production credentials, HTTPS/cookie configuration, backups, recovery, retention and operator procedures require deployment review.

## Existing presentation-only features and known gaps

These visible elements must not be interpreted as completed backend features:

- Club membership prices, sample committee/member counts, guest wording and Apply for membership are presentation/demo content. There is no membership application, guest registration, membership eligibility or discounted-price backend.
- Forgot password is a placeholder link. Remember me does not implement persistent login tokens.
- Inbox provides no live notifications, email delivery, messaging or operational response guarantee.
- Payment displays a club QR or fallback image; the QR is not an immutable reservation snapshot or verified payment destination. No separate versioned terms snapshot is stored.
- Session discovery uses fixed November/December 2026 filter choices. The listing query does not consistently exclude already-started published sessions despite upcoming wording; reservation handlers enforce the start boundary.
- Receipt history exists, but full registration-attempt history, late-discovered-transfer reconciliation, scheduled expiry and exceptional post-start withdrawal remain incomplete.
- No team signup, waitlist, attendance tracking, event reviews, payment gateway, club-creation UI or manager-appointment UI.

## Verification and acceptance

Current automated checks are executable implementation checks, not usability or operational evidence:

- `tests/run-existing.php` runs six PHP suites with assertions enabled against disposable seeded databases: public pages, reservations/capacity, manager scope/review, activities/cancellation, end-to-end helper/state checks, and receipt-history migration.
- `tests/ux-journey.cjs` exercises real browser/HTTP registration, upload, review, cancellation, refund, expiry, access and concurrent-reservation paths; captures desktop/mobile states and axe results.
- `tests/noted-gaps.cjs` covers signup restrictions, published-term immutability, retained evidence, protected access, receipt lightbox, native controls, keyboard navigation, branding and selected UI regressions.
- Browser tooling uses Playwright and axe installed outside the application. Generated browser fixtures are cleaned up; visits can still execute the application's normal expiry sweep.
- PHP/JavaScript syntax and diff checks complement these suites. Passing finite tests does not establish absence of all security, concurrency or accessibility defects.

Verification on 5 November 2026: all six PHP suites passed, the journey runner passed 70 checks with zero axe findings in captured states and no browser JavaScript errors, and the focused browser runner passed 11 checks including shared field feedback and reset/popular styling. These results describe the tested local environment and scenarios.

Remaining release validation:

1. Representative students and organisers complete discovery, signup/payment, status and management tasks without coaching; record errors, assistance and timings.
2. Establish a real pilot club, appointed operator and backup, external communication channel, payment-review target and refund process.
3. Review production identity/data handling, credentials, HTTPS, retention, backup/recovery and incident responsibilities.
4. Broaden race/error-path tests, especially expiry/upload/cancellation combinations and storage/database failures.
5. Obtain the assessment rubric and map required evidence before claiming assessment coverage.

## Repository documentation

- `MVP-PRD.md`: current product scope and implementation boundary; this document.
- `CONTEXT.md`: domain-language reference with older free-only wording; consult this current PRD for payment/capacity scope.
- `UX-REFINEMENT.md`: historical UX work and test report; not required at runtime, and some limitations are superseded.
- `docs/`: historical discovery/planning, paid-scope decisions, Figma tooling, design assets and local verification evidence. It is currently locally excluded from Git.
- `scripts/`: currently empty; not used by application code or the test runners.
- `tests/`: executable regression checks and disposable test-data helpers; not application features.
