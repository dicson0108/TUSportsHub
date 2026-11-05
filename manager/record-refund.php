<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/helpers.php";
$user = require_login();
$db = get_db_connection();
$reg_id = (int) ($_GET["id"] ?? 0);
$sql =
    "SELECT r.*,u.name AS student_name,s.title AS session_title,s.club_id FROM registrations r JOIN users u ON r.user_id=u.id JOIN sessions s ON r.session_id=s.id WHERE r.id=?";
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
$sum = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM refund_records WHERE registration_id=?");
$sum->execute([$reg_id]);
$total_refunded = (float) $sum->fetchColumn();
$remaining = max(0, (float) $reg["price_snapshot"] - $total_refunded);
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        $error = "Your form session expired. Reload and try again.";
    } elseif ($is_self) {
        $error = "Another authorised manager must record your refund.";
    } else {
        $db->beginTransaction();
        $saved_file = null;
        try {
            $q = $db->prepare("SELECT * FROM registrations WHERE id=? FOR UPDATE");
            $q->execute([$reg_id]);
            $fresh = $q->fetch();
            $sum->execute([$reg_id]);
            $total_refunded = (float) $sum->fetchColumn();
            $remaining = max(0, (float) $fresh["price_snapshot"] - $total_refunded);
            $amount_text = trim($_POST["amount"] ?? "");
            $amount = round((float) $amount_text, 2);
            $reason = trim($_POST["reason"] ?? "");
            if ($fresh["status"] !== "cancellation_pending") {
                $error = "This registration is not awaiting a refund. Return to participants for its current status.";
            } elseif (
                !preg_match('/^\d+(?:\.\d{1,2})?$/', $amount_text) ||
                $amount <= 0 ||
                (int) round($amount * 100) > (int) round($remaining * 100)
            ) {
                $error =
                    "Enter a positive amount up to RM " .
                    number_format($remaining, 2) .
                    ", with at most 2 decimal places.";
            } elseif ($reason === "" || mb_strlen($reason) > 255) {
                $error = "Enter a reason or transfer reference of 1–255 characters.";
            }
            $proof_path = null;
            if (!$error && isset($_FILES["proof"]) && $_FILES["proof"]["error"] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES["proof"];
                if ($file["error"] !== UPLOAD_ERR_OK || $file["size"] > 5 * 1024 * 1024) {
                    $error = "Refund proof was not saved. Choose a file up to 5 MB and retry. No refund was recorded.";
                } else {
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file["tmp_name"]);
                    $allowed = [
                        "image/jpeg" => "jpg",
                        "image/png" => "png",
                        "image/webp" => "webp",
                        "application/pdf" => "pdf",
                    ];
                    if (!isset($allowed[$mime])) {
                        $error = "Choose JPG, PNG, WebP or PDF proof. No refund was recorded.";
                    } else {
                        $filename = "refund_" . $reg_id . "_" . bin2hex(random_bytes(8)) . "." . $allowed[$mime];
                        $dir = __DIR__ . "/../assets/uploads/refunds";
                        if (!is_dir($dir)) {
                            mkdir($dir, 0755, true);
                        }
                        $saved_file = $dir . "/" . $filename;
                        if (!move_uploaded_file($file["tmp_name"], $saved_file)) {
                            $error = "Refund proof could not be saved. Retry; no refund was recorded.";
                        } else {
                            $proof_path = "assets/uploads/refunds/" . $filename;
                        }
                    }
                }
            }
            if ($error) {
                $db->rollBack();
            } else {
                $q = $db->prepare(
                    "INSERT INTO refund_records (registration_id,amount,reason,proof_path,recorded_by) VALUES (?,?,?,?,?)",
                );
                $q->execute([$reg_id, $amount, $reason, $proof_path, $user["id"]]);
                if (
                    (int) round(($total_refunded + $amount) * 100) >= (int) round((float) $reg["price_snapshot"] * 100)
                ) {
                    $q = $db->prepare("UPDATE registrations SET status='cancelled',updated_at=NOW() WHERE id=?");
                    $q->execute([$reg_id]);
                }
                $db->commit();
                header(
                    "Location: " .
                        app_url("/manager/participants.php?session_id=" . $reg["session_id"] . "&msg=refund_logged"),
                );
                exit();
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($saved_file && is_file($saved_file)) {
                unlink($saved_file);
            }
            throw $e;
        }
    }
}
$page_title = "Record external refund";
require_once __DIR__ . "/../includes/header.php";
?>
<div class="journey-page narrow">
 <header class="journey-header"><nav class="journey-breadcrumb" aria-label="Breadcrumb"><a href="<?= app_url(
     "/manager/participants.php?session_id=" . $reg["session_id"],
 ) ?>">Participants</a> / Refund</nav><h1>Record external refund.</h1><p class="journey-subtitle"><?= e(
    $reg["student_name"],
) ?> · <?= e($reg["session_title"]) ?></p></header>
 <?php if ($error): ?><div class="journey-notice notice-error" role="alert" tabindex="-1" data-focus-error><?= e(
    $error,
) ?></div><?php endif; ?>
 <section class="journey-card"><h2>Refund progress</h2><?= get_status_badge(
     $reg["status"],
 ) ?><dl class="journey-facts"><dt>Approved payment</dt><dd><?= format_price(
    $reg["price_snapshot"],
) ?></dd><dt>Already recorded</dt><dd>RM <?= number_format(
    $total_refunded,
    2,
) ?></dd><dt>Remaining refund</dt><dd>RM <?= number_format($remaining, 2) ?></dd></dl>
  <div class="journey-callout"><strong>Private refund recipient</strong><p><?= e(
      $reg["recipient_bank_name"] ?: "Bank details not provided",
  ) ?><br><?= e($reg["recipient_account_name"] ?: $reg["student_name"]) ?><br><?= e(
    $reg["recipient_account_number"] ?: "Arrange recipient details through your existing private contact channel.",
) ?></p></div>
  <?php if (!$is_self && $reg["status"] === "cancellation_pending" && $remaining > 0.001): ?>
   <div class="journey-callout"><strong>Record a transfer already made</strong><p>SportsHub does not send money. Record the actual external transfer, including partial refunds. Cancellation stays pending until the full refund is recorded.</p></div>
   <form method="POST" enctype="multipart/form-data" autocomplete="off"><?= csrf_field() ?>
    <div class="journey-field"><label for="amount">Amount transferred (MYR)</label><input id="amount" name="amount" type="number" inputmode="decimal" min="0.01" max="<?= number_format(
        $remaining,
        2,
        ".",
        "",
    ) ?>" step="0.01" required value="<?= e($_POST["amount"] ?? number_format($remaining, 2, ".", "")) ?>"></div>
    <div class="journey-field"><label for="reason">Reason or transfer reference</label><input id="reason" name="reason" maxlength="255" required value="<?= e(
        $_POST["reason"] ?? "",
    ) ?>" placeholder="External transfer reference…"></div>
    <div class="journey-field"><label for="proof">Transfer proof (optional)</label><input type="file" id="proof" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf" aria-describedby="proof-hint"><p id="proof-hint" class="journey-help">JPG, PNG, WebP or PDF · Up to 5 MB. A supplied file must save successfully before the record is accepted.</p></div>
    <div class="journey-actions"><button class="journey-button primary" type="submit" data-pending-label="Recording refund…">Record refund transfer</button><a class="journey-button" href="<?= app_url(
        "/manager/participants.php?session_id=" . $reg["session_id"],
    ) ?>">Back to participants</a></div>
   </form>
  <?php else: ?><div class="journey-callout"><strong><?= $is_self
    ? "Another manager must record your refund"
    : "No refund action available" ?></strong><p>Check the current registration status and recorded transfers before proceeding.</p></div><a class="journey-button" href="<?= app_url(
    "/manager/participants.php?session_id=" . $reg["session_id"],
) ?>">Back to participants</a><?php endif; ?>
 </section>
</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
