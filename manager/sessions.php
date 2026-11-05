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

// Executive metrics across all managed sessions
$all_mgr_sql = "SELECT s.status,
                (SELECT COUNT(*) FROM registrations r WHERE r.session_id = s.id AND r.status = 'awaiting_approval') AS pending_review_count
                FROM sessions s WHERE s.club_id IN ($in_clause)";
$all_mgr_rows = $db->query($all_mgr_sql)->fetchAll();

$metric_total = count($all_mgr_rows);
$metric_published = count(array_filter($all_mgr_rows, fn($r) => $r['status'] === 'published'));
$metric_draft = count(array_filter($all_mgr_rows, fn($r) => $r['status'] === 'draft'));
$metric_pending_receipts = array_sum(array_column($all_mgr_rows, 'pending_review_count'));

$page_title = "Manager Workbench · Sessions";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="manager-container">
    <!-- Workbench Header -->
    <div class="manager-header-row">
        <div class="manager-title-group">
            <span class="manager-eyebrow">Club Operations</span>
            <h1 class="manager-title">Manager Workbench</h1>
            <p class="manager-desc">
                Manage club sessions, review payment receipts, and record refunds.
            </p>
        </div>

        <a href="<?= app_url('/manager/session-edit.php') ?>" class="manager-btn-create">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>Create New Session</span>
        </a>
    </div>

    <!-- Executive Metrics Grid -->
    <div class="manager-metrics-grid">
        <div class="manager-metric-card">
            <div class="manager-metric-val"><?= $metric_total ?></div>
            <div class="manager-metric-label">All Sessions</div>
        </div>
        <div class="manager-metric-card">
            <div class="manager-metric-val"><?= $metric_published ?></div>
            <div class="manager-metric-label">Published Active</div>
        </div>
        <div class="manager-metric-card">
            <div class="manager-metric-val <?= $metric_pending_receipts > 0 ? 'alert' : '' ?>"><?= $metric_pending_receipts ?></div>
            <div class="manager-metric-label">Receipts to Review</div>
        </div>
        <div class="manager-metric-card">
            <div class="manager-metric-val"><?= $metric_draft ?></div>
            <div class="manager-metric-label">Draft Sessions</div>
        </div>
    </div>

    <!-- Status Filter Tabs -->
    <div class="manager-filter-tabs">
        <a href="<?= app_url('/manager/sessions.php') ?>" class="manager-tab <?= $filter_status === '' ? 'active' : '' ?>">
            All Sessions (<?= $metric_total ?>)
        </a>
        <a href="<?= app_url('/manager/sessions.php?status=published') ?>" class="manager-tab <?= $filter_status === 'published' ? 'active' : '' ?>">
            Published
        </a>
        <a href="<?= app_url('/manager/sessions.php?status=draft') ?>" class="manager-tab <?= $filter_status === 'draft' ? 'active' : '' ?>">
            Drafts
        </a>
        <a href="<?= app_url('/manager/sessions.php?status=closed') ?>" class="manager-tab <?= $filter_status === 'closed' ? 'active' : '' ?>">
            Closed
        </a>
        <a href="<?= app_url('/manager/sessions.php?status=cancelled') ?>" class="manager-tab <?= $filter_status === 'cancelled' ? 'active' : '' ?>">
            Cancelled
        </a>
    </div>

    <!-- Sessions List / Cards -->
    <?php if (empty($sessions)): ?>
        <div class="manager-empty-state">
            <svg class="manager-empty-icon" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg>
            <h3 class="manager-empty-title">
                <?= $filter_status !== '' ? 'No ' . e($filter_status) . ' sessions found' : 'No club sessions created yet' ?>
            </h3>
            <p class="manager-empty-desc">
                <?= $filter_status !== '' ? 'Try selecting another status tab or view all club sessions.' : 'Get started by creating your club\'s first training session or social open play.' ?>
            </p>
            <div class="manager-empty-actions">
                <a href="<?= app_url('/manager/session-edit.php') ?>" class="manager-btn-create">
                    + Create New Session
                </a>
                <?php if ($filter_status !== ''): ?>
                    <a href="<?= app_url('/manager/sessions.php') ?>" class="manager-empty-btn-all">
                        Show All Sessions
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="manager-card-list">
            <?php foreach ($sessions as $s): ?>
                <div class="manager-card">
                    <div class="manager-card-main">
                        <div class="manager-card-badge-row">
                            <?= get_status_badge($s['status']) ?>
                            <span class="manager-card-club-tag"><?= e($s['club_name']) ?> · <?= e($s['sport_type']) ?></span>
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

                    <!-- Participant & Review Stats + Actions -->
                    <div class="manager-card-aside">
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
                                <div class="manager-stat-label alert-text">Receipts to Review</div>
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
