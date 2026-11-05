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
        <div class="manager-empty-state">
            <svg class="manager-empty-icon" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg>
            <h3 class="manager-empty-title">
                <?= $filter_status !== '' ? 'No ' . e($filter_status) . ' sessions found' : 'No club sessions created yet' ?>
            </h3>
            <p class="manager-empty-desc">
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
        <div class="manager-card-list">
            <?php foreach ($sessions as $s): ?>
                <div class="manager-card">
                    <div style="flex: 1; min-width: 280px;">
                        <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.5rem;">
                            <?= get_status_badge($s['status']) ?>
                            <span style="font-size: 0.8rem; font-weight: 600; color: #86868b;"><?= e($s['club_name']) ?> · <?= e($s['sport_type']) ?></span>
                        </div>

                        <h3 class="manager-card-title">
                            <?= e($s['title']) ?>
                        </h3>

                        <div class="manager-meta-row">
                            <span class="manager-meta-item">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                                <span><?= format_session_date($s['session_date']) ?></span>
                            </span>
                            <span class="manager-meta-item">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <span><?= format_session_time_range($s['start_time'], $s['end_time']) ?></span>
                            </span>
                            <span class="manager-meta-item">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                <span><?= format_price($s['price']) ?></span>
                            </span>
                            <span class="manager-meta-item">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                <span><?= e($s['venue']) ?></span>
                            </span>
                        </div>
                    </div>

                    <!-- Participant & Review stats -->
                    <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                        <div class="manager-stat-badge">
                            <div class="manager-stat-val">
                                <?= $s['confirmed_count'] ?> / <?= $s['capacity'] ?>
                            </div>
                            <div class="manager-stat-label">Confirmed</div>
                        </div>

                        <?php if ($s['pending_review_count'] > 0): ?>
                            <div class="manager-stat-badge alert">
                                <div class="manager-stat-val">
                                    <?= $s['pending_review_count'] ?>
                                </div>
                                <div class="manager-stat-label" style="color: #c8102e;">Receipts to Review</div>
                            </div>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="manager-btn-group">
                            <a href="<?= app_url('/manager/participants.php?session_id=' . $s['id']) ?>" class="manager-btn manager-btn-primary">
                                Participants (<?= $s['confirmed_count'] + $s['pending_review_count'] + $s['awaiting_payment_count'] ?>)
                            </a>

                            <?php if ($s['status'] === 'draft'): ?>
                                <a href="<?= app_url('/manager/session-edit.php?id=' . $s['id']) ?>" class="manager-btn manager-btn-secondary">
                                    Edit Draft
                                </a>
                            <?php elseif ($s['status'] === 'published'): ?>
                                <a href="<?= app_url('/manager/close-signup.php?id=' . $s['id']) ?>" class="manager-btn manager-btn-secondary">
                                    Close Signup
                                </a>
                                <a href="<?= app_url('/manager/cancel-session.php?id=' . $s['id']) ?>" class="manager-btn manager-btn-danger">
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
