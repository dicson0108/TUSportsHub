<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_login();
$reg_id = (int)($_GET['id'] ?? 0);
$db = get_db_connection();

$stmt = $db->prepare("SELECT r.*, u.name AS student_name, u.email AS student_email, u.phone AS student_phone, 
                             s.title AS session_title, s.sport_type, s.session_date, s.start_time, s.end_time, s.club_id, 
                             c.name AS club_name 
                      FROM registrations r 
                      INNER JOIN users u ON r.user_id = u.id 
                      INNER JOIN sessions s ON r.session_id = s.id 
                      INNER JOIN clubs c ON s.club_id = c.id 
                      WHERE r.id = :id LIMIT 1");
$stmt->execute([':id' => $reg_id]);
$reg = $stmt->fetch();

if (!$reg) {
    render_error_page(404);
}

if (!is_club_manager($reg['club_id'], $user['id'])) {
    render_error_page(403);
}

// Anti-self-approval rule
$is_self = ($reg['user_id'] === $user['id']);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Session expired. Please try again.";
    } elseif ($is_self) {
        $error = "Conflict of interest: You cannot approve or reject your own payment receipt. Another authorised manager or administrator must review it.";
    } else {
        $decision = $_POST['decision'] ?? '';

        if ($decision === 'approve') {
            $stmt = $db->prepare("UPDATE registrations 
                                  SET status = 'confirmed', rejection_reason = NULL, updated_at = NOW() 
                                  WHERE id = :id");
            $stmt->execute([':id' => $reg_id]);
            header("Location: " . app_url('/manager/participants.php?session_id=' . $reg['session_id'] . '&msg=approved'));
            exit;
        } elseif ($decision === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? '');
            if (empty($reason)) {
                $error = "Please provide a specific reason for rejecting the receipt.";
            } else {
                // Calculate new 24h window capped at session start
                $session_start = strtotime($reg['session_date'] . ' ' . $reg['start_time']);
                $twenty_four_hours_later = time() + (24 * 3600);
                $new_deadline_ts = min($twenty_four_hours_later, $session_start);
                $new_deadline = date('Y-m-d H:i:s', $new_deadline_ts);

                $stmt = $db->prepare("UPDATE registrations 
                                      SET status = 'payment_rejected', rejection_reason = :reason, 
                                          payment_deadline = :deadline, updated_at = NOW() 
                                      WHERE id = :id");
                $stmt->execute([
                    ':reason' => $reason,
                    ':deadline' => $new_deadline,
                    ':id' => $reg_id,
                ]);
                header("Location: " . app_url('/manager/participants.php?session_id=' . $reg['session_id'] . '&msg=rejected'));
                exit;
            }
        }
    }
}

$page_title = "Review Receipt · " . $reg['student_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 800px; margin: 0 auto; padding: 3rem 1.5rem;">
    <!-- Breadcrumb -->
    <div style="margin-bottom: 2rem;">
        <a href="<?= app_url('/manager/participants.php?session_id=' . $reg['session_id']) ?>" style="color: #6e6e73; text-decoration: none; font-size: 0.88rem;">← Back to Participants</a>
        <h1 style="font-size: 2rem; font-weight: 800; color: #1d1d1f; margin: 0.5rem 0 0; letter-spacing: -0.02em;">
            Review Payment Receipt
        </h1>
    </div>

    <?php if ($error): ?>
        <div style="background: #fff2f2; border: 1px solid #fecdd3; color: #c81e27; padding: 0.85rem 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($is_self): ?>
        <div style="background: #fff2f2; border: 1px solid #fecdd3; color: #c81e27; padding: 1.25rem; border-radius: 16px; margin-bottom: 2rem;">
            <strong>Self-Approval Restriction:</strong> You are the participant for this registration. Per Taylor's SportsHub audit rules, another club manager or administrator must review and approve your payment.
        </div>
    <?php endif; ?>

    <div style="background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 4px 20px rgba(0,0,0,0.04); padding: 2.5rem; margin-bottom: 2rem;">
        <!-- Student & Session info -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; border-bottom: 1px solid #f0f0f2; padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
            <div>
                <span style="font-size: 0.8rem; text-transform: uppercase; color: #86868b; font-weight: 600; display: block; margin-bottom: 0.25rem;">Participant</span>
                <strong style="font-size: 1.1rem; color: #1d1d1f; display: block;"><?= e($reg['student_name']) ?></strong>
                <span style="font-size: 0.88rem; color: #6e6e73;"><?= e($reg['student_email']) ?></span>
            </div>
            <div>
                <span style="font-size: 0.8rem; text-transform: uppercase; color: #86868b; font-weight: 600; display: block; margin-bottom: 0.25rem;">Amount Quoted</span>
                <strong style="font-size: 1.35rem; color: #c8102e;"><?= format_price($reg['price_snapshot']) ?></strong>
                <span style="font-size: 0.82rem; color: #86868b; display: block;">Uploaded <?= date('j M Y, g:i A', strtotime($reg['receipt_uploaded_at'])) ?></span>
            </div>
        </div>

        <!-- Receipt preview -->
        <div style="margin-bottom: 2rem;">
            <span style="font-size: 0.88rem; font-weight: 600; color: #1d1d1f; display: block; margin-bottom: 0.75rem;">Submitted Transfer Evidence:</span>
            <?php if (!empty($reg['receipt_path'])): ?>
                <?php $is_pdf = str_ends_with(strtolower($reg['receipt_path']), '.pdf'); ?>
                <?php if ($is_pdf): ?>
                    <div style="padding: 2rem; background: #fafafa; border: 1px dashed #d2d2d7; border-radius: 14px; text-align: center;">
                        <span style="font-size: 2.5rem; display: block; margin-bottom: 0.5rem;">📄</span>
                        <a href="<?= app_url('/' . $reg['receipt_path']) ?>" target="_blank" class="btn-hero-secondary" style="display: inline-block;">
                            Open PDF Receipt in New Tab ↗
                        </a>
                    </div>
                <?php else: ?>
                    <div style="border: 1px solid #e5e5ea; border-radius: 14px; overflow: hidden; background: #fafafa; text-align: center; padding: 1rem;">
                        <img src="<?= app_url('/' . $reg['receipt_path']) ?>" alt="Payment Receipt" style="max-width: 100%; max-height: 500px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.06);">
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div style="color: #6e6e73; font-style: italic;">No receipt file attached.</div>
            <?php endif; ?>
        </div>

        <!-- Decision Form -->
        <?php if (!$is_self): ?>
            <form method="POST" action="<?= $_SERVER['REQUEST_URI'] ?>" style="border-top: 1px solid #f0f0f2; padding-top: 2rem;">
                <?= csrf_field() ?>

                <div style="margin-bottom: 1.5rem;">
                    <label for="rejection_reason" style="display: block; font-size: 0.88rem; font-weight: 600; color: #1d1d1f; margin-bottom: 0.4rem;">
                        Rejection Reason (only required if rejecting)
                    </label>
                    <input type="text" id="rejection_reason" name="rejection_reason" placeholder="e.g. Incomplete payment screenshot or amount mismatch..." style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; box-sizing: border-box;">
                    <span style="font-size: 0.8rem; color: #86868b; display: block; margin-top: 0.35rem;">
                        Rejecting grants student an additional 24 hours (capped at session start) to resubmit evidence.
                    </span>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                    <button type="submit" name="decision" value="reject" style="padding: 0.85rem 1.5rem; background: #fff2f2; color: #c81e27; border: 1px solid #fecdd3; border-radius: 980px; font-weight: 600; font-size: 0.95rem; cursor: pointer;">
                        ✕ Reject Receipt
                    </button>
                    <button type="submit" name="decision" value="approve" class="btn-hero-primary" style="border: none; cursor: pointer;">
                        ✓ Approve Payment
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
