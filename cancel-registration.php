<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();
$reg_id = (int)($_GET['id'] ?? 0);
$db = get_db_connection();
expire_overdue_reservations();

$stmt = $db->prepare("SELECT r.*, s.title, s.sport_type, s.session_date, s.start_time, s.end_time, s.venue, s.status AS session_status, c.name AS club_name
                      FROM registrations r 
                      INNER JOIN sessions s ON r.session_id = s.id 
                      INNER JOIN clubs c ON s.club_id = c.id 
                      WHERE r.id = :id AND r.user_id = :uid LIMIT 1");
$stmt->execute([':id' => $reg_id, ':uid' => $user['id']]);
$reg = $stmt->fetch();

if (!$reg) {
    render_error_page(404);
}

// Check session start boundary
$session_start = strtotime($reg['session_date'] . ' ' . $reg['start_time']);
if ($session_start <= time()) {
    header("Location: " . app_url('/my-activities.php?msg=cannot_cancel_past_start'));
    exit;
}
if (!can_cancel_registration($reg)) {
    header('Location: ' . app_url('/payment.php?id=' . $reg_id . '&msg=invalid_status'));
    exit;
}

$is_paid_hold = ((float)$reg['price_snapshot'] > 0.001 && in_array($reg['status'], ['confirmed', 'awaiting_approval']));
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Session expired. Please try again.";
    } else {
        $db->beginTransaction();
        $lock = $db->prepare('SELECT status FROM sessions WHERE id=? FOR UPDATE');
        $lock->execute([$reg['session_id']]);
        $reg['session_status'] = $lock->fetchColumn();
        $lock = $db->prepare('SELECT * FROM registrations WHERE id=? FOR UPDATE');
        $lock->execute([$reg_id]);
        $fresh = $lock->fetch();
        if (!can_cancel_registration(array_merge($reg, $fresh))) {
            $db->rollBack();
            header('Location: ' . app_url('/payment.php?id=' . $reg_id . '&msg=invalid_status'));
            exit;
        }
        $reg = array_merge($reg, $fresh);
        $is_paid_hold = ((float)$reg['price_snapshot'] > .001 && in_array($reg['status'], ['confirmed','awaiting_approval']));
        if ($is_paid_hold) {
            $bank_name = trim($_POST['recipient_bank_name'] ?? '');
            $account_name = trim($_POST['recipient_account_name'] ?? '');
            $account_number = trim($_POST['recipient_account_number'] ?? '');

            if (empty($bank_name) || empty($account_name) || empty($account_number)) {
                $error = "Please provide your bank name, account holder name, and account number to receive your full refund.";
            } elseif (mb_strlen($bank_name)>100 || mb_strlen($account_name)>150 || mb_strlen($account_number)>50) {
                $error = 'Use at most 100 characters for bank name, 150 for account holder and 50 for account number.';
            } else {
                $stmt = $db->prepare("UPDATE registrations 
                                      SET status = :next_status, cancellation_requested_at = NOW(),
                                          cancellation_reason = 'Participant voluntary withdrawal', 
                                          recipient_bank_name = :bank, recipient_account_name = :acc_name, 
                                          recipient_account_number = :acc_no, updated_at = NOW() 
                                      WHERE id = :id");
                $stmt->execute([
                    ':next_status' => $reg['status'] === 'awaiting_approval' ? 'awaiting_approval' : 'cancellation_pending',
                    ':bank' => $bank_name,
                    ':acc_name' => $account_name,
                    ':acc_no' => $account_number,
                    ':id' => $reg_id,
                ]);
                $db->commit();
                header("Location: " . app_url('/my-activities.php?msg=cancellation_pending'));
                exit;
            }
        } else {
            // Unpaid or free registration -> cancel immediately and release place
            $stmt = $db->prepare("UPDATE registrations 
                                  SET status = 'cancelled', cancellation_requested_at = NOW(), 
                                      cancellation_reason = 'Participant cancelled prior to payment/commencement', 
                                      updated_at = NOW() 
                                  WHERE id = :id");
            $stmt->execute([':id' => $reg_id]);
            $db->commit();
            header("Location: " . app_url('/my-activities.php?msg=cancelled'));
            exit;
        }
        if ($db->inTransaction()) $db->rollBack();
    }
}

$page_title = "Cancel Registration · " . $reg['title'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="journey-page narrow">
    <div class="journey-card">
        <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #c81e27; background: #fff2f2; padding: 0.35rem 0.75rem; border-radius: 980px; display: inline-block; margin-bottom: 0.75rem;">
            Withdrawal Request
        </span>

        <h1 style="font-size: 1.85rem; font-weight: 800; color: #1d1d1f; margin: 0 0 0.5rem; letter-spacing: -0.02em;">
            Cancel Registration?
        </h1>
        <p style="color: #6e6e73; font-size: 0.95rem; line-height: 1.5; margin: 0 0 1.5rem;">
            You are requesting to withdraw from <strong><?= e($reg['title']) ?></strong> on <?= format_session_date($reg['session_date']) ?>.
        </p>

        <?php if ($error): ?>
            <div class="journey-notice notice-error" role="alert" tabindex="-1" data-focus-error>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($is_paid_hold): ?>
            <div style="background: #fafafa; border-radius: 14px; padding: 1.25rem; border: 1px solid #e5e5ea; margin-bottom: 1.75rem;">
                <div style="font-size: 0.95rem; font-weight: 700; color: #1d1d1f; margin-bottom: 0.35rem;"><?= $reg['status']==='awaiting_approval'?'Settlement review before refund':'Full refund entitlement' ?> (<?= format_price($reg['price_snapshot']) ?>)</div>
                <p style="font-size: 0.85rem; color: #6e6e73; margin: 0;">
                    <?= $reg['status']==='awaiting_approval'?'Your receipt is still under review. The organiser must check whether money arrived before resolving cancellation. If payment is confirmed, a full external refund is due.':'Requesting cancellation before session start entitles you to a full external refund of your approved payment.' ?> Your place stays held until resolution. Recipient details are private to you and authorised club managers.
                </p>
            </div>

            <form method="POST" autocomplete="off">
                <?= csrf_field() ?>

                <div style="margin-bottom: 1.25rem;">
                    <label for="recipient_bank_name" style="display: block; font-size: 0.88rem; font-weight: 600; color: #1d1d1f; margin-bottom: 0.4rem;">
                        Your Bank Name
                    </label>
                    <input type="text" id="recipient_bank_name" name="recipient_bank_name" maxlength="100" value="<?= e($_POST['recipient_bank_name']??'') ?>" placeholder="Bank name…" required style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label for="recipient_account_name" style="display: block; font-size: 0.88rem; font-weight: 600; color: #1d1d1f; margin-bottom: 0.4rem;">
                        Account Holder Full Name
                    </label>
                    <input type="text" id="recipient_account_name" name="recipient_account_name" maxlength="150" value="<?= e($_POST['recipient_account_name']??$user['name']) ?>" required style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 2rem;">
                    <label for="recipient_account_number" style="display: block; font-size: 0.88rem; font-weight: 600; color: #1d1d1f; margin-bottom: 0.4rem;">
                        Account / Phone Number
                    </label>
                    <input type="text" id="recipient_account_number" name="recipient_account_number" maxlength="50" value="<?= e($_POST['recipient_account_number']??'') ?>" placeholder="Recipient account number…" required style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; box-sizing: border-box;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                    <a href="<?= app_url('/my-activities.php') ?>" class="btn-hero-secondary" style="display: inline-block;">
                        Keep Registration
                    </a>
                    <button type="submit" style="padding: 0.85rem 1.5rem; background: #c81e27; color: #fff; border: none; border-radius: 980px; font-weight: 600; font-size: 0.95rem; cursor: pointer;">
                        Request cancellation
                    </button>
                </div>
            </form>
        <?php else: ?>
            <form method="POST" autocomplete="off">
                <?= csrf_field() ?>
                <p style="font-size: 0.9rem; color: #424245; margin-bottom: 2rem;">
                    Cancelling will immediately release your reserved spot so another student can participate.
                </p>
                <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                    <a href="<?= app_url('/my-activities.php') ?>" class="btn-hero-secondary" style="display: inline-block;">
                        Keep Registration
                    </a>
                    <button type="submit" style="padding: 0.85rem 1.5rem; background: #c81e27; color: #fff; border: none; border-radius: 980px; font-weight: 600; font-size: 0.95rem; cursor: pointer;">
                        Yes, Cancel Spot
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
