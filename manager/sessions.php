<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_login();
$managed_clubs = get_user_managed_clubs($user['id']);
$is_admin = ($user['role'] === 'admin');

if (empty($managed_clubs) && !$is_admin) {
    render_error_page(403);
}

$db = get_db_connection();
expire_overdue_reservations();

// If admin, can view all clubs; otherwise only managed clubs
if ($is_admin) {
    $club_ids = $db->query("SELECT id FROM clubs")->fetchAll(PDO::FETCH_COLUMN);
} else {
    $club_ids = array_column($managed_clubs, 'id');
}

$in_clause = implode(',', array_map('intval', $club_ids));

$filter_status = trim($_GET['status'] ?? '');
$sql = "SELECT s.*, c.name AS club_name, 
        (SELECT COUNT(*) FROM registrations r WHERE r.session_id = s.id AND r.status = 'confirmed') AS confirmed_count,
        (SELECT COUNT(*) FROM registrations r WHERE r.session_id = s.id AND r.status = 'awaiting_approval') AS pending_review_count,
        (SELECT COUNT(*) FROM registrations r WHERE r.session_id = s.id AND r.status IN ('awaiting_payment', 'payment_rejected')) AS awaiting_payment_count,
        (SELECT COUNT(*) FROM registrations r WHERE r.session_id = s.id AND r.status = 'cancellation_pending') AS pending_refund_count
        FROM sessions s 
        INNER JOIN clubs c ON s.club_id = c.id 
        WHERE s.club_id IN ($in_clause)";

if ($filter_status !== '') {
    $sql .= " AND s.status = " . $db->quote($filter_status);
}
$sql .= " ORDER BY s.session_date DESC, s.start_time DESC";

$sessions = $db->query($sql)->fetchAll();

$page_title = "Manager Workbench · Sessions";
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 3rem 1.5rem;">
    <!-- Workbench Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: #c8102e; letter-spacing: 0.05em; margin-bottom: 0.25rem;">
                Club Operations
            </div>
            <h1 style="font-size: 2.25rem; font-weight: 800; color: #1d1d1f; margin: 0 0 0.5rem; letter-spacing: -0.02em;">
                Manager Workbench
            </h1>
            <p style="color: #6e6e73; font-size: 0.95rem; margin: 0;">
                Manage club sessions, review payment receipts, and record refunds.
            </p>
        </div>

        <a href="<?= app_url('/manager/session-edit.php') ?>" class="btn-hero-primary" style="display: inline-block;">
            + Create New Session
        </a>
    </div>

    <!-- Status filter tabs -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 2rem; overflow-x: auto; padding-bottom: 0.5rem;">
        <a href="<?= app_url('/manager/sessions.php') ?>" style="padding: 0.5rem 1rem; border-radius: 980px; text-decoration: none; font-size: 0.88rem; font-weight: 500; <?= $filter_status === '' ? 'background: #1d1d1f; color: #fff;' : 'background: #f5f5f7; color: #424245;' ?>">
            All Sessions (<?= count($sessions) ?>)
        </a>
        <a href="<?= app_url('/manager/sessions.php?status=published') ?>" style="padding: 0.5rem 1rem; border-radius: 980px; text-decoration: none; font-size: 0.88rem; font-weight: 500; <?= $filter_status === 'published' ? 'background: #1d1d1f; color: #fff;' : 'background: #f5f5f7; color: #424245;' ?>">
            Published
        </a>
        <a href="<?= app_url('/manager/sessions.php?status=draft') ?>" style="padding: 0.5rem 1rem; border-radius: 980px; text-decoration: none; font-size: 0.88rem; font-weight: 500; <?= $filter_status === 'draft' ? 'background: #1d1d1f; color: #fff;' : 'background: #f5f5f7; color: #424245;' ?>">
            Drafts
        </a>
        <a href="<?= app_url('/manager/sessions.php?status=closed') ?>" style="padding: 0.5rem 1rem; border-radius: 980px; text-decoration: none; font-size: 0.88rem; font-weight: 500; <?= $filter_status === 'closed' ? 'background: #1d1d1f; color: #fff;' : 'background: #f5f5f7; color: #424245;' ?>">
            Closed
        </a>
        <a href="<?= app_url('/manager/sessions.php?status=cancelled') ?>" style="padding: 0.5rem 1rem; border-radius: 980px; text-decoration: none; font-size: 0.88rem; font-weight: 500; <?= $filter_status === 'cancelled' ? 'background: #1d1d1f; color: #fff;' : 'background: #f5f5f7; color: #424245;' ?>">
            Cancelled
        </a>
    </div>

    <!-- Sessions Table / Cards -->
    <?php if (empty($sessions)): ?>
        <div style="background: #fff; border-radius: 20px; padding: 4.5rem 2rem; text-align: center; border: 1px solid #e5e5ea; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
            <div style="font-size: 3rem; margin-bottom: 0.75rem;">📋</div>
            <h3 style="font-size: 1.35rem; font-weight: 700; color: #1d1d1f; margin: 0 0 0.5rem; letter-spacing: -0.015em;">
                <?= $filter_status !== '' ? 'No ' . e($filter_status) . ' sessions found' : 'No club sessions created yet' ?>
            </h3>
            <p style="color: #6e6e73; font-size: 0.95rem; margin: 0 0 1.75rem; max-width: 440px; margin-left: auto; margin-right: auto; line-height: 1.5;">
                <?= $filter_status !== '' ? 'Try selecting another status tab or view all club sessions.' : 'Get started by creating your club\'s first training session or social open play.' ?>
            </p>
            <div style="display: flex; gap: 0.75rem; justify-content: center; align-items: center; flex-wrap: wrap;">
                <a href="<?= app_url('/manager/session-edit.php') ?>" class="btn-hero-primary" style="display: inline-block;">
                    + Create New Session
                </a>
                <?php if ($filter_status !== ''): ?>
                    <a href="<?= app_url('/manager/sessions.php') ?>" style="display: inline-block; padding: 0.75rem 1.4rem; background: #f5f5f7; color: #1d1d1f; border-radius: 980px; text-decoration: none; font-weight: 600; font-size: 0.92rem; border: 1px solid #d2d2d7;">
                        Show All Sessions
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            <?php foreach ($sessions as $s): ?>
                <div style="background: #fff; border-radius: 18px; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 4px 18px rgba(0,0,0,0.03); padding: 1.5rem 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
                    <div style="flex: 1; min-width: 280px;">
                        <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.5rem;">
                            <?= get_status_badge($s['status']) ?>
                            <span style="font-size: 0.8rem; font-weight: 600; color: #86868b;"><?= e($s['club_name']) ?> · <?= e($s['sport_type']) ?></span>
                        </div>

                        <h3 style="font-size: 1.25rem; font-weight: 700; color: #1d1d1f; margin: 0 0 0.5rem;">
                            <?= e($s['title']) ?>
                        </h3>

                        <div style="font-size: 0.88rem; color: #424245; display: flex; flex-wrap: wrap; gap: 1.25rem;">
                            <span>📅 <?= format_session_date($s['session_date']) ?></span>
                            <span>⏰ <?= format_session_time_range($s['start_time'], $s['end_time']) ?></span>
                            <span>💰 <?= format_price($s['price']) ?></span>
                            <span>📍 <?= e($s['venue']) ?></span>
                        </div>
                    </div>

                    <!-- Participant & Review stats -->
                    <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                        <div style="text-align: center; padding: 0.5rem 1rem; background: #fafafa; border-radius: 12px; border: 1px solid #ebebed;">
                            <div style="font-size: 1.2rem; font-weight: 700; color: #1d1d1f;">
                                <?= $s['confirmed_count'] ?> / <?= $s['capacity'] ?>
                            </div>
                            <div style="font-size: 0.75rem; color: #86868b; text-transform: uppercase;">Confirmed</div>
                        </div>

                        <?php if ($s['pending_review_count'] > 0): ?>
                            <div style="text-align: center; padding: 0.5rem 1rem; background: #fff2f2; border-radius: 12px; border: 1px solid #fecdd3;">
                                <div style="font-size: 1.2rem; font-weight: 700; color: #c8102e;">
                                    <?= $s['pending_review_count'] ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #c8102e; text-transform: uppercase; font-weight: 600;">Receipts to Review</div>
                            </div>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <a href="<?= app_url('/manager/participants.php?session_id=' . $s['id']) ?>" style="padding: 0.65rem 1.15rem; background: #c8102e; color: #fff; text-decoration: none; border-radius: 980px; font-weight: 600; font-size: 0.88rem;">
                                Participants (<?= $s['confirmed_count'] + $s['pending_review_count'] + $s['awaiting_payment_count'] ?>)
                            </a>

                            <?php if ($s['status'] === 'draft'): ?>
                                <a href="<?= app_url('/manager/session-edit.php?id=' . $s['id']) ?>" style="padding: 0.65rem 1.15rem; background: #f5f5f7; color: #1d1d1f; text-decoration: none; border-radius: 980px; font-weight: 500; font-size: 0.88rem; border: 1px solid #d2d2d7;">
                                    Edit Draft
                                </a>
                            <?php elseif ($s['status'] === 'published'): ?>
                                <a href="<?= app_url('/manager/close-signup.php?id=' . $s['id']) ?>" style="padding: 0.65rem 1rem; background: #f5f5f7; color: #1d1d1f; text-decoration: none; border-radius: 980px; font-size: 0.85rem; border: 1px solid #d2d2d7;">
                                    Close Signup
                                </a>
                                <a href="<?= app_url('/manager/cancel-session.php?id=' . $s['id']) ?>" style="padding: 0.65rem 1rem; background: #fff2f2; color: #c81e27; text-decoration: none; border-radius: 980px; font-size: 0.85rem; border: 1px solid #fecdd3;">
                                    Cancel Session
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
