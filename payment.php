<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();
$reg_id = (int)($_GET['id'] ?? 0);
$db = get_db_connection();

expire_overdue_reservations();

$stmt = $db->prepare("SELECT r.*, s.title, s.sport_type, s.venue, s.session_date, s.start_time, s.end_time, s.status AS session_status, 
                             c.id AS club_id, c.name AS club_name, c.bank_name, c.account_holder, c.account_number 
                      FROM registrations r 
                      INNER JOIN sessions s ON r.session_id = s.id 
                      INNER JOIN clubs c ON s.club_id = c.id 
                      WHERE r.id = :id LIMIT 1");
$stmt->execute([':id' => $reg_id]);
$reg = $stmt->fetch();

if (!$reg) {
    render_error_page(404);
}

// Access check: only the participant or an authorised club manager / admin can view
if ($reg['user_id'] !== $user['id'] && !is_club_manager($reg['club_id'], $user['id'])) {
    render_error_page(403);
}

$deadline_ts = strtotime($reg['payment_deadline'] ?? '');
$is_expired = ($reg['status'] === 'expired' || ($deadline_ts && $deadline_ts <= time() && in_array($reg['status'], ['awaiting_payment', 'payment_rejected'])));

$page_title = "Payment & Receipt Submission · " . $reg['title'];
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 800px; margin: 0 auto; padding: 3rem 1.5rem;">
    <!-- Breadcrumb -->
    <div style="font-size: 0.88rem; color: #86868b; margin-bottom: 1.5rem;">
        <a href="<?= app_url('/my-activities.php') ?>" style="color: #6e6e73; text-decoration: none;">My Activities</a>
        <span> · </span>
        <span>Payment & Receipt</span>
    </div>

    <!-- Status Alert Header -->
    <div style="background: #fff; border-radius: 24px; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 4px 20px rgba(0,0,0,0.04); padding: 2.5rem; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
            <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #c8102e; background: #fdf2f4; padding: 0.35rem 0.75rem; border-radius: 980px;">
                <?= e($reg['sport_type']) ?>
            </span>
            <div>
                <?= get_status_badge($reg['status']) ?>
            </div>
        </div>

        <h1 style="font-size: 1.85rem; font-weight: 800; color: #1d1d1f; margin: 0 0 0.5rem; letter-spacing: -0.02em;">
            <?= e($reg['title']) ?>
        </h1>
        <div style="color: #6e6e73; font-size: 0.95rem; margin-bottom: 1.5rem;">
            <?= e($reg['club_name']) ?> · <?= format_session_date($reg['session_date']) ?>, <?= format_session_time_range($reg['start_time'], $reg['end_time']) ?>
        </div>

        <!-- Summary Amount Card -->
        <div style="background: #fafafa; border-radius: 16px; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border: 1px solid #ebebed;">
            <div>
                <span style="font-size: 0.82rem; text-transform: uppercase; font-weight: 600; color: #86868b; display: block; margin-bottom: 0.2rem;">Amount Due</span>
                <span style="font-size: 1.75rem; font-weight: 800; color: #c8102e;"><?= format_price($reg['price_snapshot']) ?></span>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 0.82rem; text-transform: uppercase; font-weight: 600; color: #86868b; display: block; margin-bottom: 0.2rem;">Reservation Deadline</span>
                <span style="font-size: 0.95rem; font-weight: 600; color: <?= $is_expired ? '#d70015' : '#1d1d1f' ?>;">
                    <?php if ($is_expired): ?>
                        Expired
                    <?php else: ?>
                        <?= format_session_time($reg['payment_deadline']) ?> · <?= format_session_date($reg['payment_deadline']) ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <?php if ($reg['status'] === 'payment_rejected'): ?>
            <div style="background: #fff2f2; border: 1px solid #fecdd3; border-radius: 14px; padding: 1.25rem; margin-bottom: 2rem;">
                <h4 style="color: #c81e27; margin: 0 0 0.35rem; font-size: 1rem; font-weight: 700;">Previous Receipt Rejected by Manager</h4>
                <p style="color: #424245; font-size: 0.92rem; margin: 0 0 0.5rem;">
                    <strong>Reason:</strong> <?= e($reg['rejection_reason'] ?: 'Evidence could not be verified.') ?>
                </p>
                <p style="color: #6e6e73; font-size: 0.85rem; margin: 0;">
                    Please check the transfer details below and upload an updated payment receipt before your deadline expires.
                </p>
            </div>
        <?php endif; ?>

        <?php if ($reg['status'] === 'awaiting_approval'): ?>
            <div style="background: #f0f7ff; border: 1px solid #cce5ff; border-radius: 14px; padding: 1.25rem; margin-bottom: 2rem;">
                <h4 style="color: #004085; margin: 0 0 0.25rem; font-size: 1rem; font-weight: 700;">Receipt Submitted · Under Review</h4>
                <p style="color: #424245; font-size: 0.92rem; margin: 0 0 0.5rem;">
                    Your receipt was submitted on <?= date('j M Y, g:i A', strtotime($reg['receipt_uploaded_at'])) ?>. Your place is held while the club manager verifies external settlement.
                </p>
                <?php if (!empty($reg['receipt_path'])): ?>
                    <div style="margin-top: 1rem;">
                        <a href="<?= app_url('/' . $reg['receipt_path']) ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.88rem; color: #0071e3; font-weight: 600; text-decoration: none;">
                            📄 View Uploaded Receipt ↗
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($reg['status'] === 'confirmed'): ?>
            <div style="background: #f2faf3; border: 1px solid #d1e7dd; border-radius: 14px; padding: 1.25rem; margin-bottom: 2rem;">
                <h4 style="color: #0f5132; margin: 0 0 0.25rem; font-size: 1rem; font-weight: 700;">Registration Confirmed</h4>
                <p style="color: #424245; font-size: 0.92rem; margin: 0;">
                    Your place has been approved and confirmed. See you at <?= e($reg['venue']) ?> on <?= format_session_date($reg['session_date']) ?>!
                </p>
            </div>
        <?php endif; ?>

        <!-- Official Bank / DuitNow Instructions -->
        <?php if (in_array($reg['status'], ['awaiting_payment', 'payment_rejected'])): ?>
            <div style="margin-bottom: 2.5rem;">
                <h3 style="font-size: 1.15rem; font-weight: 700; color: #1d1d1f; margin: 0 0 1rem;">
                    Step 1: Transfer Externally
                </h3>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; background: #fff; border: 1px solid #e5e5ea; border-radius: 16px; padding: 1.5rem;">
                    <!-- DuitNow QR simulation card -->
                    <div style="border: 2px dashed #d2d2d7; border-radius: 14px; padding: 1.5rem; text-align: center; background: #fafafa;">
                        <div style="display: inline-block; padding: 0.25rem 0.65rem; background: #e30613; color: #fff; font-size: 0.75rem; font-weight: 800; border-radius: 6px; margin-bottom: 0.75rem;">
                            DuitNow QR
                        </div>
                        <div style="width: 140px; height: 140px; margin: 0 auto 0.75rem; background: #fff; border: 1px solid #d2d2d7; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 2.5rem;">
                            📱
                        </div>
                        <span style="font-size: 0.8rem; color: #86868b; display: block;">Scan with any Malaysian banking app</span>
                    </div>

                    <!-- Bank details -->
                    <div>
                        <div style="margin-bottom: 1rem;">
                            <span style="font-size: 0.78rem; text-transform: uppercase; color: #86868b; font-weight: 600; display: block;">Bank Name</span>
                            <span style="font-size: 1rem; font-weight: 600; color: #1d1d1f;"><?= e($reg['bank_name']) ?></span>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <span style="font-size: 0.78rem; text-transform: uppercase; color: #86868b; font-weight: 600; display: block;">Account Holder</span>
                            <span style="font-size: 1rem; font-weight: 600; color: #1d1d1f;"><?= e($reg['account_holder']) ?></span>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <span style="font-size: 0.78rem; text-transform: uppercase; color: #86868b; font-weight: 600; display: block;">Account Number</span>
                            <span style="font-size: 1.15rem; font-weight: 700; color: #c8102e; letter-spacing: 0.05em;"><?= e($reg['account_number']) ?></span>
                        </div>
                        <p style="font-size: 0.82rem; color: #86868b; margin: 0; line-height: 1.4;">
                            * Make payment via DuitNow or Online Banking transfer. Save or take a screenshot of your transfer confirmation slip.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Receipt Upload Form -->
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 700; color: #1d1d1f; margin: 0 0 1rem;">
                    Step 2: Upload Payment Receipt
                </h3>

                <form method="POST" action="<?= app_url('/upload-receipt.php') ?>" enctype="multipart/form-data" style="background: #fafafa; border: 1px solid #e5e5ea; border-radius: 16px; padding: 1.75rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="registration_id" value="<?= $reg['id'] ?>">

                    <div style="margin-bottom: 1.25rem;">
                        <label for="receipt" style="display: block; font-size: 0.9rem; font-weight: 600; color: #1d1d1f; margin-bottom: 0.5rem;">
                            Select Receipt File (PNG, JPG, WebP, or PDF - max 5MB)
                        </label>
                        <input type="file" id="receipt" name="receipt" accept="image/png,image/jpeg,image/webp,application/pdf" required style="width: 100%; padding: 0.75rem; background: #fff; border: 1px solid #d2d2d7; border-radius: 10px; font-size: 0.9rem;">
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <span style="font-size: 0.82rem; color: #6e6e73;">
                            Submission locks your place for manager review.
                        </span>
                        <button type="submit" class="btn-hero-primary" style="border: none; cursor: pointer; padding: 0.75rem 1.75rem;">
                            Submit Receipt →
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Voluntary Cancellation -->
        <?php if (!in_array($reg['status'], ['cancelled', 'expired'])): ?>
            <div style="margin-top: 2.5rem; border-top: 1px solid #f0f0f2; padding-top: 1.25rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.88rem;">
                <span style="color: #86868b;">Need to change plans?</span>
                <a href="<?= app_url('/cancel-registration.php?id=' . $reg['id']) ?>" style="color: #c81e27; text-decoration: none; font-weight: 500;">
                    Cancel this registration
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
