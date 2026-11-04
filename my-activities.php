<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();
$db = get_db_connection();

expire_overdue_reservations();

// Fetch student's registrations
$stmt = $db->prepare("SELECT r.*, s.title, s.sport_type, s.venue, s.session_date, s.start_time, s.end_time, s.status AS session_status, 
                             c.name AS club_name, c.slug AS club_slug 
                      FROM registrations r 
                      INNER JOIN sessions s ON r.session_id = s.id 
                      INNER JOIN clubs c ON s.club_id = c.id 
                      WHERE r.user_id = :uid 
                      ORDER BY s.session_date DESC, s.start_time DESC");
$stmt->execute([':uid' => $user['id']]);
$all_registrations = $stmt->fetchAll();

$active_registrations = [];
$past_registrations = [];

$now = time();
foreach ($all_registrations as $reg) {
    $sess_end = strtotime($reg['session_date'] . ' ' . $reg['end_time']);
    if (in_array($reg['status'], ['confirmed', 'awaiting_payment', 'awaiting_approval', 'payment_rejected', 'cancellation_pending']) && $sess_end > $now) {
        $active_registrations[] = $reg;
    } else {
        $past_registrations[] = $reg;
    }
}

$page_title = "My Activities";
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 1000px; margin: 0 auto; padding: 3rem 1.5rem;">
    <!-- Header -->
    <div style="margin-bottom: 2.5rem;">
        <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #c8102e; background: #fdf2f4; padding: 0.35rem 0.75rem; border-radius: 980px; display: inline-block; margin-bottom: 0.5rem;">
            Student Dashboard
        </span>
        <h1 style="font-size: 2.25rem; font-weight: 800; color: #1d1d1f; margin: 0 0 0.5rem; letter-spacing: -0.02em;">
            My Sports Activities
        </h1>
        <p style="color: #6e6e73; font-size: 0.95rem; margin: 0;">
            Track your current session places, payment review statuses, and historical activity.
        </p>
    </div>

    <!-- Active & Upcoming Section -->
    <div style="margin-bottom: 3.5rem;">
        <h2 style="font-size: 1.4rem; font-weight: 700; color: #1d1d1f; margin: 0 0 1.25rem;">
            Active Registrations & Reserved Spots (<?= count($active_registrations) ?>)
        </h2>

        <?php if (empty($active_registrations)): ?>
            <div style="background: #fff; border-radius: 20px; padding: 3.5rem 2rem; text-align: center; border: 1px solid #e5e5ea; box-shadow: 0 4px 18px rgba(0,0,0,0.03);">
                <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">🏃‍♂️</div>
                <h3 style="font-size: 1.15rem; font-weight: 600; color: #1d1d1f; margin: 0 0 0.5rem;">No active registrations</h3>
                <p style="color: #6e6e73; font-size: 0.9rem; margin: 0 0 1.5rem;">You haven't joined any upcoming sessions yet.</p>
                <a href="<?= app_url('/sessions.php') ?>" class="btn-hero-primary" style="display: inline-block;">
                    Explore Upcoming Sessions
                </a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <?php foreach ($active_registrations as $r): ?>
                    <div style="background: #fff; border-radius: 18px; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 4px 20px rgba(0,0,0,0.04); padding: 1.75rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
                        <div style="flex: 1; min-width: 280px;">
                            <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.5rem;">
                                <?= get_status_badge($r['status']) ?>
                                <span style="font-size: 0.82rem; font-weight: 600; color: #86868b;"><?= e($r['sport_type']) ?> · <?= e($r['club_name']) ?></span>
                            </div>

                            <h3 style="font-size: 1.25rem; font-weight: 700; color: #1d1d1f; margin: 0 0 0.5rem;">
                                <?= e($r['title']) ?>
                            </h3>

                            <div style="font-size: 0.88rem; color: #424245; display: flex; flex-direction: column; gap: 0.35rem;">
                                <div>📅 <?= format_session_date($r['session_date']) ?> · <?= format_session_time_range($r['start_time'], $r['end_time']) ?></div>
                                <div>📍 <?= e($r['venue']) ?> · 💰 <?= format_price($r['price_snapshot']) ?></div>
                            </div>
                        </div>

                        <!-- Status Context & Actions -->
                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.75rem;">
                            <?php if ($r['status'] === 'awaiting_payment' || $r['status'] === 'payment_rejected'): ?>
                                <a href="<?= app_url('/payment.php?id=' . $r['id']) ?>" style="padding: 0.7rem 1.35rem; background: #c8102e; color: #fff; text-decoration: none; border-radius: 980px; font-weight: 600; font-size: 0.9rem;">
                                    Upload Receipt →
                                </a>
                                <span style="font-size: 0.78rem; color: #d70015;">
                                    Deadline: <?= date('j M, g:i A', strtotime($r['payment_deadline'])) ?>
                                </span>
                            <?php elseif ($r['status'] === 'awaiting_approval'): ?>
                                <a href="<?= app_url('/payment.php?id=' . $r['id']) ?>" style="padding: 0.65rem 1.25rem; background: #f0f7ff; color: #0071e3; text-decoration: none; border-radius: 980px; font-weight: 600; font-size: 0.88rem; border: 1px solid #cce5ff;">
                                    Receipt Under Review
                                </a>
                            <?php elseif ($r['status'] === 'confirmed'): ?>
                                <a href="<?= app_url('/session-detail.php?id=' . $r['session_id']) ?>" style="padding: 0.65rem 1.25rem; background: #f5f5f7; color: #1d1d1f; text-decoration: none; border-radius: 980px; font-weight: 600; font-size: 0.88rem; border: 1px solid #d2d2d7;">
                                    View Session
                                </a>
                                <a href="<?= app_url('/cancel-registration.php?id=' . $r['id']) ?>" style="font-size: 0.82rem; color: #c81e27; text-decoration: none;">
                                    Cancel Registration
                                </a>
                            <?php elseif ($r['status'] === 'cancellation_pending'): ?>
                                <span style="font-size: 0.85rem; color: #b25000; font-weight: 500;">
                                    Awaiting external manager refund
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Past & History Section -->
    <?php if (!empty($past_registrations)): ?>
        <div>
            <h2 style="font-size: 1.3rem; font-weight: 700; color: #86868b; margin: 0 0 1.25rem;">
                Past & Concluded Sessions (<?= count($past_registrations) ?>)
            </h2>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($past_registrations as $pr): ?>
                    <div style="background: #fafafa; border-radius: 16px; border: 1px solid #e5e5ea; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                                <?= get_status_badge($pr['status']) ?>
                                <strong style="color: #424245;"><?= e($pr['title']) ?></strong>
                            </div>
                            <span style="font-size: 0.82rem; color: #86868b;">
                                <?= e($pr['club_name']) ?> · <?= format_session_date($pr['session_date']) ?> · <?= format_price($pr['price_snapshot']) ?>
                            </span>
                        </div>

                        <div>
                            <a href="<?= app_url('/session-detail.php?id=' . $pr['session_id']) ?>" style="color: #6e6e73; text-decoration: none; font-size: 0.85rem;">
                                View Details →
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
