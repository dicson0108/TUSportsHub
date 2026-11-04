<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_login();
$session_id = (int)($_GET['session_id'] ?? 0);
$db = get_db_connection();

expire_overdue_reservations($session_id);

$stmt = $db->prepare("SELECT s.*, c.name AS club_name FROM sessions s INNER JOIN clubs c ON s.club_id = c.id WHERE s.id = :id LIMIT 1");
$stmt->execute([':id' => $session_id]);
$session = $stmt->fetch();

if (!$session) {
    render_error_page(404);
}

if (!is_club_manager($session['club_id'], $user['id'])) {
    render_error_page(403);
}

// Fetch all participants
$stmt = $db->prepare("SELECT r.*, u.name, u.email, u.phone 
                      FROM registrations r 
                      INNER JOIN users u ON r.user_id = u.id 
                      WHERE r.session_id = :sid 
                      ORDER BY r.created_at ASC");
$stmt->execute([':sid' => $session_id]);
$participants = $stmt->fetchAll();

$seat_info = get_session_seat_info($session_id);

$page_title = "Participants · " . $session['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 1100px; margin: 0 auto; padding: 3rem 1.5rem;">
    <!-- Breadcrumb & Header -->
    <div style="margin-bottom: 2rem;">
        <a href="<?= app_url('/manager/sessions.php') ?>" style="color: #6e6e73; text-decoration: none; font-size: 0.88rem;">← Back to Workbench Sessions</a>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-top: 0.75rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #c8102e; background: #fdf2f4; padding: 0.35rem 0.75rem; border-radius: 980px; display: inline-block; margin-bottom: 0.5rem;">
                    <?= e($session['sport_type']) ?> · <?= e($session['club_name']) ?>
                </span>
                <h1 style="font-size: 2rem; font-weight: 800; color: #1d1d1f; margin: 0 0 0.35rem; letter-spacing: -0.02em;">
                    <?= e($session['title']) ?>
                </h1>
                <div style="color: #6e6e73; font-size: 0.95rem;">
                    <?= format_session_date($session['session_date']) ?> · <?= format_session_time_range($session['start_time'], $session['end_time']) ?> · <?= e($session['venue']) ?>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <?php if ($session['status'] === 'published'): ?>
                    <a href="<?= app_url('/manager/close-signup.php?id=' . $session['id']) ?>" style="padding: 0.65rem 1.15rem; background: #f5f5f7; color: #1d1d1f; text-decoration: none; border-radius: 980px; font-size: 0.88rem; font-weight: 500; border: 1px solid #d2d2d7;">
                        Close Signup
                    </a>
                    <a href="<?= app_url('/manager/cancel-session.php?id=' . $session['id']) ?>" style="padding: 0.65rem 1.15rem; background: #fff2f2; color: #c81e27; text-decoration: none; border-radius: 980px; font-size: 0.88rem; font-weight: 500; border: 1px solid #fecdd3;">
                        Cancel Session
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem; margin-bottom: 2.5rem;">
        <div style="background: #fff; padding: 1.25rem; border-radius: 16px; border: 1px solid #e5e5ea; box-shadow: 0 4px 14px rgba(0,0,0,0.03);">
            <span style="font-size: 0.78rem; text-transform: uppercase; color: #86868b; font-weight: 600; display: block; margin-bottom: 0.25rem;">Capacity</span>
            <div style="font-size: 1.75rem; font-weight: 800; color: #1d1d1f;"><?= $seat_info['capacity'] ?></div>
        </div>
        <div style="background: #fff; padding: 1.25rem; border-radius: 16px; border: 1px solid #e5e5ea; box-shadow: 0 4px 14px rgba(0,0,0,0.03);">
            <span style="font-size: 0.78rem; text-transform: uppercase; color: #86868b; font-weight: 600; display: block; margin-bottom: 0.25rem;">Places Remaining</span>
            <div style="font-size: 1.75rem; font-weight: 800; color: <?= $seat_info['seats_left'] === 0 ? '#d70015' : '#248a3d' ?>;"><?= $seat_info['seats_left'] ?></div>
        </div>
        <div style="background: #fff; padding: 1.25rem; border-radius: 16px; border: 1px solid #e5e5ea; box-shadow: 0 4px 14px rgba(0,0,0,0.03);">
            <span style="font-size: 0.78rem; text-transform: uppercase; color: #86868b; font-weight: 600; display: block; margin-bottom: 0.25rem;">Active Holds</span>
            <div style="font-size: 1.75rem; font-weight: 800; color: #1d1d1f;"><?= $seat_info['occupied'] ?></div>
        </div>
        <div style="background: #fff; padding: 1.25rem; border-radius: 16px; border: 1px solid #e5e5ea; box-shadow: 0 4px 14px rgba(0,0,0,0.03);">
            <span style="font-size: 0.78rem; text-transform: uppercase; color: #86868b; font-weight: 600; display: block; margin-bottom: 0.25rem;">Price</span>
            <div style="font-size: 1.75rem; font-weight: 800; color: #c8102e;"><?= format_price($session['price']) ?></div>
        </div>
    </div>

    <!-- Participants Table -->
    <div style="background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 4px 20px rgba(0,0,0,0.04); overflow: hidden;">
        <div style="padding: 1.5rem 2rem; border-bottom: 1px solid #f0f0f2; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.2rem; font-weight: 700; color: #1d1d1f; margin: 0;">
                Registered Students (<?= count($participants) ?>)
            </h3>
        </div>

        <?php if (empty($participants)): ?>
            <div style="padding: 4.5rem 2rem; text-align: center; color: #6e6e73;">
                <div style="font-size: 3rem; margin-bottom: 0.75rem;">👥</div>
                <h3 style="font-size: 1.35rem; font-weight: 700; color: #1d1d1f; margin: 0 0 0.5rem; letter-spacing: -0.015em;">No registrations yet</h3>
                <p style="color: #6e6e73; font-size: 0.95rem; margin: 0 0 1.75rem; max-width: 440px; margin-left: auto; margin-right: auto; line-height: 1.5;">
                    Students haven't registered for this session yet. Share your session link with students or promote it during club practice!
                </p>
                <div style="display: flex; gap: 0.85rem; justify-content: center; align-items: center; flex-wrap: wrap;">
                    <a href="<?= app_url('/session-detail.php?id=' . $session['id']) ?>" target="_blank" class="btn-hero-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                        View Public Session Page ↗
                    </a>
                    <button type="button" data-copy-link="<?= app_url('/session-detail.php?id=' . $session['id']) ?>" class="btn-hero-secondary" style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        📋 Copy Session Link
                    </button>
                    <a href="<?= app_url('/manager/sessions.php') ?>" style="display: inline-block; padding: 0.75rem 1.4rem; background: #f5f5f7; color: #1d1d1f; border-radius: 980px; text-decoration: none; font-weight: 600; font-size: 0.92rem; border: 1px solid #d2d2d7;">
                        Back to Sessions
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.92rem;">
                    <thead>
                        <tr style="background: #fafafa; border-bottom: 1px solid #e5e5ea; color: #86868b; font-size: 0.8rem; text-transform: uppercase;">
                            <th style="padding: 1rem 1.5rem; font-weight: 600;">Student</th>
                            <th style="padding: 1rem; font-weight: 600;">Status</th>
                            <th style="padding: 1rem; font-weight: 600;">Price</th>
                            <th style="padding: 1rem; font-weight: 600;">Registered At</th>
                            <th style="padding: 1rem 1.5rem; font-weight: 600; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $p): ?>
                            <tr style="border-bottom: 1px solid #f0f0f2;">
                                <td style="padding: 1.25rem 1.5rem;">
                                    <strong style="display: block; color: #1d1d1f;"><?= e($p['name']) ?></strong>
                                    <span style="font-size: 0.82rem; color: #6e6e73;"><?= e($p['email']) ?></span>
                                    <?php if (!empty($p['phone'])): ?>
                                        <span style="font-size: 0.82rem; color: #86868b;"> · <?= e($p['phone']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <?= get_status_badge($p['status']) ?>
                                    <?php if ($p['status'] === 'awaiting_payment'): ?>
                                        <div style="font-size: 0.75rem; color: #86868b; margin-top: 0.25rem;">
                                            Due: <?= date('j M, g:i A', strtotime($p['payment_deadline'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem; font-weight: 600; color: #1d1d1f;">
                                    <?= format_price($p['price_snapshot']) ?>
                                </td>
                                <td style="padding: 1rem; color: #6e6e73; font-size: 0.85rem;">
                                    <?= date('j M Y, g:i A', strtotime($p['created_at'])) ?>
                                </td>
                                <td style="padding: 1.25rem 1.5rem; text-align: right;">
                                    <?php if ($p['status'] === 'awaiting_approval'): ?>
                                        <a href="<?= app_url('/manager/review-receipt.php?id=' . $p['id']) ?>" style="padding: 0.5rem 1rem; background: #c8102e; color: #fff; text-decoration: none; border-radius: 980px; font-size: 0.85rem; font-weight: 600;">
                                            Review Receipt ↗
                                        </a>
                                    <?php elseif ($p['status'] === 'cancellation_pending'): ?>
                                        <a href="<?= app_url('/manager/record-refund.php?id=' . $p['id']) ?>" style="padding: 0.5rem 1rem; background: #1d1d1f; color: #fff; text-decoration: none; border-radius: 980px; font-size: 0.85rem; font-weight: 600;">
                                            Record Refund ↗
                                        </a>
                                    <?php elseif (!empty($p['receipt_path'])): ?>
                                        <?php if (str_ends_with(strtolower($p['receipt_path']), '.pdf')): ?>
                                            <a href="<?= app_url('/' . $p['receipt_path']) ?>" target="_blank" style="color: #0071e3; text-decoration: none; font-size: 0.85rem; font-weight: 500;">
                                                📄 View PDF ↗
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="btn-secondary-sm" data-receipt-zoom="<?= app_url('/' . $p['receipt_path']) ?>" data-receipt-title="Receipt · <?= e($p['name']) ?> (<?= format_price($p['price_snapshot']) ?>)" style="padding: 4px 10px; font-size: 0.82rem; border-radius: 980px; cursor: pointer;">
                                                🔍 View Receipt
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #c7c7cc; font-size: 0.85rem;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
