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
            <div style="background: #f0f7ff; border: 1px solid #cce5ff; border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <h4 style="color: #004085; margin: 0 0 0.35rem; font-size: 1.05rem; font-weight: 700;">Receipt Submitted · Under Review</h4>
                        <p style="color: #424245; font-size: 0.92rem; margin: 0;">
                            Your transfer slip was submitted on <?= date('j M Y, g:i A', strtotime($reg['receipt_uploaded_at'])) ?>. Your spot is reserved while the club manager verifies payment.
                        </p>
                    </div>
                    <?php if (!empty($reg['receipt_path'])): ?>
                        <?php $is_pdf = str_ends_with(strtolower($reg['receipt_path']), '.pdf'); ?>
                        <?php if ($is_pdf): ?>
                            <a href="<?= app_url('/' . $reg['receipt_path']) ?>" target="_blank" class="btn-hero-secondary" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                                📄 Open PDF Receipt ↗
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn-hero-secondary" data-receipt-zoom="<?= app_url('/' . $reg['receipt_path']) ?>" data-receipt-title="Payment Receipt · <?= e($reg['title']) ?>" data-receipt-caption="Submitted on <?= date('j M Y, g:i A', strtotime($reg['receipt_uploaded_at'])) ?>" style="font-size: 0.85rem; padding: 0.5rem 1rem; cursor: pointer;">
                                🔍 Zoom & Preview Receipt
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($reg['receipt_path']) && !str_ends_with(strtolower($reg['receipt_path']), '.pdf')): ?>
                    <div style="margin-top: 1.25rem; display: flex; align-items: center; gap: 1rem; background: #fff; padding: 0.75rem 1rem; border-radius: 12px; border: 1px solid #d0e3ff;">
                        <img src="<?= app_url('/' . $reg['receipt_path']) ?>" alt="Receipt" style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px; cursor: pointer;" data-receipt-zoom="<?= app_url('/' . $reg['receipt_path']) ?>">
                        <div style="flex: 1;">
                            <div style="font-size: 0.88rem; font-weight: 600; color: #1d1d1f;"><?= basename($reg['receipt_path']) ?></div>
                            <span style="font-size: 0.8rem; color: #0071e3; cursor: pointer;" data-receipt-zoom="<?= app_url('/' . $reg['receipt_path']) ?>">Click to view enlarged receipt modal</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($reg['status'] === 'confirmed'): ?>
            <div style="background: #f2faf3; border: 1px solid #d1e7dd; border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <h4 style="color: #0f5132; margin: 0 0 0.35rem; font-size: 1.05rem; font-weight: 700;">Registration Confirmed</h4>
                        <p style="color: #424245; font-size: 0.92rem; margin: 0;">
                            Your booking has been approved and confirmed. See you at <?= e($reg['venue']) ?> on <?= format_session_date($reg['session_date']) ?>!
                        </p>
                    </div>
                    <?php if (!empty($reg['receipt_path'])): ?>
                        <?php $is_pdf = str_ends_with(strtolower($reg['receipt_path']), '.pdf'); ?>
                        <?php if ($is_pdf): ?>
                            <a href="<?= app_url('/' . $reg['receipt_path']) ?>" target="_blank" class="btn-hero-secondary" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                                📄 View PDF Receipt ↗
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn-hero-secondary" data-receipt-zoom="<?= app_url('/' . $reg['receipt_path']) ?>" data-receipt-title="Confirmed Receipt · <?= e($reg['title']) ?>" style="font-size: 0.85rem; padding: 0.5rem 1rem; cursor: pointer;">
                                🔍 View Receipt Modal
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
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

                <form method="POST" action="<?= app_url('/upload-receipt.php') ?>" enctype="multipart/form-data" style="background: #fafafa; border: 1px solid #e5e5ea; border-radius: 20px; padding: 2rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="registration_id" value="<?= $reg['id'] ?>">

                    <div style="margin-bottom: 1.5rem;">
                        <label for="receipt" style="display: block; font-size: 0.92rem; font-weight: 600; color: #1d1d1f; margin-bottom: 0.65rem;">
                            Transfer Evidence Slip
                        </label>

                        <!-- Apple Drag & Drop Zone -->
                        <div class="upload-dropzone" id="receipt-dropzone" onclick="document.getElementById('receipt').click()">
                            <input type="file" id="receipt" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required style="display: none;">
                            <div style="font-size: 2.25rem; margin-bottom: 0.5rem;">📤</div>
                            <div style="font-weight: 700; font-size: 1rem; color: #1d1d1f; margin-bottom: 0.35rem;">
                                Choose a file or drag & drop here
                            </div>
                            <p style="color: #6e6e73; font-size: 0.88rem; margin: 0 0 0.85rem;">
                                Upload proof of transfer (transaction slip or banking screenshot)
                            </p>
                            <div style="display: flex; gap: 0.4rem; justify-content: center; align-items: center; flex-wrap: wrap;">
                                <span class="format-pill">JPG</span>
                                <span class="format-pill">PNG</span>
                                <span class="format-pill">WEBP</span>
                                <span class="format-pill">PDF</span>
                                <span style="font-size: 0.8rem; color: #86868b; margin-left: 6px;">· Max 5 MB</span>
                            </div>
                        </div>

                        <!-- Live File Preview -->
                        <div id="receipt-upload-preview" style="display: none; align-items: center; gap: 1rem; margin-top: 1rem; background: #fff; padding: 1rem; border-radius: 12px; border: 1px solid #d2d2d7;">
                            <div class="preview-thumb" style="width: 54px; height: 54px; background: #f5f5f7; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;"></div>
                            <div style="flex: 1; min-width: 0;">
                                <div class="preview-name" style="font-weight: 600; font-size: 0.92rem; color: #1d1d1f; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></div>
                                <div class="preview-size" style="font-size: 0.82rem; color: #6e6e73; margin-top: 2px;"></div>
                            </div>
                            <span style="font-size: 0.8rem; color: #248a3d; font-weight: 600;">Ready to upload ✓</span>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <span style="font-size: 0.82rem; color: #6e6e73;">
                            Submission locks your place while managers review settlement.
                        </span>
                        <button type="submit" class="btn-hero-primary" style="border: none; cursor: pointer; padding: 0.75rem 1.85rem;">
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
