<?php
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/helpers.php";
$user = require_login();
$reg_id = (int) ($_GET["id"] ?? 0);
$db = get_db_connection();
expire_overdue_reservations();
$stmt = $db->prepare(
    "SELECT r.*, s.title,s.sport_type,s.venue,s.session_date,s.start_time,s.end_time,s.status AS session_status,s.cancellation_reason AS session_cancellation_reason,c.id AS club_id,c.name AS club_name,c.duitnow_qr_path FROM registrations r INNER JOIN sessions s ON r.session_id=s.id INNER JOIN clubs c ON s.club_id=c.id WHERE r.id=?",
);
$stmt->execute([$reg_id]);
$reg = $stmt->fetch();
if (!$reg) {
    render_error_page(404);
}
if ($reg["user_id"] !== $user["id"] && !is_club_manager($reg["club_id"], $user["id"])) {
    render_error_page(403);
}
$is_owner = $reg["user_id"] === $user["id"];
$can_upload =
    $is_owner &&
    in_array($reg["status"], ["awaiting_payment", "payment_rejected"], true) &&
    $reg["session_status"] !== "cancelled" &&
    strtotime($reg["payment_deadline"] ?? "") > time() &&
    strtotime($reg["session_date"] . " " . $reg["start_time"]) > time();
$stmt = $db->prepare(
    "SELECT amount,reason,created_at,id,proof_path FROM refund_records WHERE registration_id=? ORDER BY created_at,id",
);
$stmt->execute([$reg_id]);
$refunds = $stmt->fetchAll();
$refunded = array_sum(array_column($refunds, "amount"));
$remaining = max(0, (float) $reg["price_snapshot"] - $refunded);
$is_refund = $reg["status"] === "cancellation_pending" || !empty($refunds);
$page_title = "Registration · " . $reg["title"];
require_once __DIR__ . "/includes/header.php";
?>
<div class="journey-page">
  <header class="journey-header">
    <nav class="journey-breadcrumb" aria-label="Breadcrumb"><a href="<?= app_url(
        "/my-activities.php",
    ) ?>">My Activities</a> / Registration</nav>
    <h1><?= $can_upload
        ? ($reg["status"] === "payment_rejected"
            ? "Resubmit your receipt."
            : "Complete your payment.")
        : ($is_refund
            ? "Cancellation & refund."
            : "Your registration.") ?></h1>
    <p class="journey-subtitle"><?= e($reg["title"]) ?> · <?= format_session_date(
     $reg["session_date"],
 ) ?> · <?= format_session_time_range($reg["start_time"], $reg["end_time"]) ?></p>
  </header>
  <?php journey_feedback(); ?>
  <?php if ($reg["session_status"] === "cancelled"): ?>
    <div class="journey-notice notice-error"><strong>Session cancelled by organiser</strong><p><?= e(
        $reg["session_cancellation_reason"],
    ) ?></p><p>New reservations and receipt uploads are closed. Payment and refund resolution remain separate.</p></div>
  <?php endif; ?>
  <div class="journey-columns">
    <!-- Payment & Actions Column (Left / Primary) -->
    <div>
      <?php if ($can_upload): ?>
        <section class="journey-card" aria-labelledby="pay-heading">
          <h2 id="pay-heading">Pay <?= format_price($reg["price_snapshot"]) ?></h2>
          
          <!-- Bank Transfer Instructions on Left, DuitNow QR on Right (Side by Side on same line) -->
          <div class="payment-transfer-split">
            <div class="payment-instructions-col">
              <div class="journey-callout">
                <strong>Bank transfer instructions</strong>
                <p style="white-space:pre-line; margin-top: 6px;"><?= e(
                    $reg["payment_instructions_snapshot"] ?:
                    "Instructions unavailable. Contact your club organiser before transferring.",
                ) ?></p>
              </div>
              <p class="journey-help">These instructions were saved when you reserved. Keep your transfer receipt. A transfer alone does not submit it to SportsHub.</p>
            </div>
            <div class="payment-qr-col">
              <div class="duitnow-qr-card">
                <span class="duitnow-qr-badge">DuitNow National QR</span>
                <div class="duitnow-qr-image-wrapper">
                  <img src="<?= app_url('/' . ltrim($reg['duitnow_qr_path'] ?: 'assets/images/payment/duitnow-qr.webp', '/')) ?>" alt="DuitNow QR Code" class="duitnow-qr-img" width="180" height="205">
                </div>
                <div class="duitnow-qr-caption">
                  <strong>Scan with Bank or E-Wallet</strong>
                  <p>Supports Maybank, Touch 'n Go, CIMB & all DuitNow apps.</p>
                </div>
              </div>
            </div>
          </div>

          <h2>Upload your receipt</h2>
          <form method="POST" action="<?= app_url(
              "/upload-receipt.php",
          ) ?>" enctype="multipart/form-data" autocomplete="off">
            <?= csrf_field() ?><input type="hidden" name="registration_id" value="<?= $reg_id ?>">
            <div class="journey-field upload-dropzone" id="receipt-dropzone">
              <label for="receipt">Receipt file</label>
              <p class="journey-help" id="receipt-hint">JPG, PNG, WebP or PDF · Up to 5 MB. Choose a file or drop it here.</p>
              <input type="file" id="receipt" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf" required aria-describedby="receipt-hint receipt-error">
              <p id="receipt-error" class="field-error" aria-live="polite"></p>
            </div>
            <div id="receipt-upload-preview" hidden class="journey-callout" aria-live="polite"><span class="preview-name"></span><span class="preview-size"></span></div>
            <button type="submit" class="journey-button primary" data-pending-label="Submitting receipt…">Submit receipt</button>
            <p class="journey-help">Submit before <?= format_deadline(
                $reg["payment_deadline"],
            ) ?>. Timely submission holds your place while an authorised manager checks external settlement.</p>
          </form>
        </section>
      <?php endif; ?>
      <?php if ($is_refund): ?>
        <section class="journey-card" aria-labelledby="refund-heading">
          <h2 id="refund-heading"><?= $remaining < 0.001 ? "Refund recorded in full" : "Refund in progress" ?></h2>
          <dl class="journey-facts"><dt>Refund entitlement</dt><dd><?= format_price(
              $reg["price_snapshot"],
          ) ?></dd><dt>Recorded so far</dt><dd>RM <?= number_format(
    $refunded,
    2,
) ?></dd><dt>Remaining</dt><dd>RM <?= number_format($remaining, 2) ?></dd></dl>
          <p class="journey-help">Refunds happen externally. This page shows transfers recorded by your organiser; SportsHub does not move money.</p>
          <?php if ($reg["recipient_account_number"]): ?>
            <div class="journey-callout"><strong>Private refund recipient</strong><p><?= e(
                $reg["recipient_bank_name"],
            ) ?><br><?= e($reg["recipient_account_name"]) ?><br><?= e($reg["recipient_account_number"]) ?></p></div>
          <?php elseif (
              $remaining > 0.001
          ): ?><div class="journey-callout"><strong>Recipient details needed</strong><p>Contact your club organiser through your existing private channel to arrange the refund. No turnaround time has been set.</p></div><?php endif; ?>
          <?php foreach ($refunds as $refund): ?><div class="journey-callout"><strong>RM <?= number_format(
    (float) $refund["amount"],
    2,
) ?> recorded</strong><p><?= format_deadline($refund["created_at"]) ?><br><?= e($refund["reason"]) ?></p><?php if (
    $refund["proof_path"]
): ?><a href="<?= app_url(
    "/evidence.php?registration_id=" . $reg_id . "&refund_id=" . $refund["id"],
) ?>" target="_blank" rel="noopener">View refund evidence ↗</a><?php endif; ?></div><?php endforeach; ?>
        </section>
      <?php endif; ?>
      <?php if ($reg["receipt_path"]): ?>
        <section class="journey-card"><h2>Submitted evidence</h2><p class="journey-help">A receipt is evidence of a claimed transfer. Only organiser review confirms settlement.</p><a class="journey-button" href="<?= app_url(
            "/evidence.php?registration_id=" . $reg_id,
        ) ?>" target="_blank" rel="noopener">Open receipt ↗</a></section>
      <?php endif; ?>
      <?php if (!$can_upload && !$is_refund && !$reg["receipt_path"]): ?><section class="journey-card"><h2><?= $reg[
    "status"
] === "expired"
    ? "Your place was released"
    : "Registration details" ?></h2><p><?= e(
    registration_next_step($reg),
) ?></p><a class="journey-button" href="<?= app_url(
    "/my-activities.php",
) ?>">Back to My Activities</a></section><?php endif; ?>
    </div>

    <!-- Your Reservation Summary Column (Right / Sidebar) -->
    <section class="journey-card" aria-labelledby="reservation-heading">
      <h2 id="reservation-heading">Your reservation</h2>
      <?= get_status_badge($reg["status"]) ?>
      <dl class="journey-facts">
        <dt><?= $can_upload ? "Amount due" : "Quoted amount" ?></dt><dd><?= format_price($reg["price_snapshot"]) ?></dd>
        <dt>Club</dt><dd><?= e($reg["club_name"]) ?></dd>
        <dt>Where</dt><dd><?= e($reg["venue"]) ?></dd>
        <?php if ($can_upload): ?><dt>Receipt deadline</dt><dd><?= format_deadline(
    $reg["payment_deadline"],
) ?></dd><?php endif; ?>
        <dt>Session starts</dt><dd><?= format_deadline($reg["session_date"] . " " . $reg["start_time"]) ?></dd>
        <?php if ($reg["receipt_uploaded_at"]): ?><dt>Receipt submitted</dt><dd><?= format_deadline(
    $reg["receipt_uploaded_at"],
) ?></dd><?php endif; ?>
      </dl>
      <div class="journey-callout"><strong>Next step</strong><p><?= e(registration_next_step($reg)) ?></p></div>
      <?php if (
          $reg["rejection_reason"]
      ): ?><div class="journey-notice notice-error"><strong>Rejection reason</strong><p><?= e(
    $reg["rejection_reason"],
) ?></p></div><?php endif; ?>
      <?php if ($reg["cancellation_reason"]): ?><p class="journey-help"><strong>Cancellation reason:</strong> <?= e(
    $reg["cancellation_reason"],
) ?></p><?php endif; ?>
      <div class="journey-actions">
        <a class="journey-button" href="<?= app_url("/session-detail.php?id=" . $reg["session_id"]) ?>">View session</a>
        <?php if ($is_owner && can_cancel_registration($reg)): ?><a class="journey-button" href="<?= app_url(
    "/cancel-registration.php?id=" . $reg_id,
) ?>">Cancel registration</a><?php endif; ?>
        <?php if (!$is_owner): ?><a class="journey-button" href="<?= app_url(
    "/manager/participants.php?session_id=" . $reg["session_id"],
) ?>">Back to participants</a><?php endif; ?>
      </div>
    </section>
  </div>
  </div>
</div>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
