# SportsHub UX refinement — 4 November 2026

[Before/after gallery](docs/ux-evidence/index.html) · [Approved journey map](.archify/lifecycle-registration-20261104-223000/registration.html) · [Browser/axe evidence](docs/ux-evidence/after-audit.json)

## Authority, scope and evidence

`docs/mvp-paid-scope-amendment.md` governs conflicting free-only wording in CONTEXT.md and MVP-PRD.md. The UIUX exports supply intended hierarchy, spacing, presentation and state messaging. They do not establish backend correctness. The sibling `26TUEventManagement` supplies existing system typography, crimson controls, responsive grid, native PHP forms and navigation patterns. Its waitlist, event reviews, attendance and broad admin scope were not carried over.

Reviewed the running localhost app and the connected reserve/upload/review/cancel/refund handlers, shared auth/helpers, schema, seed data and all existing tests. Followed writes through to DB outcomes. The gallery compares the same seeded route/account at 1440 and 390 CSS pixels. Additional captures use disposable synthetic users, clubs, sessions and receipt artwork; those are test scenarios rather than pilot observations.

## Findings and implemented changes

Reference paths below start at `UIUX Designs/`; both desktop and mobile counterparts were considered. Source links point to the current correction, with the original symptom described separately.

| Finding and reproduction | Source / correction | Reference screen |
| --- | --- | --- |
| Session detail claimed cancellation needed 24 hours' notice and implied immediate release for paid cancellations. Open paid details before reserving. | [session-detail.php:120](session-detail.php#L120): cancellation allowed before start; approved payments receive full external refund; pending money resolution retains occupancy. Full/started/closed/cancelled availability is distinguished; visitors no longer receive a signup invitation for unavailable sessions. | `01 Desktop/01 Public Screens/05-session-detail-student.png`; `02 Mobile/01 Public Screens/05-session-detail-student.png` |
| Changing a club's bank details after a reservation changed the payer's instructions. Reserve, update club bank data, reopen payment. | [payment.php:98](payment.php#L98): display saved `payment_instructions_snapshot` and quoted amount. Removed simulated phone artwork described as a scannable QR. Missing usable QR is explicitly stated. | `02 Mobile/02 Student Journey/02-payment-and-receipt.png` |
| Under-review and approved screens still presented amount/deadline as actionable payment demands; expired records lacked meaningful recovery. Submit/approve/expire a reservation, then reopen payment. | [payment.php:20](payment.php#L20), [helpers.php:42](includes/helpers.php#L42): show actionable deadline/upload only in eligible states; explain held versus confirmed, review after start, cancellation and expiry. | `01 Desktop/02 Student Journey/03-receipt-submitted.png`, `04-confirmed.png`; `01 Desktop/04 States and Errors/07-expired.png` |
| Pending refunds were classified under “Cancelled / Past,” with no student view of partial transfers or remaining balance. Cancel approved payment and record a partial refund. | [my-activities.php:16](my-activities.php#L16), [payment.php:123](payment.php#L123): separate Refund pending filter, explicit next step, cancellation reason, entitlement, recorded transfers, remaining balance and private recipient details. | `01 Desktop/02 Student Journey/08-refund-pending.png`, `09-refund-complete.png` |
| Mobile payment nested large paddings and a 260px minimum grid inside a narrow card. Primary action and status badge classes were referenced but undefined. | [journey.css](assets/css/journey.css): shared crimson/outlined controls, status tints, flat cards, wider useful content, stacked mobile grid, readable MYT facts, system typography and consistent focus. Payment, activities, review and refund layouts now follow export hierarchy. | `02 Mobile/02 Student Journey/02-payment-and-receipt.png`; `01 Desktop/03 Club Manager/08-review-payment-receipt.png` |
| Hidden upload input was not keyboard reachable. Server error query strings had no visible explanation. Select unsupported file or fail upload. | [payment.php:105](payment.php#L105), [main.js:365](assets/js/main.js#L365): native reachable file input, persistent field errors, selected-file feedback, server error/retry messages, pending submit feedback. Refund forms retain typed values and reject invalid supplied proof without silently recording the transfer. | `02 Mobile/04 States and Errors/08-upload-error.png`; `01 Desktop/04 States and Errors/09-save-error.png` |
| Stale review could resurrect cancelled/confirmed records. Cancellation during review implied an already-approved refund. Uploading after organiser cancellation was possible. | [review-receipt.php:26](manager/review-receipt.php#L26), [cancel-registration.php:40](cancel-registration.php#L40), [upload-receipt.php:22](upload-receipt.php#L22): lock/recheck current state, separate settlement review from approval, block cancelled-session uploads, preserve review through cancellation. Explicit manager choice: payment arrived → refund pending; verified no transfer → cancellation complete. | `01 Desktop/03 Club Manager/08-review-payment-receipt.png`, `10-review-reject.png`; `02 Mobile/02 Student Journey/07-cancel-registration.png` |
| Refund balance was checked before locking and stale forms could record against non-pending registrations. Old close-signup could change a cancelled session to closed. | [record-refund.php:30](manager/record-refund.php#L30), [close-signup.php:25](manager/close-signup.php#L25): lock/recompute refund balance, accept only pending refunds and valid cents, retain partial state until full resolution; close only published sessions. | `01 Desktop/03 Club Manager/11-record-refund.png`, `12-close-signup.png` |
| Financial file URLs bypassed page ownership checks. Drawer focus escaped; account menu depended on hover; static Inbox implied unread delivery. | [evidence.php](evidence.php), [uploads/.htaccess](assets/uploads/.htaccess), [header.php:292](includes/header.php#L292), [main.js:56](assets/js/main.js#L56): authorised evidence endpoint, Apache denial of direct uploads, real account button, drawer focus containment/Escape restoration, skip link, reduced motion/transparency variants; remove unread dot. Existing receipt zoom also contains/restores focus. | `02 Mobile/01 Public Screens/09-navigation-menu.png`; `01 Desktop/02 Student Journey/12-inbox-preview.png` |
| PHP used Europe/Berlin while DB used Malaysian host time, yielding inconsistent deadline comparisons. Inspect host settings and compare reserved deadline. | [app.php:3](config/app.php#L3), [db.php:21](config/db.php#L21): explicit Asia/Kuala_Lumpur and SQL +08:00. Registration email validation now enforces student domain as required; seeded admin login remains independent. | Payment and rejection screens' exact MYT deadlines |

No automatic payment, refund, live inbox, notification service or operational turnaround promise was added. Removed an unsupported footer contact address; users are directed to their club organiser.

## Verification

- **70 browser checks passed** against real PHP handlers, with DB assertions: paid reservation, upload, rejection/resubmission, approval, free signup, expiry, cancellation during settlement review, partial/full refund, manual closure preserving deadline, post-start review, and cancellation blocking uploads.
- Two independent HTTP sessions competed for the final place; exactly one active hold remained. Retry did not create another place. These finite checks do not establish freedom from every concurrency defect.
- Other-student registration/evidence access and unappointed receipt review were denied. Direct upload URLs returned 403. Stale approval/refund/closure requests could not undo completed outcomes.
- **Axe: zero WCAG A/AA violations in 24 captured journey/state viewport cases** (12 screens × desktop/mobile), including payment, review, participant records, refund form and outcomes. Matched seeded payment/activities also had zero axe findings at both widths. This is not a WCAG certification or a full-site accessibility claim.
- Actual browser keyboard input exercised account button, drawer focus containment, Escape restoration and visible upload control. Reduced-motion media emulation suppressed drawer travel. A 720px viewport verified reflow equivalent to a 1440px desktop at 200%; physical-device and actual browser-zoom testing remain unperformed.
- Visually inspected representative mobile payment/activities and desktop review captures against the exports; reviewed matched seeded captures. No pixel-perfect equivalence is claimed: real data, native file controls, unavailable QR and working navigation intentionally differ from static prototype content.
- All five original PHP suites pass with assertions enabled against fresh disposable seeded databases. One pre-existing heading expectation was updated from `Explore Sports Sessions` to the existing `Find your next session.` heading. Original suites mostly check helper/direct-SQL behavior; the browser runner supplies handler coverage.
- PHP syntax checks and JS syntax checks pass. Archify validate/deliver/provenance/browser gates pass. Diagram is a compact workflow of the **approved contract**, with cancellation/expiry detail in its associated cards, not a certification that every backend rule is implemented.

Run locally (Playwright/axe are test tools installed outside the app; no app package/dependency changes):

```powershell
$env:NODE_PATH='C:\Users\dicson\.codex\tmp\sportshub-ux-tools\node_modules'
node tests\ux-journey.cjs
Get-ChildItem tests\test_*.php | ForEach-Object {
  php -d zend.assertions=1 -d assert.exception=1 tests\run-existing.php $_.Name
}
```

Browser fixtures use generated identities and remove their records/files on completion. Existing-suite runner creates a named disposable database and drops only that database in `finally`; the working application's records are not reset. Browser visits still execute the app's normal overdue-expiry sweep. `docs/` was already excluded through `.git/info/exclude`; the gallery/screenshots are local review artifacts, not automatically included in Git changes.

## Remaining limitations and assumptions

1. **History is incomplete:** current schema stores the latest receipt/decision and does not link every previous attempt, receipt and reviewer decision. Replacement uploads do not provide a navigable full history. Financial rebooking is now stopped before overwriting existing evidence/refund totals; this is a deliberate ceiling until linked attempts/history exist. It is not full compliance with the amendment's history requirement.
2. **QR and terms snapshots:** the text/amount snapshot is used, but a usable immutable QR and a separate versioned terms snapshot are absent. The UI states QR unavailability instead of presenting simulated artwork as usable payment instructions.
3. **Rejected-payment money exceptions:** evidence/reason remains visible and warns that rejection does not prove no money arrived. A dedicated reconciliation workflow for a transfer discovered after rejection/cancellation is not implemented. Manager-recorded exceptional post-start withdrawal is also absent.
4. **Expiry is request-driven:** no scheduled cleanup worker was added. Broader expiry/upload/review/cancellation race combinations and server/file-write failures need more coverage; final-place concurrency alone is insufficient. Apache access rules must be adapted if deploying under another web server.
5. No real payment, bank verification, refund receipt by a student, live delivery or operational response time was measured. Operators, backup reviewers and private contact arrangements remain unresolved. No real club data was invented.
6. **Usability unvalidated:** no representative students/managers were available. Uncoached task success, completion time, errors and actual organiser workload remain unmeasured. Automated checks and visual inspection cannot substitute for those observations.

Review guidance: [current Vercel Web Interface Guidelines](https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md). Apple principles were applied to feedback, spatial predictability, type hierarchy and accessibility; no motion library was needed.

Diagram provenance uses a local immutable snapshot of the approved amendment because `docs/` was absent from the existing commit. Current branch and staging index were left unchanged. The map does not attribute uncommitted implementation changes to the old commit.
