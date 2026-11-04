<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_login();
$reg_id = (int)($_GET['id'] ?? 0);
$db = get_db_connection();

$stmt = $db->prepare("SELECT r.*, u.name AS student_name, u.email AS student_email, 
                             s.title AS session_title, s.club_id, s.session_date 
                      FROM registrations r 
                      INNER JOIN users u ON r.user_id = u.id 
                      INNER JOIN sessions s ON r.session_id = s.id 
                      WHERE r.id = :id LIMIT 1");
$stmt->execute([':id' => $reg_id]);
$reg = $stmt->fetch();

if (!$reg) {
    render_error_page(404);
}

if (!is_club_manager($reg['club_id'], $user['id'])) {
    render_error_page(403);
}

// Anti-self-refund rule
$is_self = ($reg['user_id'] === $user['id']);

// Calculate previously refunded amount
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM refund_records WHERE registration_id = :id");
$stmt->execute([':id' => $reg_id]);
$total_refunded = (float)$stmt->fetchColumn();
$price_owed = (float)$reg['price_snapshot'];
$remaining_owed = max(0.00, $price_owed - $total_refunded);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Session expired. Please try again.";
    } elseif ($is_self) {
        $error = "Conflict of interest: You cannot record your own refund. Another authorised manager or administrator must record it.";
    } else {
        $amount = (float)($_POST['amount'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if ($amount <= 0 || $amount > ($remaining_owed + 0.001)) {
            $error = "Refund amount must be positive and cannot exceed the remaining balance of " . format_price($remaining_owed) . ".";
        } elseif (empty($reason)) {
            $error = "Please provide an explicit reason or transaction reference for this refund.";
        } else {
            $proof_path = null;
            if (!empty($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['proof'];
                if ($file['size'] <= 5 * 1024 * 1024) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);
                    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
                    if (isset($allowed[$mime])) {
                        $ext = $allowed[$mime];
                        $filename = 'refund_' . $reg_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                        $dir = __DIR__ . '/../assets/uploads/refunds';
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        if (move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
                            $proof_path = 'assets/uploads/refunds/' . $filename;
                        }
                    }
                }
            }

            $db->beginTransaction();
            try {
                $stmt = $db->prepare("INSERT INTO refund_records (registration_id, amount, reason, proof_path, recorded_by) 
                                      VALUES (:rid, :amt, :reason, :proof, :rec_by)");
                $stmt->execute([
                    ':rid' => $reg_id,
                    ':amt' => $amount,
                    ':reason' => $reason,
                    ':proof' => $proof_path,
                    ':rec_by' => $user['id'],
                ]);

                // Check if now fully refunded
                $new_total = $total_refunded + $amount;
                if ($new_total >= ($price_owed - 0.001)) {
                    $stmt = $db->prepare("UPDATE registrations SET status = 'cancelled', updated_at = NOW() WHERE id = :id");
                    $stmt->execute([':id' => $reg_id]);
                }

                $db->commit();
                header("Location: " . app_url('/manager/participants.php?session_id=' . $reg['session_id'] . '&msg=refund_logged'));
                exit;
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
        }
    }
}

$page_title = "Record Refund · " . $reg['student_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 700px; margin: 0 auto; padding: 3rem 1.5rem;">
    <!-- Breadcrumb -->
    <div style="margin-bottom: 2rem;">
        <a href="<?= app_url('/manager/participants.php?session_id=' . $reg['session_id']) ?>" style="color: #6e6e73; text-decoration: none; font-size: 0.88rem;">← Back to Participants</a>
        <h1 style="font-size: 2rem; font-weight: 800; color: #1d1d1f; margin: 0.5rem 0 0; letter-spacing: -0.02em;">
            Record External Refund
        </h1>
    </div>

    <?php if ($error): ?>
        <div style="background: #fff2f2; border: 1px solid #fecdd3; color: #c81e27; padding: 0.85rem 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($is_self): ?>
        <div style="background: #fff2f2; border: 1px solid #fecdd3; color: #c81e27; padding: 1.25rem; border-radius: 16px; margin-bottom: 2rem;">
            <strong>Self-Refund Restriction:</strong> You are the participant for this registration. Per Taylor's SportsHub rules, another club manager or administrator must record this refund.
        </div>
    <?php endif; ?>

    <div style="background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 4px 20px rgba(0,0,0,0.04); padding: 2.5rem;">
        <!-- Recipient Information (Private Bank Details) -->
        <div style="background: #fafafa; border-radius: 16px; border: 1px solid #e5e5ea; padding: 1.5rem; margin-bottom: 2rem;">
            <span style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: #c8102e; letter-spacing: 0.05em; display: block; margin-bottom: 0.5rem;">
                Student Recipient Bank Details (Private)
            </span>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <span style="font-size: 0.8rem; color: #86868b; display: block;">Recipient Name</span>
                    <strong style="color: #1d1d1f;"><?= e($reg['recipient_account_name'] ?: $reg['student_name']) ?></strong>
                </div>
                <div>
                    <span style="font-size: 0.8rem; color: #86868b; display: block;">Bank Name</span>
                    <strong style="color: #1d1d1f;"><?= e($reg['recipient_bank_name'] ?: 'Not provided yet') ?></strong>
                </div>
                <div>
                    <span style="font-size: 0.8rem; color: #86868b; display: block;">Account Number</span>
                    <strong style="color: #1d1d1f; font-family: monospace;"><?= e($reg['recipient_account_number'] ?: 'Not provided yet') ?></strong>
                </div>
                <div>
                    <span style="font-size: 0.8rem; color: #86868b; display: block;">Remaining Refund Due</span>
                    <strong style="color: #c8102e; font-size: 1.15rem;"><?= format_price($remaining_owed) ?></strong>
                </div>
            </div>
        </div>

        <?php if (!$is_self): ?>
            <form method="POST" action="<?= $_SERVER['REQUEST_URI'] ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div style="margin-bottom: 1.5rem;">
                    <label for="amount" style="display: block; font-size: 0.88rem; font-weight: 600; color: #1d1d1f; margin-bottom: 0.4rem;">
                        Refund Amount (MYR)
                    </label>
                    <input type="number" step="0.01" min="0.01" max="<?= $remaining_owed ?>" id="amount" name="amount" value="<?= $remaining_owed ?>" required style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 1rem; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label for="reason" style="display: block; font-size: 0.88rem; font-weight: 600; color: #1d1d1f; margin-bottom: 0.4rem;">
                        Refund Reason & Reference
                    </label>
                    <input type="text" id="reason" name="reason" placeholder="e.g. DuitNow Ref #98124 - Session cancelled full refund" required style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 2rem;">
                    <label for="proof" style="display: block; font-size: 0.88rem; font-weight: 600; color: #1d1d1f; margin-bottom: 0.4rem;">
                        Transfer Proof Screenshot (optional)
                    </label>
                    <input type="file" id="proof" name="proof" accept="image/png,image/jpeg,image/webp,application/pdf" style="width: 100%; padding: 0.75rem; background: #fafafa; border: 1px solid #d2d2d7; border-radius: 10px; font-size: 0.9rem;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                    <a href="<?= app_url('/manager/participants.php?session_id=' . $reg['session_id']) ?>" class="btn-hero-secondary" style="display: inline-block;">
                        Cancel
                    </a>
                    <button type="submit" class="btn-hero-primary" style="border: none; cursor: pointer;">
                        Record Refund Completed
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
