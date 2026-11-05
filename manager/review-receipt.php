<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/helpers.php";
$user = require_login();
$db = get_db_connection();
$reg_id = (int) ($_GET["id"] ?? 0);
$sql =
    "SELECT r.*,u.name AS student_name,u.email AS student_email,s.title AS session_title,s.club_id,s.session_date,s.start_time,s.status AS session_status,c.name AS club_name FROM registrations r JOIN users u ON u.id=r.user_id JOIN sessions s ON s.id=r.session_id JOIN clubs c ON c.id=s.club_id WHERE r.id=?";
$q = $db->prepare($sql);
$q->execute([$reg_id]);
$reg = $q->fetch();
if (!$reg) {
    render_error_page(404);
}
if (!is_club_manager($reg["club_id"], $user["id"])) {
    render_error_page(403);
}
$is_self = $reg["user_id"] === $user["id"];
$error = null;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        $error = "Your form session expired. Reload and try again.";
    } elseif ($is_self) {
        $error = "Another authorised manager must review your own payment.";
    } else {
        $db->beginTransaction();
        try {
            // Same lock order as reservation and organiser cancellation: session, registration.
            $q = $db->prepare("SELECT id FROM sessions WHERE id=? FOR UPDATE");
            $q->execute([$reg["session_id"]]);
            $q = $db->prepare($sql . " FOR UPDATE");
            $q->execute([$reg_id]);
            $reg = $q->fetch();
            $cancelling = !empty($reg["cancellation_requested_at"]) || $reg["session_status"] === "cancelled";
            $decision = $_POST["decision"] ?? "";
            $reason = trim($_POST["rejection_reason"] ?? "");
            $receipt_query = $db->prepare('SELECT id FROM payment_receipts WHERE registration_id=? AND receipt_path=?');
            $receipt_query->execute([$reg_id, $reg['receipt_path']]);
            $current_receipt_id = (int)$receipt_query->fetchColumn();
            if ($reg["status"] !== "awaiting_approval" || !$reg["receipt_path"]) {
                $error =
                    "This receipt is no longer awaiting a decision. Return to participants for its current status.";
            } elseif (!$current_receipt_id || (int)($_POST['receipt_id'] ?? 0) !== $current_receipt_id) {
                $error = 'The receipt changed since you opened this form. Inspect the current receipt before deciding.';
            } elseif (!in_array($decision, ["approve", "reject"], true)) {
                $error = "Choose a payment decision.";
            } elseif ($decision === "reject" && ($reason === "" || mb_strlen($reason) > 255)) {
                $error = "Enter a rejection reason of 1–255 characters.";
            } else {
                $q = $db->prepare("UPDATE payment_receipts SET decision=?, rejection_reason=?, reviewed_by=?, reviewed_at=NOW() WHERE registration_id=? AND receipt_path=? AND decision='awaiting_review'");
                $q->execute([
                    $decision === 'approve' ? 'approved' : 'rejected',
                    $decision === 'reject' ? $reason : null,
                    $user['id'], $reg_id, $reg['receipt_path'],
                ]);
                if ($q->rowCount() !== 1) throw new RuntimeException('Receipt history is unavailable or already reviewed. Reload before retrying.');
                if ($decision === "approve") {
                    $next = $cancelling ? "cancellation_pending" : "confirmed";
                    $q = $db->prepare(
                        "UPDATE registrations SET status=?,rejection_reason=NULL,updated_at=NOW() WHERE id=?",
                    );
                    $q->execute([$next, $reg_id]);
                } else {
                    $deadline = date(
                        "Y-m-d H:i:s",
                        min(time() + 86400, strtotime($reg["session_date"] . " " . $reg["start_time"])),
                    );
                    $next = $cancelling ? "cancelled" : "payment_rejected";
                    $q = $db->prepare(
                        "UPDATE registrations SET status=?,rejection_reason=?,payment_deadline=?,updated_at=NOW() WHERE id=?",
                    );
                    $q->execute([$next, $reason, $deadline, $reg_id]);
                }
                $db->commit();
                header(
                    "Location: " .
                        app_url(
                            "/manager/participants.php?session_id=" .
                                $reg["session_id"] .
                                "&msg=" .
                                ($decision === "approve" ? "approved" : "rejected"),
                        ),
                );
                exit();
            }
            $db->rollBack();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
$cancelling = !empty($reg["cancellation_requested_at"]) || $reg["session_status"] === "cancelled";
$can_review = $reg["status"] === "awaiting_approval" && !empty($reg["receipt_path"]) && !$is_self;
$receipt_query = $db->prepare('SELECT id FROM payment_receipts WHERE registration_id=? AND receipt_path=?');
$receipt_query->execute([$reg_id, $reg['receipt_path']]);
$displayed_receipt_id = (int)$receipt_query->fetchColumn();
$page_title = "Review payment evidence";
require_once __DIR__ . "/../includes/header.php";
?>
<div class="journey-page">
 <header class="journey-header"><nav class="journey-breadcrumb" aria-label="Breadcrumb"><a href="<?= app_url(
     "/manager/participants.php?session_id=" . $reg["session_id"],
 ) ?>">Participants</a> / Payment review</nav><h1>Review payment evidence.</h1><p class="journey-subtitle"><?= e(
    $reg["student_name"],
) ?> · <?= e($reg["session_title"]) ?></p></header>
 <?php if ($error): ?><div class="journey-notice notice-error" role="alert" tabindex="-1" data-focus-error><?= e(
    $error,
) ?></div><?php endif; ?>
 <div class="journey-columns review">
  <section class="journey-card"><h2>Receipt submission</h2><?= get_status_badge($reg["status"]) ?>
   <?php if ($reg["receipt_path"] && is_file(__DIR__ . "/../" . $reg["receipt_path"])): ?>
    <?php $evidence_url = app_url("/evidence.php?registration_id=" . $reg_id); ?>
    <?php if (
        !str_ends_with(strtolower($reg["receipt_path"]), ".pdf")
    ): ?><img class="receipt-inline" src="<?= $evidence_url ?>" alt="Payment receipt submitted by <?= e(
    $reg["student_name"],
) ?>"><?php endif; ?>
    <a class="journey-button" href="<?= $evidence_url ?>" target="_blank" rel="noopener">Open full receipt ↗</a>
   <?php else: ?><div class="journey-callout"><strong>Receipt file unavailable</strong><p>No readable receipt is attached. Check with the participant through your existing contact channel.</p></div><?php endif; ?>
   <p class="journey-help">Evidence is private to this participant and authorised club managers.</p>
  </section>
  <section class="journey-card"><h2>Payment decision</h2><dl class="journey-facts"><dt>Payer</dt><dd><?= e(
      $reg["student_name"],
  ) ?></dd><dt>Email</dt><dd><?= e($reg["student_email"]) ?></dd><dt>Quoted amount</dt><dd><?= format_price(
    $reg["price_snapshot"],
) ?></dd><dt>Reviewer</dt><dd><?= e($user["name"]) ?></dd><dt>Club</dt><dd><?= e(
    $reg["club_name"],
) ?></dd><dt>Submitted</dt><dd><?= format_deadline($reg["receipt_uploaded_at"]) ?></dd></dl>
   <div class="journey-callout"><strong>Check external settlement first</strong><p>A receipt alone does not verify payment. Check the club’s bank records before deciding. You cannot review your own payment.</p></div>
   <?php if (
       $cancelling
   ): ?><div class="journey-notice"><strong>Cancellation requested</strong><p>If money arrived, approve settlement to queue a full refund. Complete cancellation without refund only after checking that no transfer arrived.</p></div><?php endif; ?>
   <?php if ($can_review): ?><form method="POST" autocomplete="off">
     <?= csrf_field() ?>
     <input type="hidden" name="receipt_id" value="<?= $displayed_receipt_id ?>">
     <div class="journey-field"><label for="rejection_reason"><?= $cancelling
         ? "Settlement check notes (required to complete without refund)"
         : "Rejection reason (required when rejecting)" ?></label><textarea id="rejection_reason" name="rejection_reason" rows="3" maxlength="255" placeholder="Explain what needs correcting…"><?= e(
    $_POST["rejection_reason"] ?? "",
) ?></textarea><p class="journey-help"><?= $cancelling
    ? "Record the external settlement check. A rejected receipt is not proof that no money arrived."
    : "Rejection allows up to 24 hours to resubmit, capped at session start. Rejection at or after start allows no further upload." ?></p></div>
     <div class="journey-actions"><button class="journey-button primary" type="submit" name="decision" value="approve" data-pending-label="Saving decision…"><?= $cancelling
         ? "Payment received — queue refund"
         : "Approve payment" ?></button><button class="journey-button" type="submit" name="decision" value="reject" data-pending-label="Saving decision…"><?= $cancelling
    ? "No transfer received — complete cancellation"
    : "Reject with reason" ?></button></div>
   </form><?php else: ?><div class="journey-callout"><strong><?= $is_self
    ? "Another reviewer is needed"
    : "No decision available" ?></strong><p><?= $is_self
    ? "Another authorised manager or setup admin must review this payment."
    : "This record is no longer awaiting payment review. Its current status is shown above." ?></p></div><?php endif; ?>
   <div class="journey-actions"><a class="journey-button" href="<?= app_url(
       "/manager/participants.php?session_id=" . $reg["session_id"],
   ) ?>">Back to participants</a></div>
  </section>
 </div>
 <?php render_receipt_history($reg_id); ?>
</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
